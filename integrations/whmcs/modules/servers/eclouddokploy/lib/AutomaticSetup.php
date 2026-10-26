<?php
declare(strict_types=1);

/**
 * Zero-SSH configuration for new WHMCS App Ecloud subscriptions.
 *
 * Hostname and Dokploy API token are configured once in the normal WHMCS
 * Servers page. Each product chooses Guided (default) or Legacy mode.
 * Guided mode auto-creates the service reservation on WHMCS CreateAccount
 * and from the client area for existing Active pilot accounts.
 *
 * Password seed is generated ONCE and stored encrypted with WHMCS's own
 * Laravel encryption service. The seed is never echoed, exposed to templates
 * or written in plaintext to a file or Git repository.
 */

function eclouddokploy_auto_product_mode(array $params): string
{
    $selected = strtolower(trim((string) ($params['configoption6'] ?? 'guiado')));
    if ($selected === '') return 'guiado';
    return in_array($selected, ['guiado', 'pedido', 'legado'], true) ? $selected : 'desconocido';
}

function eclouddokploy_auto_enabled(array $params): bool
{
    return in_array(eclouddokploy_auto_product_mode($params), ['guiado', 'pedido'], true);
}

function eclouddokploy_auto_seed_valid(string $secret): bool
{
    return preg_match('/^[a-f0-9]{64}$/Di', $secret) === 1;
}

/**
 * Historic deployments that used an administrator-managed seed continue to
 * use that SAME seed. Never silently overwrite it after installing an update.
 */
function eclouddokploy_auto_seed(): string
{
    $legacy = trim((string) getenv('ECLOUD_DOKPLOY_PASSWORD_SEED'));
    if ($legacy !== '') {
        if (!eclouddokploy_auto_seed_valid($legacy)) {
            throw new RuntimeException('Invalid existing password seed. No resources were created.');
        }
        return strtolower($legacy);
    }

    // Laravel Facades dispatch encryptString/decryptString through __callStatic,
    // so method_exists() is not a reliable capability check.
    if (!class_exists(\Illuminate\Support\Facades\Crypt::class)) {
        throw new RuntimeException('WHMCS encryption service is unavailable.');
    }

    $db = \WHMCS\Database\Capsule::connection();
    $lock = 'ec-dokploy-seed-v1';
    $taken = $db->selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lock]);
    if (!$taken || (int) $taken->acquired !== 1) {
        throw new RuntimeException('Password seed setup is busy.');
    }
    try {
        $schema = \WHMCS\Database\Capsule::schema();
        if (!$schema->hasTable('mod_ecloud_dokploy_runtime')) {
            $schema->create('mod_ecloud_dokploy_runtime', static function ($table) {
                $table->unsignedInteger('id')->primary();
                $table->text('encrypted_seed');
                $table->timestamps();
            });
        }
        $table = \WHMCS\Database\Capsule::table('mod_ecloud_dokploy_runtime');
        $row = $table->where('id', 1)->first(['encrypted_seed']);
        if ($row) {
            $decoded = \Illuminate\Support\Facades\Crypt::decryptString(
                (string) $row->encrypted_seed
            );
            if (!is_string($decoded) || !eclouddokploy_auto_seed_valid($decoded)) {
                throw new RuntimeException('The stored password seed is unavailable.');
            }
            return $decoded;
        }
        $newSeed = bin2hex(random_bytes(32));
        $cipher = \Illuminate\Support\Facades\Crypt::encryptString($newSeed);
        if (!is_string($cipher) || $cipher === '' || str_contains($cipher, $newSeed)) {
            throw new RuntimeException('Could not protect the module encryption seed.');
        }
        $table->insert([
            'id' => 1,
            'encrypted_seed' => $cipher,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $newSeed;
    } finally {
        $db->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
    }
}

/**
 * Used from the existing customer's Lagom page only if paid and active.
 * This does not deploy an application or expose new unbilled infrastructure.
 */
function eclouddokploy_auto_reserve_existing(
    array $params, int $currentOwner, string $domain
): bool {
    if (!eclouddokploy_auto_enabled($params)) return false;
    $serviceId = (int) ($params['serviceid'] ?? 0);
    $owner = (int) ($params['userid'] ?? 0);
    if ($serviceId < 1 || $owner < 1 || $owner !== $currentOwner
        || !eclouddokploy_valid_domain($domain)) return false;

    $hosting = \WHMCS\Database\Capsule::table('tblhosting')
        ->where('id', $serviceId)->where('userid', $currentOwner)
        ->first(['packageid', 'domainstatus', 'domain', 'server']);
    if (!$hosting || (string) $hosting->domainstatus !== 'Active'
        || (int) $hosting->packageid !== (int) ($params['pid'] ?? 0)
        || (int) $hosting->server !== (int) ($params['serverid'] ?? 0)
        || strtolower(rtrim((string) $hosting->domain, '.')) !== $domain) {
        return false;
    }
    $route = eclouddokploy_resolve_route($params);
    return eclouddokploy_setup_reserve(
        $params, $route, (string) ($params['configoption5'] ?? 'none')
    ) === 'success';
}
