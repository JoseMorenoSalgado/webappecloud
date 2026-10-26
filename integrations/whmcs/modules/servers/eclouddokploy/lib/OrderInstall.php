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
/**
 * Stable diagnostic code with no sensitive exception message. Use it in
 * WHMCS Activity Log (customer id, service id, code) for support escalation.
 */
function eclouddokploy_project_error_code(Throwable $e): string
{
    $detail = $e->getMessage();
    if (preg_match(
        '/^Dokploy (?:request|read) failed: ([a-z]+[.][a-zA-Z]+) [(]HTTP ([0-9]{1,3})[)][.]$/D',
        $detail, $matches
    )) {
        $op = strtoupper(str_replace('.', '_', $matches[1]));
        return 'EC-DOK-HTTP-' . $matches[2] . '-' . $op;
    }
    if (preg_match('/^Dokploy transport failed [(]cURL ([0-9]{1,3})[)][.]$/D', $detail, $m)) {
        return 'EC-DOK-CURL-' . $m[1];
    }
    if (str_contains($detail, 'project.search returned an incomplete or unknown result')) {
        return 'EC-DOK-SEARCH-FORMAT';
    }
    if (str_contains($detail, 'Unexpected Dokploy environment listing')) {
        return 'EC-DOK-ENV-FORMAT';
    }
    if (str_contains($detail, 'Environment does not belong to the service project')) {
        return 'EC-DOK-ENV-OWNER';
    }
    if (str_contains($detail, 'Project description does not match')) {
        return 'EC-DOK-PROJECT-DESCRIPTION';
    }
    if (str_contains($detail, 'Duplicate project names')
        || str_contains($detail, 'Ambiguous production environments')) {
        return 'EC-DOK-AMBIGUOUS';
    }
    if (str_contains($detail, 'Invalid Dokploy environment identifier')
        || str_contains($detail, 'Invalid Dokploy project ID')) {
        return 'EC-DOK-INVALID-ID';
    }
    if ($e instanceof \PDOException
        || is_a($e, 'Illuminate\\Database\\QueryException')) {
        return 'EC-DOK-WHMCS-DB';
    }
    if ($e instanceof \JsonException) return 'EC-DOK-JSON';
    if ($e instanceof \TypeError) return 'EC-DOK-TYPE';
    if (str_contains($detail, 'Unexpected Dokploy response')
        || str_contains($detail, 'Dokploy response exceeded size limit')) {
        return 'EC-DOK-RESPONSE';
    }
    if (str_contains($detail, 'Dokploy did not return a projectId')
        || str_contains($detail, 'Dokploy did not return a environmentId')) {
        return 'EC-DOK-MISSING-ID';
    }
    // Intentionally never return arbitrary exception messages or filenames.
    return 'EC-DOK-UNCLASSIFIED';
}

function eclouddokploy_safe_project_diagnostic(Throwable $e): string
{
    $code = eclouddokploy_project_error_code($e);
    $detail = $e->getMessage();
    if (preg_match(
        '/^Dokploy (?:request|read) failed: ([a-z]+[.][a-zA-Z]+) [(]HTTP ([0-9]{1,3})[)][.]$/D',
        $detail, $match
    )) {
        $operation = $match[1];
        $status = (int) $match[2];
        if ($status === 401 || $status === 403) {
            return 'La API de Dokploy rechazó ' . $operation . ' (HTTP ' . $status
                . '). Revisa los permisos de la API key para la organización y el proyecto. '
                . 'Referencia: ' . $code . '.';
        }
        if ($status === 404 || $status === 405) {
            return 'Dokploy no reconoce la operación ' . $operation . ' (HTTP '
                . $status . '). Verifica la versión y configuración de la API. '
                . 'Referencia: ' . $code . '.';
        }
        if ($status >= 500) {
            return 'El servidor Dokploy falló al consultar ' . $operation
                . ' (HTTP ' . $status . '). El proyecto existente NO se ha eliminado '
                . 'ni duplicado. Revisa el registro de Dokploy. Referencia: '
                . $code . '.';
        }
        return 'Dokploy rechazó la operación ' . $operation . ' (HTTP '
            . $status . '). No se han duplicado recursos. Referencia: ' . $code . '.';
    }
    if (preg_match('/^EC-DOK-CURL-([0-9]{1,3})$/D', $code, $m)) {
        $curl = (int) $m[1];
        $reason = match ($curl) {
            6 => 'DNS (nombre del servidor no resuelto)',
            7 => 'conexión TCP o firewall',
            28 => 'tiempo de espera agotado',
            35, 51, 60 => 'certificado TLS o conexión HTTPS',
            default => 'conectividad HTTPS entre WHMCS y Dokploy',
        };
        return 'No se completó la consulta a Dokploy (cURL ' . $curl . '): ' . $reason
            . '. Revisa la conectividad desde el servidor WHMCS. Referencia: '
            . $code . '.';
    }
    $messages = [
        'EC-DOK-SEARCH-FORMAT' => 'Dokploy devolvió un resultado de búsqueda incompleto o incompatible.',
        'EC-DOK-ENV-FORMAT' => 'La API devolvió una lista de entornos con formato inesperado.',
        'EC-DOK-ENV-OWNER' => 'El entorno no corresponde al proyecto asignado a este cliente.',
        'EC-DOK-PROJECT-DESCRIPTION' => 'La descripción del proyecto existente no corresponde al servicio WHMCS.',
        'EC-DOK-AMBIGUOUS' => 'Dokploy devolvió recursos repetidos; hace falta conciliación administrativa.',
        'EC-DOK-INVALID-ID' => 'Dokploy devolvió un identificador de proyecto o entorno inválido.',
        'EC-DOK-WHMCS-DB' => 'Falló la consulta a la base de datos o el esquema del módulo en WHMCS.',
        'EC-DOK-JSON' => 'La API de Dokploy devolvió JSON mal formado.',
        'EC-DOK-TYPE' => 'El complemento recibió un tipo de datos inesperado; comprueba compatibilidad de PHP/WHMCS.',
        'EC-DOK-RESPONSE' => 'La respuesta de la API de Dokploy tiene un formato o tamaño inesperado.',
        'EC-DOK-MISSING-ID' => 'La API de Dokploy no devolvió el identificador obligatorio.',
        'EC-DOK-UNCLASSIFIED' => 'No fue posible clasificar el error; revisa el registro de actividad de WHMCS.',
    ];
    return ($messages[$code] ?? $messages['EC-DOK-UNCLASSIFIED'])
        . ' No se ha intentado crear un proyecto duplicado. Referencia: ' . $code . '.';
}

/** Activity Log event: fixed diagnostic code only, never raw exception text. */
function eclouddokploy_log_project_diagnostic(
    Throwable $error, int $serviceId, int $ownerId
): void {
    if (!function_exists('logActivity') || $serviceId < 1 || $ownerId < 1) return;
    $code = eclouddokploy_project_error_code($error);
    try {
        logActivity(
            'Elearning Cloud Dokploy: fallo de verificación de proyecto. Servicio #'
            . $serviceId . ', referencia ' . $code,
            $ownerId
        );
    } catch (Throwable $ignored) {
        // A logging outage must never affect service state or leak secrets.
    }
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
        // An ambiguous API write must never be repeated blindly.
        // Log only a stable diagnostic code, not the raw exception, API
        // response, SQL statement, URL or any credentials.
        eclouddokploy_log_project_diagnostic($e, $serviceId, $owner);
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
