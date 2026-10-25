<?php
declare(strict_types=1);

/**
 * One Dokploy project and production environment per WHMCS service.
 * Project IDs are persisted before any application/database creation.
 * No destructive automatic cleanup is attempted in ambiguous API states.
 */
function eclouddokploy_project_name(int $clientId, int $serviceId): string
{
    if ($clientId < 1 || $serviceId < 1) {
        throw new InvalidArgumentException('Invalid WHMCS service owner.');
    }
    return 'ec-client-' . $clientId . '-service-' . $serviceId;
}

function eclouddokploy_project_create_payload(int $clientId, int $serviceId): array
{
    return [
        'name' => eclouddokploy_project_name($clientId, $serviceId),
        'description' => 'Elearning Cloud WHMCS service ' . $serviceId,
    ];
}

/**
 * Only accept the one production environment within the newly created project.
 * Never silently adopt an environment from another project or a different
 * environment name.
 */
function eclouddokploy_find_production_environment(array $response, string $projectId): ?string
{
    $items = array_is_list($response)
        ? $response
        : ($response['environments'] ?? null);
    if (!is_array($items)) {
        throw new RuntimeException('Unexpected Dokploy environment listing.');
    }
    $found = [];
    foreach ($items as $item) {
        if (!is_array($item) || ($item['name'] ?? null) !== 'production') continue;
        if (!is_string($item['projectId'] ?? null)
            || !hash_equals($projectId, $item['projectId'])) {
            throw new RuntimeException('Environment does not belong to the service project.');
        }
        $id = $item['environmentId'] ?? null;
        if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{3,128}$/D', $id)) {
            throw new RuntimeException('Invalid Dokploy environment identifier.');
        }
        $found[] = $id;
    }
    if (count($found) > 1) {
        throw new RuntimeException('Ambiguous production environments require manual reconciliation.');
    }
    return $found[0] ?? null;
}

/** An application must be in the specific service's private environment. */
function eclouddokploy_app_environment_matches(array $remote, string $appId, string $envId): bool
{
    return is_string($remote['applicationId'] ?? null)
        && is_string($remote['environmentId'] ?? null)
        && hash_equals($appId, $remote['applicationId'])
        && hash_equals($envId, $remote['environmentId']);
}

/** Verify the stored three-way binding before a client action. */
function eclouddokploy_service_isolation_valid(
    $storedClientId, $storedProjectId, $storedEnvironmentId, int $currentClientId
): bool {
    return $currentClientId > 0
        && (int) $storedClientId === $currentClientId
        && is_string($storedProjectId)
        && preg_match('/^[A-Za-z0-9_-]{3,128}$/D', $storedProjectId) === 1
        && is_string($storedEnvironmentId)
        && preg_match('/^[A-Za-z0-9_-]{3,128}$/D', $storedEnvironmentId) === 1;
}


/**
 * Create the customer's private Dokploy project and production environment
 * immediately after the FIRST PAID invoice. The GitHub repository isn't
 * needed yet: project provisioning is independent of application deployment.
 *
 * Requires WHMCS service lock ecloud-dokploy-{serviceId} to be held by caller.
 * Persist "creating_*" before writes to avoid duplicating resources when a
 * response is lost. Reconciliation of uncertain writes is admin-only.
 *
 * Returns a status, not "success": callers must only mark the WHMCS module
 * command successful once project and environment IDs were persisted.
 */
function eclouddokploy_prepare_private_project_locked(
    ECloudDokployClient $api,
    int $serviceId,
    int $clientId,
    string $routeFingerprint
): string {
    if ($serviceId < 1 || $clientId < 1
        || !preg_match('/^[a-f0-9]{64}$/D', $routeFingerprint)) {
        return 'No se pudo validar el propietario o la ruta del VPS.';
    }
    $table = \WHMCS\Database\Capsule::table('mod_ecloud_dokploy_services');
    $row = $table->where('service_id', $serviceId)->first();
    if (!$row || (int) $row->owner_client_id !== $clientId
        || !is_string($row->route_fingerprint)
        || !hash_equals($row->route_fingerprint, $routeFingerprint)) {
        return 'No se pudo validar la cuenta y su servidor asignado.';
    }
    $state = (string) $row->state;
    $projectId = (string) ($row->project_id ?? '');
    $envId = (string) ($row->environment_id ?? '');

    if ($state === 'awaiting_setup') {
        eclouddokploy_set_state($serviceId, 'creating_project');
        $project = $api->post(
            'project.create', eclouddokploy_project_create_payload($clientId, $serviceId)
        );
        $projectId = eclouddokploy_required_id($project, 'projectId');
        eclouddokploy_set_state(
            $serviceId, 'project_created', ['project_id' => $projectId]
        );
        $state = 'project_created';
    }

    if ($state === 'project_created') {
        if ($projectId === '' || !preg_match('/^[A-Za-z0-9_-]{3,128}$/D', $projectId)) {
            return 'El proyecto creado requiere conciliación administrativa.';
        }
        $found = eclouddokploy_find_production_environment(
            $api->get('environment.byProjectId', ['projectId' => $projectId]),
            $projectId
        );
        if ($found === null) {
            eclouddokploy_set_state($serviceId, 'creating_environment');
            $created = $api->post('environment.create', [
                'name' => 'production', 'projectId' => $projectId,
            ]);
            $found = eclouddokploy_required_id($created, 'environmentId');
        }
        eclouddokploy_set_state(
            $serviceId, 'environment_ready', ['environment_id' => $found]
        );
        $state = 'environment_ready';
        $envId = $found;
    }

    // An already running deployment is also an already prepared project.
    if (in_array($state, ['environment_ready', 'db_created', 'db_deployed',
        'app_created', 'configuring_app', 'app_configured', 'domain_created',
        'deployment_requested'], true)
        && preg_match('/^[A-Za-z0-9_-]{3,128}$/D', $projectId) === 1
        && preg_match('/^[A-Za-z0-9_-]{3,128}$/D', $envId) === 1) {
        return 'success';
    }

    return 'La creación del proyecto está pendiente o requiere revisión. No se ha repetido la operación.';
}
