<?php
declare(strict_types=1);

/**
 * Administrative recovery for a project intentionally deleted from Dokploy.
 *
 * This is NEVER called by automatic paid-order or client-side provisioning.
 * A support administrator must run WHMCS's "Crear / verificar proyecto".
 *
 * Only an EMPTY project, with a provably deleted remote projectId (404 from
 * Dokploy project.one and negative exact project.search), can be recreated.
 * We do not delete resources, alter WHMCS billing or overwrite a live app.
 */

function eclouddokploy_deleted_project_recovery_eligible(object $row): bool
{
    // A project with applications, databases, domains or uncertain deployments
    // must be reconciled manually to avoid accidental data loss.
    if (!in_array((string) ($row->state ?? ''), ['project_created', 'environment_ready'], true)) {
        return false;
    }
    if (!is_string($row->project_id ?? null)
        || preg_match('/^[A-Za-z0-9_-]{3,128}$/D', (string) $row->project_id) !== 1) {
        return false;
    }
    foreach (['application_id', 'mariadb_id', 'domain_id'] as $resource) {
        if (trim((string) ($row->$resource ?? '')) !== '') return false;
    }
    return true;
}

/**
 * @return bool Whether project.one conclusively returned 404 for the old ID.
 * A transient 500, permission issue, time-out or malformed response is NOT
 * proof of deletion and must abort without mutating WHMCS.
 */
function eclouddokploy_deleted_project_not_found(
    ECloudDokployClient $api, string $oldProjectId
): bool {
    try {
        // An unexpected 200 response is evidence that the project still exists
        // (even if somebody renamed it); it must never be recreated.
        $api->get('project.one', ['projectId' => $oldProjectId]);
        return false;
    } catch (RuntimeException $e) {
        if (preg_match(
            '/^Dokploy read failed: project[.]one [(]HTTP 404[)][.]$/D',
            $e->getMessage()
        ) === 1) return true;
        throw $e;
    }
}

/**
 * Called ONLY from the admin's manual Create/Verify command after a stale
 * binding was encountered. Under the same service MySQL advisory lock,
 * perform two independent remote read-only checks, then recreate if safe.
 */
function eclouddokploy_admin_recreate_deleted_project(
    array $params, array $route
): string {
    $service = eclouddokploy_admin_service($params, true);
    if ($service === null) {
        return 'Solo soporte puede recuperar proyectos de servicios activos y válidos.';
    }
    $id = (int) $service['service_id'];
    $owner = (int) $service['owner_id'];
    if (!eclouddokploy_order_invoice_paid($id, $owner)) {
        return 'No se recuperó el proyecto: la primera factura no está pagada.';
    }
    $db = \WHMCS\Database\Capsule::connection();
    $lock = 'ecloud-dokploy-' . $id;
    $got = $db->selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lock]);
    if (!$got || (int) $got->acquired !== 1) {
        return 'El proyecto ya está siendo revisado. No se intentó recrearlo.';
    }
    try {
        $table = \WHMCS\Database\Capsule::table('mod_ecloud_dokploy_services');
        $row = $table->where('service_id', $id)->first();
        if (!$row || (int) $row->owner_client_id !== $owner
            || !is_string($row->route_fingerprint)
            || !hash_equals($row->route_fingerprint, $route['fingerprint'])
            || (string) $row->db_mode !== (string) ($params['configoption5'] ?? 'none')
            || !eclouddokploy_deleted_project_recovery_eligible($row)) {
            return 'La recuperación automática solo se permite para proyectos vacíos '
                . 'y servicios con propietario, VPS y estado confirmados. '
                . 'No se cambió la vinculación.';
        }
        if (!eclouddokploy_server_has_capacity(
            (int) $route['whmcs_server_id'], $id
        )) {
            return 'El servidor Dokploy asignado alcanzó su capacidad de cuentas.';
        }
        $api = new ECloudDokployClient($route['url'], $route['api_key']);

        // Check name first. This guarantees an existing same-name project
        // isn't accidentally replaced or duplicated.
        $found = eclouddokploy_lookup_service_project($api, $owner, $id);
        if ($found !== null) {
            return 'Dokploy todavía contiene el proyecto de este servicio '
                . '(ID ' . $found . '). No se reinició la vinculación.';
        }

        // Search cannot distinguish deletion from a rename. Checking the
        // original projectId makes the distinction, and prevents adopting
        // unrelated resources or falsely treating a 403/500 as deletion.
        if (!eclouddokploy_deleted_project_not_found($api, (string) $row->project_id)) {
            return 'El proyecto anterior todavía existe con otro nombre. '
                . 'No se recreó para evitar duplicados.';
        }

        // Both definitive read-only checks passed and WHMCS has no remotely
        // provisioned child services. Reset ONLY stale project/environment
        // references, not payment, product, domain, email, DB mode or secrets.
        // If creation is interrupted, state creating_project blocks duplicates.
        eclouddokploy_set_state($id, 'awaiting_setup', [
            'project_id' => null, 'environment_id' => null,
        ]);
        $result = eclouddokploy_prepare_private_project_locked(
            $api, $id, $owner, $route['fingerprint']
        );
        if ($result === 'success') {
            eclouddokploy_admin_audit('project_recreated', $params);
        }
        return $result;
    } catch (Throwable $e) {
        eclouddokploy_log_project_diagnostic($e, $id, $owner);
        return eclouddokploy_safe_project_diagnostic($e);
    } finally {
        $db->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
    }
}
