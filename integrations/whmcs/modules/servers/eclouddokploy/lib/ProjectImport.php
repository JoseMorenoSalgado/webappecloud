<?php
declare(strict_types=1);

/**
 * Dokploy-first UX: check -> confirm import if found -> create if absent.
 *
 * The confirmation is a separate native WHMCS admin module command, not
 * an automatic adoption or an embedded custom HTML POST bypassing WHMCS CSRF.
 */
function eclouddokploy_import_question(): string
{
    return 'El proyecto ya existe en Dokploy. ¿Deseas importarlo a este servicio? '
        . 'Selecciona «Sí, importar proyecto existente» en Comandos del módulo. '
        . 'No se creará un segundo proyecto.';
}

function eclouddokploy_admin_import_pending_clear(int $serviceId): void
{
    if (isset($_SESSION['ec_dokploy_import_pending'][$serviceId])) {
        unset($_SESSION['ec_dokploy_import_pending'][$serviceId]);
    }
}

function eclouddokploy_admin_import_pending(array $params, array $route): ?array
{
    $id = (int) ($params['serviceid'] ?? 0);
    $owner = (int) ($params['userid'] ?? 0);
    $pending = $_SESSION['ec_dokploy_import_pending'][$id] ?? null;
    if (!is_array($pending)
        || (int) ($pending['owner_id'] ?? 0) !== $owner
        || (int) ($pending['service_id'] ?? 0) !== $id
        || !is_string($pending['fingerprint'] ?? null)
        || !hash_equals((string) $route['fingerprint'], $pending['fingerprint'])
        || !is_string($pending['project_id'] ?? null)
        || preg_match('/^[A-Za-z0-9_-]{3,128}$/D', $pending['project_id']) !== 1
        || (int) ($pending['expires_at'] ?? 0) < time()) {
        eclouddokploy_admin_import_pending_clear($id);
        return null;
    }
    return $pending;
}

/**
 * May only run under ecloud-dokploy-{serviceId} MySQL lock after an explicit
 * WHMCS admin confirmation. Recheck everything remotely and locally.
 */
function eclouddokploy_import_existing_locked(
    ECloudDokployClient $api,
    int $serviceId,
    int $clientId,
    string $routeFingerprint,
    string $confirmedProjectId
): string {
    $row = \WHMCS\Database\Capsule::table('mod_ecloud_dokploy_services')
        ->where('service_id', $serviceId)->first();
    if (!$row || (int) ($row->owner_client_id ?? 0) !== $clientId
        || !is_string($row->route_fingerprint ?? null)
        || !hash_equals($routeFingerprint, $row->route_fingerprint)) {
        return 'No se pudo verificar el propietario y el VPS original del servicio.';
    }
    $eligibleStates = [
        'new', 'awaiting_setup', 'creating_project',
        'project_created', 'creating_environment', 'environment_ready',
    ];
    if (!in_array((string) $row->state, $eligibleStates, true)) {
        return 'La cuenta tiene una aplicación o instalación en curso. '
            . 'No se cambiará su proyecto automáticamente.';
    }
    // Never relink a service already managing an app, database or domain.
    foreach (['application_id', 'mariadb_id', 'domain_id'] as $key) {
        if (trim((string) ($row->$key ?? '')) !== '') {
            return 'Este servicio ya tiene recursos provisionados. '
                . 'La importación automática no está permitida.';
        }
    }

    $remoteProjectId = eclouddokploy_lookup_service_project(
        $api, $clientId, $serviceId
    );
    if ($remoteProjectId === null) {
        return 'El proyecto detectado ya no aparece en Dokploy. '
            . 'Pulsa «Comprobar / crear proyecto» para revisar su estado.';
    }
    if (!hash_equals($confirmedProjectId, $remoteProjectId)) {
        return 'El identificador remoto del proyecto cambió desde la confirmación. '
            . 'Vuelve a comprobarlo antes de importar.';
    }
    $envId = eclouddokploy_find_production_environment(
        $api->get('environment.byProjectId', ['projectId' => $remoteProjectId]),
        $remoteProjectId
    );
    if ($envId === null) {
        return 'El proyecto existe, pero no tiene un entorno production verificable. '
            . 'No se importó ni se crearon entornos nuevos.';
    }

    $oldProjectId = (string) ($row->project_id ?? '');
    $oldEnvironmentId = (string) ($row->environment_id ?? '');
    if ($oldProjectId !== '' && !hash_equals($oldProjectId, $remoteProjectId)) {
        // If a previously bound project was merely renamed, its ID still
        // exists and must NEVER be silently replaced by a name collision.
        if (!eclouddokploy_deleted_project_not_found($api, $oldProjectId)) {
            return 'El proyecto anterior todavía existe en Dokploy con otro nombre. '
                . 'No se vinculará uno distinto a este cliente.';
        }
    } elseif ($oldEnvironmentId !== '' && !hash_equals($oldEnvironmentId, $envId)) {
        // The project is the same: changing its production ID might indicate
        // resource loss or ownership drift. Require separate reconciliation.
        return 'El entorno production cambió en el proyecto ya vinculado. '
            . 'No se sobrescribió su identificador.';
    }

    eclouddokploy_set_state($serviceId, 'environment_ready', [
        'project_id' => $remoteProjectId,
        'environment_id' => $envId,
    ]);
    return 'success';
}

function eclouddokploy_admin_import_existing(array $params): string
{
    $service = eclouddokploy_admin_service($params, true);
    if ($service === null) {
        return 'La importación requiere una sesión administrativa y un servicio activo.';
    }
    $id = $service['service_id'];
    $owner = $service['owner_id'];
    if (!eclouddokploy_order_invoice_paid($id, $owner)) {
        return 'La primera factura no está pagada. No se importó ningún proyecto.';
    }
    try {
        $route = eclouddokploy_resolve_route($params);
        $pending = eclouddokploy_admin_import_pending($params, $route);
        if ($pending === null) {
            return 'Primero ejecuta «Comprobar / crear proyecto» para detectar '
                . 'el proyecto existente y confirmar cuál deseas importar.';
        }
        $db = \WHMCS\Database\Capsule::connection();
        $lock = 'ecloud-dokploy-' . $id;
        $got = $db->selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lock]);
        if (!$got || (int) $got->acquired !== 1) {
            return 'Hay otra operación en curso para este servicio. No se importó nada.';
        }
        try {
            if (!eclouddokploy_server_has_capacity(
                (int) $route['whmcs_server_id'], $id
            )) {
                return 'El VPS asignado alcanzó su capacidad de cuentas.';
            }
            $api = new ECloudDokployClient($route['url'], $route['api_key']);
            $result = eclouddokploy_import_existing_locked(
                $api, $id, $owner, $route['fingerprint'], $pending['project_id']
            );
            if ($result === 'success') {
                eclouddokploy_admin_import_pending_clear($id);
                eclouddokploy_admin_audit('project_imported', $params);
                $_SESSION['ec_dokploy_admin_notice'][$id] =
                    'Proyecto existente importado y vinculado a este servicio. '
                    . 'Se conserva el mismo entorno production y no se creó otro.';
            }
            return $result;
        } finally {
            $db->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
        }
    } catch (Throwable $e) {
        eclouddokploy_log_project_diagnostic($e, $id, $owner);
        return eclouddokploy_safe_project_diagnostic($e);
    }
}
