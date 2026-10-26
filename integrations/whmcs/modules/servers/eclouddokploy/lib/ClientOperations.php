<?php
declare(strict_types=1);

/**
 * Customer controls for a WHMCS Dokploy service. Never expose Dokploy tokens
 * and never accept an application ID from the browser.
 *
 * This file is included by eclouddokploy.php after the WHMCS guard.
 */

function eclouddokploy_csrf(int $serviceId): string
{
    if ($serviceId <= 0) {
        throw new InvalidArgumentException('Invalid service.');
    }
    if (!isset($_SESSION['ecloud_dokploy_csrf'])
        || !is_array($_SESSION['ecloud_dokploy_csrf'])) {
        $_SESSION['ecloud_dokploy_csrf'] = [];
    }
    if (!isset($_SESSION['ecloud_dokploy_csrf'][$serviceId])
        || !is_string($_SESSION['ecloud_dokploy_csrf'][$serviceId])
        || strlen($_SESSION['ecloud_dokploy_csrf'][$serviceId]) !== 64) {
        $_SESSION['ecloud_dokploy_csrf'][$serviceId] = bin2hex(random_bytes(32));
    }
    return $_SESSION['ecloud_dokploy_csrf'][$serviceId];
}

/** Prefer conservative, human-readable statuses over raw Dokploy payloads. */
function eclouddokploy_application_status($status): array
{
    $states = [
        'idle' => ['En espera', 'Dokploy no está ejecutando una compilación en este momento.'],
        'running' => ['Compilando o desplegando', 'Dokploy está procesando una operación de despliegue.'],
        'done' => ['Última operación finalizada', 'Dokploy terminó la operación; esto no confirma la salud del sitio web.'],
        'error' => ['Error de despliegue', 'Dokploy registró un error; revisa el servicio con soporte.'],
    ];
    return is_string($status) && isset($states[$status])
        ? $states[$status]
        : ['Estado no disponible', 'No se pudo interpretar el estado de Dokploy.'];
}

function eclouddokploy_deployment_rows($payload): array
{
    // Dokploy versions may return a direct array or an object with deployments.
    if (!is_array($payload)) return [];
    $items = array_is_list($payload) ? $payload : ($payload['deployments'] ?? []);
    if (!is_array($items)) return [];

    $statuses = [
        'running' => 'En ejecución',
        'done' => 'Finalizado',
        'success' => 'Correcto',
        'error' => 'Error',
        'failed' => 'Fallido',
        'pending' => 'Pendiente',
        'queued' => 'En cola',
        'cancelled' => 'Cancelado',
        'canceled' => 'Cancelado',
    ];
    $rows = [];
    foreach (array_slice($items, 0, 5) as $item) {
        if (!is_array($item)) continue;
        $rawStatus = strtolower((string) ($item['status'] ?? ''));
        $status = $statuses[$rawStatus] ?? 'Sin confirmar';
        $date = null;
        $rawDate = $item['createdAt'] ?? $item['startedAt'] ?? null;
        if (is_string($rawDate) && preg_match('/^\d{4}-\d\d-\d\dT/', $rawDate)) {
            $stamp = strtotime($rawDate);
            if ($stamp !== false) $date = gmdate('Y-m-d H:i', $stamp) . ' UTC';
        }
        $rows[] = [
            'status' => $status,
            'date' => $date ?? 'Fecha no disponible',
        ];
    }
    return $rows;
}

/**
 * Retrieve only fields that are safe to show customers. Not a site healthcheck:
 * applicationStatus is Dokploy's job state, not proof the URL responds with 200.
 */
function eclouddokploy_live_overview(ECloudDokployClient $api, string $applicationId, string $environmentId): array
{
    if (!preg_match('/^[a-zA-Z0-9_-]{3,128}$/D', $applicationId)) {
        throw new RuntimeException('Invalid remote application ID.');
    }

    $application = $api->get('application.one', ['applicationId' => $applicationId]);
    if (!eclouddokploy_app_environment_matches($application, $applicationId, $environmentId)) {
        throw new RuntimeException('Remote application environment mismatch.');
    }
    $state = eclouddokploy_application_status($application['applicationStatus'] ?? null);
    // Deployment history is optional; errors should not hide the job state.
    $history = [];
    try {
        $history = eclouddokploy_deployment_rows(
            $api->get('deployment.all', ['applicationId' => $applicationId])
        );
    } catch (Throwable $e) {
        // Do not surface raw API errors or secrets to the client.
    }
    return ['state' => $state[0], 'description' => $state[1], 'history' => $history];
}

/** Idempotent cooldown storage, independent of provisioning state. */
function eclouddokploy_create_actions_table(): void
{
    if (\WHMCS\Database\Capsule::schema()->hasTable('mod_ecloud_dokploy_actions')) return;
    \WHMCS\Database\Capsule::schema()->create('mod_ecloud_dokploy_actions', function ($table) {
        $table->unsignedInteger('service_id')->primary();
        $table->dateTime('last_redeploy_at')->nullable();
    });
}

/**
 * WHMCS invokes this only through its declared module ClientAreaAllowedFunctions.
 * An attacker cannot choose which Dokploy application is redeployed: the ID is
 * loaded by the active service ID after checking owner, product and domain.
 */
function eclouddokploy_redeploy_request(array $params): string
{
    if (getenv('ECLOUD_DOKPLOY_ENABLE_CLIENT_REDEPLOY') !== '1') {
        return 'La función de redespliegue todavía no está habilitada.';
    }
    $serviceId = (int) ($params['serviceid'] ?? 0);
    $ownerId = (int) ($params['userid'] ?? 0);
    $clientId = (int) ($_SESSION['uid'] ?? 0);
    if ($serviceId <= 0 || $ownerId <= 0 || $ownerId !== $clientId ||
        ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' ||
        (int) ($_POST['id'] ?? 0) !== $serviceId ||
        (string) ($_POST['modop'] ?? '') !== 'custom' ||
        (string) ($_POST['a'] ?? '') !== 'Redeploy' ||
        (string) ($_POST['ec_confirm'] ?? '') !== 'yes') {
        return 'Solicitud de redespliegue no autorizada.';
    }
    $token = (string) ($_POST['ec_redeploy_token'] ?? '');
    if (strlen($token) !== 64 || !hash_equals(eclouddokploy_csrf($serviceId), $token)) {
        return 'El formulario expiró; actualiza la ficha del servicio.';
    }

    try {
        $db = \WHMCS\Database\Capsule::connection();
        $service = $db->table('tblhosting')
            ->where('id', $serviceId)->where('userid', $clientId)
            ->first(['id', 'packageid', 'domain', 'domainstatus', 'server']);
        if (!$service || (int) $service->packageid !== (int) ($params['pid'] ?? 0)
            || (string) $service->domainstatus !== 'Active'
            || (int) $service->server !== (int) ($params['serverid'] ?? 0)
            || strtolower(rtrim((string) $service->domain, '.')) !==
               strtolower(rtrim((string) ($params['domain'] ?? ''), '.'))) {
            return 'El servicio no se encuentra activo o no está autorizado.';
        }

        $route = eclouddokploy_resolve_route($params);
        $lock = 'ec-dokploy-client-deploy-' . $serviceId;
        $got = $db->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lock]);
        if (!$got || (int) $got->acquired !== 1) {
            return 'Este servicio ya tiene una operación en curso.';
        }
        try {
            eclouddokploy_create_actions_table();
            $record = $db->table('mod_ecloud_dokploy_services')->where('service_id', $serviceId)
                ->first(['state', 'application_id', 'route_fingerprint',
                    'owner_client_id', 'project_id', 'environment_id']);
            if (!$record || !eclouddokploy_route_matches($record->route_fingerprint, $route)
                || !eclouddokploy_service_isolation_valid(
                    $record->owner_client_id, $record->project_id,
                    $record->environment_id, $clientId
                ) || (string) $record->state !== 'deployment_requested'
                || !is_string($record->application_id)
                || !preg_match('/^[a-zA-Z0-9_-]{3,128}$/D', $record->application_id)) {
                return 'Todavía no existe una aplicación habilitada para redespliegue.';
            }

            $actions = $db->table('mod_ecloud_dokploy_actions');
            $last = $actions->where('service_id', $serviceId)->first(['last_redeploy_at']);
            if ($last && !empty($last->last_redeploy_at) &&
                strtotime((string) $last->last_redeploy_at) > time() - 900) {
                return 'Por seguridad, espera 15 minutos entre redespliegues.';
            }
            // Fail closed if Dokploy cannot confirm this exact application's state.
            // Never queue another build while Dokploy says an operation is running.
            $api = new ECloudDokployClient($route['url'], $route['api_key']);
            $remote = $api->get('application.one', [
                'applicationId' => $record->application_id,
            ]);
            if (!eclouddokploy_app_environment_matches(
                $remote, $record->application_id, $record->environment_id
            )) {
                return 'No se pudo validar la aplicación remota.';
            }
            if (($remote['applicationStatus'] ?? null) === 'running') {
                return 'Dokploy ya está ejecutando una operación para esta aplicación.';
            }
            // Reserve the request before calling Dokploy to prevent repetition
            // if the API accepted it but the network response was interrupted.
            if ($last) {
                $actions->where('service_id', $serviceId)->update([
                    'last_redeploy_at' => gmdate('Y-m-d H:i:s'),
                ]);
            } else {
                $actions->insert([
                    'service_id' => $serviceId,
                    'last_redeploy_at' => gmdate('Y-m-d H:i:s'),
                ]);
            }

            $api->post('application.deploy', ['applicationId' => $record->application_id]);
            if (function_exists('logActivity')) {
                logActivity('ECloud Dokploy: redeploy requested for WHMCS service=' . $serviceId);
            }
            return 'success';
        } finally {
            $db->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
        }
    } catch (Throwable $e) {
        // Do not leak tokens or Dokploy error bodies. Keep cooldown for ambiguous outcomes.
        return 'No se pudo confirmar el redespliegue. Revisa el estado antes de volver a intentarlo.';
    }
}
