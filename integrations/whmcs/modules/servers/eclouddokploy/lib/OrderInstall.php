<?php
declare(strict_types=1);

/**
 * Optional zero-follow-up order: the customer supplied a public GitHub
 * repository as a WHMCS product Custom Field at checkout.
 *
 * Only enabled for products with module option "pedido"; the default is
 * "guiado", which reserves the subscription without an external mutation.
 *
 * WHMCS product automation setting must run after first invoice payment.
 */

function eclouddokploy_order_source(array $params): ?array
{
    $fields = $params['customfields'] ?? [];
    if (!is_array($fields)) return null;
    $git = '';
    foreach (['Repositorio GitHub', 'Repositorio de GitHub', 'GitHub Repository'] as $label) {
        if (isset($fields[$label]) && is_string($fields[$label]) && trim($fields[$label]) !== '') {
            $git = trim($fields[$label]);
            break;
        }
    }
    if ($git === '') return null;
    $branch = 'main';
    foreach (['Rama GitHub', 'Git Branch'] as $label) {
        if (isset($fields[$label]) && is_string($fields[$label]) && trim($fields[$label]) !== '') {
            $branch = trim($fields[$label]);
            break;
        }
    }
    // The product is "hosting de aplicaciones"; default Node/Next.js build
    // without Dockerfile, unless the customer selects a documented preset.
    $preset = 'auto';
    foreach (['Tipo de proyecto', 'Project Type'] as $label) {
        if (isset($fields[$label]) && is_string($fields[$label]) && trim($fields[$label]) !== '') {
            $preset = strtolower(trim($fields[$label]));
            break;
        }
    }
    return [
        'ec_setup_git' => $git,
        'ec_setup_branch' => $branch,
        'ec_setup_root' => '/',
        'ec_setup_preset' => $preset,
        'ec_setup_port' => '3000',
        'ec_setup_env' => '',
    ];
}

/**
 * Runs inside CreateAccount after WHMCS reserves this service.
 * The external calls only happen for an explicitly opted-in paid-order
 * product, with public GitHub URL provided and validated.
 */
function eclouddokploy_order_auto_install(array $params, array $route): string
{
    if (eclouddokploy_auto_product_mode($params) !== 'pedido') {
        return 'success';
    }
    $source = eclouddokploy_order_source($params);
    if ($source === null) {
        // No repository given at checkout; customer may use guided install.
        return 'success';
    }
    $id = (int) ($params['serviceid'] ?? 0);
    $owner = (int) ($params['userid'] ?? 0);
    $domain = strtolower(rtrim(trim((string) ($params['domain'] ?? '')), '.'));
    $dbMode = (string) ($params['configoption5'] ?? 'none');
    if ($id < 1 || $owner < 1 || !eclouddokploy_valid_domain($domain)) {
        return 'El sitio necesita un dominio válido antes del despliegue automático.';
    }

    // Reject bad repository before attempting any external writes.
    try {
        $setup = eclouddokploy_setup_prepare($source);
        $seed = eclouddokploy_auto_seed();
        $db = \WHMCS\Database\Capsule::connection();
        $lock = 'ecloud-dokploy-' . $id;
        $got = $db->selectOne('SELECT GET_LOCK(?, 8) AS acquired', [$lock]);
        if (!$got || (int) $got->acquired !== 1) {
            return 'La instalación automática ya está en ejecución.';
        }
        try {
            eclouddokploy_create_table();
            $row = \WHMCS\Database\Capsule::table('mod_ecloud_dokploy_services')
                ->where('service_id', $id)->first();
            if (!$row || (int) $row->owner_client_id !== $owner
                || (string) $row->db_mode !== $dbMode
                || !is_string($row->route_fingerprint)
                || !hash_equals($route['fingerprint'], $row->route_fingerprint)) {
                return 'La cuenta no corresponde al servidor asignado por WHMCS.';
            }
            if (!eclouddokploy_server_has_capacity($route['whmcs_server_id'], $id)) {
                return 'No hay plazas disponibles en el VPS asignado.';
            }
            if ((string) $row->state === 'deployment_requested') return 'success';
            if (!in_array((string) $row->state, ['awaiting_setup', 'new'], true)) {
                return 'La instalación ya empezó. Se requiere verificar el estado antes de reintentar.';
            }
            if (!\WHMCS\Database\Capsule::schema()->hasTable('mod_ecloud_dokploy_setup')) {
                eclouddokploy_setup_create_table();
            }
            $existing = eclouddokploy_setup_saved($id, $seed);
            if ($existing === null) {
                eclouddokploy_setup_persist($id, $setup, $seed);
            } else {
                $setup = $existing; // preserve immutable original settings
            }
            if ((string) $row->state === 'awaiting_setup') {
                eclouddokploy_set_state($id, 'new');
            }
            $api = new ECloudDokployClient($route['url'], $route['api_key']);
            $result = eclouddokploy_create_locked(
                $api, $id, $owner,
                $setup['git_url'], $setup['branch'],
                $setup['port'], $domain, $seed, $dbMode,
                $route['remote_server_id'], $route['fingerprint'], $setup
            );
            $after = \WHMCS\Database\Capsule::table('mod_ecloud_dokploy_services')
                ->where('service_id', $id)->first(['state']);
            return $after && (string) $after->state === 'deployment_requested'
                && str_starts_with($result, 'Despliegue solicitado.')
                ? 'success'
                : $result;
        } finally {
            $db->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
        }
    } catch (\InvalidArgumentException $e) {
        return 'Repositorio o parámetros del pedido incorrectos; revisa GitHub, rama y tipo de proyecto.';
    } catch (\Throwable $e) {
        return 'La instalación automática necesita revisión. No se reintentó ninguna creación remota ambigua.';
    }
}
