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
 * No automatic remote deployments on unpaid, missing or unverifiable invoices.
 * A "pedido" product is intended for WHMCS's first-payment provisioning mode;
 * this check also protects users if the product was set up "on order placed".
 */
function eclouddokploy_order_invoice_paid(int $serviceId, int $ownerId): bool
{
    if ($serviceId <= 0 || $ownerId <= 0) return false;
    $hosting = \WHMCS\Database\Capsule::table('tblhosting')
        ->where('id', $serviceId)->where('userid', $ownerId)
        ->first(['orderid']);
    $orderId = (int) ($hosting->orderid ?? 0);
    if ($orderId <= 0) return false;
    $order = \WHMCS\Database\Capsule::table('tblorders')
        ->where('id', $orderId)->first(['invoiceid']);
    $invoiceId = (int) ($order->invoiceid ?? 0);
    if ($invoiceId <= 0) return false;
    $invoice = \WHMCS\Database\Capsule::table('tblinvoices')
        ->where('id', $invoiceId)->where('userid', $ownerId)
        ->first(['status']);
    return $invoice !== null && (string) $invoice->status === 'Paid';
}

/**
 * Safe, actionable WHMCS error message. Never echo raw HTTP response bodies,
 * exception traces, SQL or credentials to customers/administrators.
 */
function eclouddokploy_safe_project_diagnostic(Throwable $e): string
{
    $detail = $e->getMessage();
    if (preg_match(
        '/^Dokploy (?:request|read) failed: ([a-z]+[.][a-zA-Z]+) [(]HTTP ([0-9]{3})[)][.]$/D',
        $detail, $match
    )) {
        $operation = $match[1];
        $status = (int) $match[2];
        if ($status === 401 || $status === 403) {
            return 'Dokploy rechazó el acceso a ' . $operation . ' (HTTP '
                . $status . '). Revisa los permisos de la API key configurada en WHMCS.';
        }
        if ($status === 404 || $status === 405) {
            return 'Dokploy no reconoce el endpoint ' . $operation . ' (HTTP '
                . $status . '). Verifica la versión y la ruta API de Dokploy.';
        }
        if ($status === 0) {
            return 'WHMCS no pudo completar la conexión HTTPS hacia Dokploy en '
                . $operation . '. Revisa DNS, firewall, TLS y conectividad desde WHMCS.';
        }
        return 'Dokploy devolvió HTTP ' . $status . ' durante '
            . $operation . '. El proyecto podría existir; no se intentó duplicarlo. '
            . 'Consulta los registros de Dokploy para ese endpoint.';
    }
    if (preg_match('/^Dokploy transport failed [(]cURL ([0-9]{1,3})[)][.]$/D', $detail, $match)) {
        return 'La conexión HTTPS con Dokploy falló (cURL ' . $match[1]
            . '). Revisa DNS, TLS, firewall y salida HTTPS desde WHMCS.';
    }
    if (str_contains($detail, 'Project description does not match')) {
        return 'El nombre del proyecto coincide, pero su descripción no corresponde '
            . 'al servicio WHMCS. No se vinculó: requiere revisión administrativa.';
    }
    if (str_contains($detail, 'Duplicate project names')) {
        return 'Dokploy devolvió más de un proyecto con el mismo identificador de servicio. '
            . 'No se vinculó ninguno para evitar confusión de cuentas.';
    }
    if (str_contains($detail, 'Invalid Dokploy project ID')) {
        return 'Dokploy devolvió un ID de proyecto con formato incompatible. '
            . 'No se modificó la vinculación del servicio.';
    }
    if (str_contains($detail, 'project.search returned an incomplete or unknown result')) {
        return 'Dokploy devolvió una estructura inesperada en project.search. '
            . 'No se creó ni vinculó otro proyecto. Comprueba la compatibilidad de la API.';
    }
    if (str_contains($detail, 'Dokploy response exceeded size limit')) {
        return 'Dokploy devolvió demasiados datos para la consulta. '
            . 'No se crearon recursos nuevos. Revisa el endpoint que respondió.';
    }
    if (str_contains($detail, 'Unexpected Dokploy response')
        || str_contains($detail, 'Syntax error')
        || str_contains($detail, 'JSON')) {
        return 'Dokploy respondió con JSON inválido o inesperado. '
            . 'No se creó otro proyecto. Comprueba la API del servidor.';
    }
    // Fail closed for all other exception classes; never include
    // potentially sensitive SQL/HTTP exception messages.
    return 'La verificación del proyecto no pudo completarse. '
        . 'No se intentará duplicar recursos; revisa la conexión y los registros de WHMCS.';
}

/**
 * After the first PAID invoice create the real Dokploy project, even when
 * the buyer hasn't provided their GitHub repository. This is what the WHMCS
 * "Create Account" green-success should represent in guided mode.
 */
function eclouddokploy_order_prepare_project(array $params, array $route): string
{
    $serviceId = (int) ($params['serviceid'] ?? 0);
    $owner = (int) ($params['userid'] ?? 0);
    if (!eclouddokploy_order_invoice_paid($serviceId, $owner)) {
        // Don't report green success for a reservation when the customer
        // expects a project; this is an actionable WHMCS module error.
        return 'No se creó el proyecto Dokploy: falta confirmar la primera factura pagada de este servicio.';
    }
    $db = \WHMCS\Database\Capsule::connection();
    $lock = 'ecloud-dokploy-' . $serviceId;
    $acquired = $db->selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lock]);
    if (!$acquired || (int) $acquired->acquired !== 1) {
        return 'Este servicio ya está siendo preparado en Dokploy.';
    }
    try {
        if (!eclouddokploy_server_has_capacity((int) $route['whmcs_server_id'], $serviceId)) {
            return 'El VPS asignado no tiene capacidad disponible para crear el proyecto.';
        }
        $api = new ECloudDokployClient($route['url'], $route['api_key']);
        return eclouddokploy_prepare_private_project_locked(
            $api, $serviceId, $owner, $route['fingerprint']
        );
    } catch (\Throwable $e) {
        // Ambiguous API write: the persisted creating_* state prevents
        // duplicate projects when WHMCS retries this module command.
        return eclouddokploy_safe_project_diagnostic($e);
    } finally {
        $db->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
    }
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
    if (!eclouddokploy_order_invoice_paid($id, $owner)) {
        // The buyer may still use the guided installer after their service is
        // genuinely active. A reserved slot alone is not a paid deployment.
        return 'success';
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
            if (!in_array((string) $row->state, ['awaiting_setup', 'new', 'environment_ready'], true)) {
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
