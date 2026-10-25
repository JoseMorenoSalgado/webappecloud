<?php
declare(strict_types=1);

/**
 * WHMCS client mail operations.
 *
 * Email remains a SEPARATE active cPanel hosting service and is billed by its
 * own WHMCS product. An admin-managed private map binds a Dokploy service ID
 * to that cPanel service ID and to a per-account cPanel UAPI token.
 * Every request checks both active service owners and exact matching domains.
 */
function eclouddokploy_email_binding_file(int $serviceId): ?array
{
    $path = (string) getenv('ECLOUD_DOKPLOY_EMAIL_MAP_FILE');
    if ($path === '' || $path[0] !== '/' || is_link($path)
        || !is_file($path) || !is_readable($path) || filesize($path) > 131072) {
        return null;
    }
    // Fail closed: credential map must have owner-only permissions.
    $mode = fileperms($path);
    if ($mode === false || ($mode & 0077) !== 0) {
        return null;
    }
    $real = realpath($path);
    $docroot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($real === false || ($docroot !== false
        && ($real === $docroot || str_starts_with($real, $docroot . DIRECTORY_SEPARATOR)))) {
        return null;
    }
    $data = json_decode((string) file_get_contents($path), true, 12);
    if (!is_array($data) || !isset($data[(string) $serviceId])
        || !is_array($data[(string) $serviceId])) {
        return null;
    }
    $entry = $data[(string) $serviceId];
    $target = filter_var($entry['email_service_id'] ?? null, FILTER_VALIDATE_INT);
    $limit = filter_var($entry['mailbox_limit'] ?? null, FILTER_VALIDATE_INT);
    $quota = filter_var($entry['max_quota_mb'] ?? null, FILTER_VALIDATE_INT);
    $token = $entry['api_token'] ?? null;
    if ($target === false || $target < 1 || $target === $serviceId
        || $limit === false || $limit < 1 || $limit > 100
        || $quota === false || $quota < 100 || $quota > 102400
        || !is_string($token) || strlen($token) < 16 || strlen($token) > 2048
        || preg_match('/[\x00-\x20\x7f]/', $token)) {
        return null;
    }
    return [
        'email_service_id' => $target,
        'api_token' => $token,
        'mailbox_limit' => $limit,
        'max_quota_mb' => $quota,
    ];
}

/**
 * Authoritative WHMCS ownership checks. Never accept a domain, server, cPanel
 * username, email service ID, or API URL from the browser.
 */
function eclouddokploy_email_binding(int $appServiceId, int $ownerId, string $domain): ?array
{
    if ($appServiceId <= 0 || $ownerId <= 0
        || !eclouddokploy_valid_domain($domain)) return null;
    $entry = eclouddokploy_email_binding_file($appServiceId);
    if ($entry === null) return null;

    $db = \WHMCS\Database\Capsule::table('tblhosting');
    $app = $db->where('id', $appServiceId)
        ->where('userid', $ownerId)->first(['id', 'domain', 'domainstatus', 'packageid']);
    if (!$app || (string) $app->domainstatus !== 'Active'
        || strtolower(rtrim((string) $app->domain, '.')) !== $domain) return null;

    $mail = \WHMCS\Database\Capsule::table('tblhosting')
        ->where('id', $entry['email_service_id'])
        ->where('userid', $ownerId)
        ->first(['id', 'domain', 'domainstatus', 'packageid', 'server', 'username']);
    if (!$mail || (string) $mail->domainstatus !== 'Active'
        || strtolower(rtrim((string) $mail->domain, '.')) !== $domain
        || (int) $mail->server <= 0) return null;

    // Only an actual cPanel product/service can authorize cPanel UAPI access.
    $product = \WHMCS\Database\Capsule::table('tblproducts')
        ->where('id', (int) $mail->packageid)->first(['servertype']);
    if (!$product || strtolower((string) $product->servertype) !== 'cpanel') return null;

    $server = \WHMCS\Database\Capsule::table('tblservers')
        ->where('id', (int) $mail->server)->first(['hostname']);
    $host = strtolower(trim((string) ($server->hostname ?? '')));
    $user = strtolower(trim((string) $mail->username));
    if (!eclouddokploy_valid_server_hostname($host)
        || !preg_match('/^[a-z][a-z0-9]{0,15}$/D', $user)) return null;

    return [
        'email_service_id' => (int) $mail->id,
        'domain' => $domain,
        'host' => $host,
        'username' => $user,
        'api_token' => $entry['api_token'],
        'mailbox_limit' => $entry['mailbox_limit'],
        'max_quota_mb' => $entry['max_quota_mb'],
    ];
}

function eclouddokploy_email_csrf(int $serviceId): string
{
    if ($serviceId <= 0) throw new InvalidArgumentException('Invalid WHMCS service.');
    if (!isset($_SESSION['ecloud_mail_csrf']) || !is_array($_SESSION['ecloud_mail_csrf'])) {
        $_SESSION['ecloud_mail_csrf'] = [];
    }
    if (!isset($_SESSION['ecloud_mail_csrf'][$serviceId])
        || !is_string($_SESSION['ecloud_mail_csrf'][$serviceId])
        || strlen($_SESSION['ecloud_mail_csrf'][$serviceId]) !== 64) {
        $_SESSION['ecloud_mail_csrf'][$serviceId] = bin2hex(random_bytes(32));
    }
    return $_SESSION['ecloud_mail_csrf'][$serviceId];
}

function eclouddokploy_email_client(array $binding): ECloudCpanelMailClient
{
    return new ECloudCpanelMailClient(
        $binding['host'], $binding['username'], $binding['api_token']
    );
}

function eclouddokploy_email_list(ECloudCpanelMailClient $api, string $domain): array
{
    return eclouddokploy_mail_rows(
        $api->call('list_pops_with_disk', ['domain' => $domain, 'skip_main' => 1]),
        $domain
    );
}

function eclouddokploy_email_exists(array $rows, string $local): bool
{
    foreach ($rows as $row) {
        if (($row['local'] ?? '') === $local) return true;
    }
    return false;
}

/** Simple last-operation cooldown to throttle brute forcing and accidental double submit. */
function eclouddokploy_email_cooldown(int $serviceId): bool
{
    $schema = \WHMCS\Database\Capsule::schema();
    if (!$schema->hasTable('mod_ecloud_dokploy_mail_cooldown')) {
        $schema->create('mod_ecloud_dokploy_mail_cooldown', function ($table) {
            $table->unsignedInteger('service_id')->primary();
            $table->dateTime('last_action_at')->nullable();
        });
    }
    $table = \WHMCS\Database\Capsule::table('mod_ecloud_dokploy_mail_cooldown');
    $row = $table->where('service_id', $serviceId)->first(['last_action_at']);
    if ($row && $row->last_action_at
        && strtotime((string) $row->last_action_at) > time() - 20) {
        return false;
    }
    $table->updateOrInsert(
        ['service_id' => $serviceId],
        ['last_action_at' => date('Y-m-d H:i:s')]
    );
    return true;
}

/** Called ONLY via WHMCS module custom actions; returns no remote response. */
function eclouddokploy_email_action(array $params): string
{
    if (getenv('ECLOUD_DOKPLOY_ENABLE_EMAIL_READS') !== '1'
        || getenv('ECLOUD_DOKPLOY_ENABLE_EMAIL_WRITES') !== '1') {
        return 'La administración de correo no está habilitada.';
    }
    $id = (int) ($params['serviceid'] ?? 0);
    $owner = (int) ($params['userid'] ?? 0);
    $session = (int) ($_SESSION['uid'] ?? 0);
    $domain = strtolower(rtrim(trim((string) ($params['domain'] ?? '')), '.'));
    if ($id <= 0 || $owner <= 0 || $owner !== $session
        || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        || (int) ($_POST['id'] ?? 0) !== $id
        || (string) ($_POST['modop'] ?? '') !== 'custom'
        || (string) ($_POST['a'] ?? '') !== 'EmailAction') {
        return 'Operación de correo no autorizada.';
    }
    $csrf = (string) ($_POST['ec_mail_csrf'] ?? '');
    if (strlen($csrf) !== 64
        || !hash_equals(eclouddokploy_email_csrf($id), $csrf)) {
        return 'El formulario de correo expiró. Actualiza esta página.';
    }
    $op = (string) ($_POST['ec_mail_op'] ?? '');
    $local = strtolower(trim((string) ($_POST['ec_mail_local'] ?? '')));
    if (!in_array($op, ['create', 'password', 'quota', 'delete'], true)
        || !eclouddokploy_mail_valid_local($local)) {
        return 'Cuenta u operación de correo incorrecta.';
    }
    if ($op === 'delete' && getenv('ECLOUD_DOKPLOY_ENABLE_EMAIL_DELETE') !== '1') {
        return 'La eliminación de buzones está desactivada.';
    }
    $password = (string) ($_POST['ec_mail_password'] ?? '');
    if (in_array($op, ['create', 'password'], true)
        && (!eclouddokploy_mail_valid_password($password)
            || !hash_equals($password, (string) ($_POST['ec_mail_confirm_password'] ?? '')))) {
        return 'La contraseña debe tener al menos 14 caracteres, mayúscula, minúscula, número y símbolo; ambas deben coincidir.';
    }
    $quota = $_POST['ec_mail_quota'] ?? '';
    try {
        $binding = eclouddokploy_email_binding($id, $owner, $domain);
        if ($binding === null) return 'No hay un servicio de correo cPanel activo y vinculado a esta aplicación.';
        if (in_array($op, ['create', 'quota'], true)
            && !eclouddokploy_mail_valid_quota($quota, $binding['max_quota_mb'])) {
            return 'La cuota no cumple los límites del plan contratado.';
        }
        $address = eclouddokploy_mail_address($local, $domain);
        if ($op === 'delete'
            && ((string) ($_POST['ec_mail_delete_confirmation'] ?? '') !== 'ELIMINAR ' . $address
                || (string) ($_POST['ec_mail_ack'] ?? '') !== 'yes')) {
            return 'Debes confirmar la eliminación permanente del buzón y sus mensajes.';
        }

        $db = \WHMCS\Database\Capsule::connection();
        $lock = 'ecloud-mail-' . $id;
        $lockRow = $db->selectOne('SELECT GET_LOCK(?, 5) AS acquired', [$lock]);
        if (!$lockRow || (int) $lockRow->acquired !== 1) {
            return 'Otra operación de correo está en proceso.';
        }
        try {
            if (!eclouddokploy_email_cooldown($id)) {
                return 'Espera 20 segundos entre operaciones de correo.';
            }
            $api = eclouddokploy_email_client($binding);
            $rows = eclouddokploy_email_list($api, $domain);
            $exists = eclouddokploy_email_exists($rows, $local);
            if ($op === 'create') {
                if ($exists) return 'Esa dirección de correo ya existe.';
                if (count($rows) >= $binding['mailbox_limit']) {
                    return 'Se alcanzó el máximo de buzones del plan contratado.';
                }
                $api->call('add_pop', [
                    'domain' => $domain,
                    'email' => $local,
                    'password' => $password,
                    'quota' => (int) $quota,
                    'send_welcome_email' => 0,
                ], true);
            } else {
                if (!$exists) return 'El buzón no existe en la cuenta de correo contratada.';
                if ($op === 'password') {
                    $api->call('passwd_pop', [
                        'domain' => $domain, 'email' => $local, 'password' => $password,
                    ], true);
                } elseif ($op === 'quota') {
                    $api->call('edit_pop_quota', [
                        'domain' => $domain, 'email' => $local, 'quota' => (int) $quota,
                    ], true);
                } else {
                    $api->call('delete_pop', ['domain' => $domain, 'email' => $local], true);
                }
            }
            return 'success';
        } finally {
            $db->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
        }
    } catch (Throwable $e) {
        // No password, mailbox, host, cPanel account, UAPI response or token is logged/echoed.
        return 'No se completó la operación de correo. Verifica el buzón o contacta con soporte.';
    }
}
