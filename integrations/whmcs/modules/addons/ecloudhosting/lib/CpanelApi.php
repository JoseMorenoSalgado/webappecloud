<?php
declare(strict_types=1);

/**
 * Account-scoped cPanel UAPI adapter.
 * Token is never sent to the browser, emitted in logs, or embedded in HTML.
 */
final class ECloudCpanelApi
{
    private string $host;
    private string $username;
    private string $token;

    public function __construct(string $host, string $username, string $token, array $allowedHosts)
    {
        $host = strtolower($host);
        if (!preg_match('/^[a-z0-9][a-z0-9.-]{2,252}$/D', $host)
            || !in_array($host, $allowedHosts, true)
            || !preg_match('/^[a-z][a-z0-9]{0,15}$/D', $username)
            || strlen($token) < 20 || preg_match('/[\r\n]/', $token)) {
            throw new RuntimeException('La configuración de acceso a cPanel no es válida.');
        }
        $this->host = $host;
        $this->username = $username;
        $this->token = $token;
    }

    public function call(string $module, string $operation, array $params = [], bool $write = false)
    {
        $allowed = [
            'Email' => ['list_pops', 'add_pop', 'passwd_pop', 'edit_pop_quota'],
            'DNS' => ['parse_zone', 'has_local_authority', 'mass_edit_zone'],
        ];
        if (!in_array($operation, $allowed[$module] ?? [], true)) {
            throw new RuntimeException('Operación cPanel no autorizada.');
        }
        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $endpoint = 'https://' . $this->host . ':2083/execute/' . $module . '/' . $operation;
        $ch = curl_init($endpoint . (!$write && $query !== '' ? '?' . $query : ''));
        if ($ch === false) throw new RuntimeException('No se pudo iniciar la conexión.');
        try {
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: cpanel ' . $this->username . ':' . $this->token,
                    'Accept: application/json',
                ],
                CURLOPT_CONNECTTIMEOUT => 7,
                CURLOPT_TIMEOUT => 25,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            // cPanel UAPI accepts form-encoded POST for state-changing requests.
            // Confirm compatibility with each server version before production.
            if ($write) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: cpanel ' . $this->username . ':' . $this->token,
                    'Accept: application/json',
                    'Content-Type: application/x-www-form-urlencoded',
                ]);
            }
            $response = curl_exec($ch);
            $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (!is_string($response) || $http !== 200 || strlen($response) > 1048576) {
                throw new RuntimeException('cPanel no respondió correctamente.');
            }
            $payload = json_decode($response, true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($payload) || !isset($payload['result']) || !is_array($payload['result'])
                || (int) ($payload['result']['status'] ?? 0) !== 1) {
                throw new RuntimeException('cPanel rechazó la operación.');
            }
            return $payload['result']['data'] ?? null;
        } finally {
            curl_close($ch);
        }
    }
}
