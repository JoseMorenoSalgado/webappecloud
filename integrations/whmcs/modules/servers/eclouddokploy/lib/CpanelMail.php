<?php
declare(strict_types=1);

/**
 * cPanel Email UAPI adapter, isolated from Dokploy provisioning.
 *
 * Credentials are per-cPanel-account API tokens loaded on the WHMCS server,
 * never obtained from a customer request or sent to Smarty.
 */
function eclouddokploy_mail_valid_local(string $local): bool
{
    return (bool) preg_match('/^[a-z0-9][a-z0-9._+-]{0,62}$/D', $local)
        && !str_contains($local, '..')
        && !str_ends_with($local, '.')
        && !in_array($local, ['cpanel'], true);
}

function eclouddokploy_mail_valid_password(string $password): bool
{
    return strlen($password) >= 14
        && strlen($password) <= 128
        && preg_match('/^[\x21-\x7e]+$/D', $password) === 1
        && preg_match('/[A-Z]/', $password) === 1
        && preg_match('/[a-z]/', $password) === 1
        && preg_match('/[0-9]/', $password) === 1
        && preg_match('/[^a-zA-Z0-9]/', $password) === 1;
}

function eclouddokploy_mail_valid_quota($value, int $maxMb): bool
{
    return is_numeric($value)
        && is_scalar($value)
        && preg_match('/^[0-9]{1,6}$/D', (string) $value) === 1
        && (int) $value >= 100 && (int) $value <= $maxMb;
}

function eclouddokploy_mail_address(string $local, string $domain): string
{
    if (!eclouddokploy_mail_valid_local($local)
        || !eclouddokploy_valid_domain($domain)) {
        throw new InvalidArgumentException('Invalid mailbox address.');
    }
    return $local . '@' . $domain;
}

/** Show ONLY address + bounded quota/usage, never raw fields from cPanel. */
function eclouddokploy_mail_rows($data, string $domain): array
{
    if (!is_array($data) || !array_is_list($data) || count($data) > 1000) {
        throw new RuntimeException('Unexpected email mailbox listing.');
    }
    $rows = [];
    foreach ($data as $item) {
        if (!is_array($item)) continue;
        $email = strtolower(trim((string) ($item['email'] ?? '')));
        $parts = explode('@', $email);
        if (count($parts) !== 2 || $parts[1] !== $domain
            || !eclouddokploy_mail_valid_local($parts[0])) {
            continue;
        }
        $quota = $item['diskquota'] ?? $item['quota'] ?? null;
        $quotaLabel = 'Según servidor';
        if (is_numeric($quota) && (int) $quota === 0) {
            $quotaLabel = 'Sin límite';
        } elseif (is_numeric($quota) && (int) $quota > 0 && (int) $quota <= 1048576) {
            $quotaLabel = (int) $quota . ' MB';
        }
        $rows[$email] = [
            'address' => $email,
            'local' => $parts[0],
            'quota' => $quotaLabel,
        ];
    }
    ksort($rows, SORT_STRING);
    return array_values($rows);
}

final class ECloudCpanelMailClient
{
    private string $host;
    private string $username;
    private string $token;

    public function __construct(string $host, string $username, string $token)
    {
        if (!eclouddokploy_valid_server_hostname($host)
            || !preg_match('/^[a-z][a-z0-9]{0,15}$/Di', $username)
            || strlen($token) < 16 || strlen($token) > 2048
            || preg_match('/[\x00-\x20\x7f]/', $token)) {
            throw new InvalidArgumentException('Invalid cPanel mail API configuration.');
        }
        $this->host = $host;
        $this->username = $username;
        $this->token = $token;
    }

    public function call(string $action, array $params = [], bool $write = false)
    {
        $actions = [
            'list_pops_with_disk' => false,
            'add_pop' => true,
            'passwd_pop' => true,
            'edit_pop_quota' => true,
            'delete_pop' => true,
        ];
        if (!array_key_exists($action, $actions) || $actions[$action] !== $write) {
            throw new InvalidArgumentException('Unsupported mail API operation.');
        }
        // Never put mailbox passwords into a URL or access log.
        $url = 'https://' . $this->host . ':2083/execute/Email/' . $action;
        if (!$write && $params) {
            $url .= '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Mail API initialization failed.');
        }
        try {
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER => [
                    'Authorization: cpanel ' . $this->username . ':' . $this->token,
                    'Accept: application/json',
                ],
                CURLOPT_USERAGENT => 'ElearningCloud-WHMCS-Mail/1.0',
            ]);
            if ($write) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params, '', '&', PHP_QUERY_RFC3986));
            }
            $response = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (!is_string($response) || strlen($response) > 262144
                || $code !== 200) {
                // Don't include remote response, request body, URL or token in exceptions.
                throw new RuntimeException('Mail API request failed.');
            }
            $json = json_decode($response, true, 24, JSON_THROW_ON_ERROR);
            if (!is_array($json) || !isset($json['result']) || !is_array($json['result'])
                || (int) ($json['result']['status'] ?? 0) !== 1) {
                throw new RuntimeException('Mail API operation was rejected.');
            }
            return $json['result']['data'] ?? null;
        } finally {
            curl_close($ch);
        }
    }
}
