<?php
declare(strict_types=1);

/**
 * Elearning Cloud: self-service cPanel email and DNS inside the WHMCS client area.
 * Does not replace the official WHMCS cPanel provisioning module.
 */
if (!defined('WHMCS')) exit('Direct access not allowed');

require_once __DIR__ . '/lib/CpanelApi.php';
require_once __DIR__ . '/lib/Dns.php';

use WHMCS\Database\Capsule;

function ecloudhosting_config(): array
{
    return [
        'name' => 'Elearning Cloud — Correo y DNS',
        'description' => 'Panel de correo y DNS por cliente, con servicios de cPanel administrados desde WHMCS.',
        'version' => '0.1.0',
        'author' => 'Elearning Cloud',
        'language' => 'spanish',
        'fields' => [],
    ];
}

function ecloudhosting_activate(): array
{
    return ['status' => 'success', 'description' => 'Módulo activado sin modificar cuentas ni zonas. Configurar el archivo privado de cuentas.'];
}

function ecloudhosting_deactivate(): array
{
    return ['status' => 'success', 'description' => 'Módulo desactivado. No se eliminaron cuentas ni correos.'];
}

function ecloudhosting_output($vars): void
{
    echo '<h2>Elearning Cloud — Correo y DNS</h2>';
    echo '<p>Portal para clientes: <code>index.php?m=ecloudhosting</code>.</p>';
    echo '<p>El módulo requiere un mapa privado de servicios cPanel y credenciales UAPI independientes.</p>';
    echo '<p>Los cambios de correo y DNS están bloqueados hasta activar sus interruptores de entorno y verificar staging.</p>';
}

function ecloudhosting_accounts_map(): array
{
    $path = (string) getenv('ECLOUD_CPANEL_ACCOUNTS_FILE');
    if ($path === '' || $path[0] !== '/' || is_link($path) || !is_file($path) || !is_readable($path)
        || filesize($path) > 262144 || ((fileperms($path) & 0077) !== 0)) {
        throw new RuntimeException('El mapa privado de cuentas cPanel no está disponible o es inseguro.');
    }
    $map = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($map) || !isset($map['services']) || !is_array($map['services'])) {
        throw new RuntimeException('Formato del mapa de cuentas incorrecto.');
    }
    return $map['services'];
}

function ecloudhosting_owned_services(int $clientId, array $map): array
{
    if ($clientId <= 0) return [];
    $list = Capsule::table('tblhosting')->where('userid', $clientId)
        ->where('domainstatus', 'Active')->select(['id', 'packageid', 'domain', 'username', 'userid'])->get();
    $result = [];
    foreach ($list as $service) {
        $sid = (string) $service->id;
        $cfg = $map[$sid] ?? null;
        if (!is_array($cfg)) continue;
        // Bind the portal only to the existing WHMCS email product for this service.
        $expectedPackageId = filter_var($cfg['package_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$expectedPackageId || $expectedPackageId !== (int) $service->packageid) continue;
        $domain = strtolower(rtrim((string) $service->domain, '.'));
        if ($domain !== strtolower(rtrim((string) ($cfg['domain'] ?? ''), '.'))) continue;
        if ((string) $service->username !== (string) ($cfg['username'] ?? '')) continue;
        if (!ecloudhosting_domain_valid($domain)) continue;
        $result[$sid] = [
            'service_id' => (int) $service->id,
            'package_id' => (int) $service->packageid,
            'domain' => $domain,
            'host' => (string) ($cfg['host'] ?? ''),
            'username' => (string) ($cfg['username'] ?? ''),
            'token' => (string) ($cfg['token'] ?? ''),
            'nameservers' => is_array($cfg['nameservers'] ?? null) ? $cfg['nameservers'] : [],
            'dns_edit' => ($cfg['dns_edit'] ?? false) === true,
        ];
    }
    return $result;
}

function ecloudhosting_domain_valid(string $domain): bool
{
    if (strlen($domain) > 253 || !str_contains($domain, '.')) return false;
    foreach (explode('.', $domain) as $part) {
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $part)) return false;
    }
    return true;
}

function ecloudhosting_csrf(): string
{
    if (!isset($_SESSION['ecloudhosting_csrf']) || !is_string($_SESSION['ecloudhosting_csrf'])) {
        $_SESSION['ecloudhosting_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['ecloudhosting_csrf'];
}

function ecloudhosting_post(ECloudCpanelApi $api, array $account, array $post, bool $canDns): string
{
    if (!hash_equals(ecloudhosting_csrf(), (string) ($post['csrf'] ?? ''))) {
        throw new RuntimeException('La sesión del formulario expiró.');
    }
    $action = (string) ($post['ecloud_action'] ?? '');
    $domain = $account['domain'];
    if (in_array($action, ['email_create', 'email_password', 'email_quota'], true)) {
        if (getenv('ECLOUD_CPANEL_ENABLE_MAIL_WRITES') !== '1') {
            throw new RuntimeException('La administración de correo aún no está habilitada.');
        }
        $local = strtolower(trim((string) ($post['email_local'] ?? '')));
        if (!preg_match('/^[a-z0-9](?:[a-z0-9._+-]{0,62}[a-z0-9])?$/D', $local)
            || str_contains($local, '..')) throw new InvalidArgumentException('Nombre de buzón no válido.');
        if ($action === 'email_create' || $action === 'email_password') {
            $password = (string) ($post['password'] ?? '');
            if (strlen($password) < 14 || strlen($password) > 128 ||
                preg_match('/[\r\n\x00]/', $password)) {
                throw new InvalidArgumentException('Utiliza una contraseña de al menos 14 caracteres.');
            }
            $method = $action === 'email_create' ? 'add_pop' : 'passwd_pop';
            $args = ['email' => $local, 'domain' => $domain, 'password' => $password];
            if ($action === 'email_create') {
                $quota = filter_var($post['quota'] ?? null, FILTER_VALIDATE_INT);
                if ($quota === false || $quota < 100 || $quota > 20480) {
                    throw new InvalidArgumentException('La cuota debe estar entre 100 y 20480 MB.');
                }
                $args['quota'] = $quota;
            }
            $api->call('Email', $method, $args, true);
            unset($password, $args);
            logActivity('ECloud Hosting: ' . $action . ' service=' . $account['service_id']);
            return $action === 'email_create' ? 'Cuenta de correo creada.' : 'Contraseña de correo actualizada.';
        }
        $quota = filter_var($post['quota'] ?? null, FILTER_VALIDATE_INT);
        if ($quota === false || $quota < 100 || $quota > 20480) {
            throw new InvalidArgumentException('La cuota debe estar entre 100 y 20480 MB.');
        }
        $api->call('Email', 'edit_pop_quota', ['email' => $local, 'domain' => $domain, 'quota' => $quota], true);
        logActivity('ECloud Hosting: email quota updated service=' . $account['service_id']);
        return 'Cuota del buzón actualizada.';
    }
    if (in_array($action, ['dns_add', 'dns_edit'], true)) {
        if (!$canDns) throw new RuntimeException('Edición DNS deshabilitada o dominio no autoritativo.');
        ecloudhosting_dns_apply($api, $domain, $post);
        logActivity('ECloud Hosting: ' . $action . ' service=' . $account['service_id']);
        return 'Registro DNS guardado.';
    }
    throw new InvalidArgumentException('Operación no admitida.');
}

function ecloudhosting_clientarea($vars): array
{
    // WHMCS authenticates the visitor before rendering this addon page.
    $clientId = (int) ($_SESSION['uid'] ?? 0);
    $base = [
        'pagetitle' => 'Mis correos y DNS',
        'breadcrumb' => ['index.php?m=ecloudhosting' => 'Mis correos y DNS'],
        'templatefile' => 'clienthome',
        'requirelogin' => true,
        'vars' => [
            'ecAccounts' => [], 'ecSelected' => null, 'ecMail' => [],
            'ecDns' => [], 'ecCanDns' => false, 'ecCanMail' => false,
            'ecMessage' => '', 'ecError' => '', 'ecToken' => '',
            'ecModuleLink' => 'index.php?m=ecloudhosting',
        ],
    ];
    if ($clientId <= 0) return $base;
    try {
        $map = ecloudhosting_accounts_map();
        $accounts = ecloudhosting_owned_services($clientId, $map);
        $selectedId = (string) ($_POST['service_id'] ?? $_GET['service_id'] ?? '');
        if (!isset($accounts[$selectedId])) $selectedId = (string) array_key_first($accounts);
        $base['vars']['ecAccounts'] = array_values(array_map(
            static fn($a) => ['service_id' => $a['service_id'], 'domain' => $a['domain']], $accounts
        ));
        $base['vars']['ecToken'] = ecloudhosting_csrf();
        if (!$selectedId || !isset($accounts[$selectedId])) return $base;
        $account = $accounts[$selectedId];
        $base['vars']['ecSelected'] = ['service_id' => $account['service_id'], 'domain' => $account['domain']];
        $hosts = array_values(array_filter(array_map('trim', explode(',', (string) getenv('ECLOUD_CPANEL_ALLOWED_HOSTS')))));
        $api = new ECloudCpanelApi($account['host'], $account['username'], $account['token'], $hosts);

        $canMail = getenv('ECLOUD_CPANEL_ENABLE_MAIL_WRITES') === '1';
        $base['vars']['ecCanMail'] = $canMail;
        $dnsReady = getenv('ECLOUD_CPANEL_ENABLE_DNS_WRITES') === '1' && $account['dns_edit'];
        $canDns = $dnsReady && ecloudhosting_dns_authority($api, $account['domain'], $account['nameservers']);
        $base['vars']['ecCanDns'] = $canDns;

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['ecloud_action'])) {
            try {
                $base['vars']['ecMessage'] = ecloudhosting_post($api, $account, $_POST, $canDns);
            } catch (Throwable $e) {
                // Never include API responses, SQL, passwords or authentication headers.
                $base['vars']['ecError'] = 'No se completó la operación. Revise los datos o contacte soporte.';
            }
        }

        $rawMail = $api->call('Email', 'list_pops', ['skip_main' => 1]);
        if (is_array($rawMail)) {
            foreach ($rawMail as $box) {
                if (!is_array($box)) continue;
                $full = strtolower((string) ($box['email'] ?? ''));
                if (!str_ends_with($full, '@' . $account['domain'])) continue;
                $base['vars']['ecMail'][] = [
                    'email' => $full,
                    'local' => substr($full, 0, -strlen('@' . $account['domain'])),
                    'quota' => (string) ($box['diskquota'] ?? $box['humandiskquota'] ?? ''),
                ];
            }
        }
        $dns = ecloudhosting_dns_zone($api, $account['domain']);
        foreach ($dns['records'] as $row) {
            $type = strtoupper($row['type']);
            if (!in_array($type, ['A', 'AAAA', 'CNAME', 'TXT', 'MX', 'NS', 'SOA'], true)) continue;
            $value = implode(' ', $row['values']);
            $base['vars']['ecDns'][] = [
                'name' => $row['name'], 'type' => $type, 'value' => $value,
                'ttl' => $row['ttl'], 'line' => $row['line'],
                'fingerprint' => hash('sha256', json_encode($row)),
                'editable' => $canDns && !ecloudhosting_dns_protected($row['name'], $account['domain'], $type, $value),
            ];
        }
    } catch (Throwable $e) {
        $base['vars']['ecError'] = 'No se pudo conectar con cPanel o validar la cuenta. Contacte soporte.';
    }
    return $base;
}
