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

/**
 * Read-only recovery of a project by the exact deterministic WHMCS name.
 * Never pick the first vaguely matching project. A duplicate project name,
 * incorrect description, or invalid ID requires human review.
 *
 * Dokploy's project.all GET is authoritative for the one configured API
 * endpoint only. The server route fingerprint and owner are validated by
 * the caller before this read.
 */
function eclouddokploy_find_exact_service_project(
    array $response, int $clientId, int $serviceId
): ?string {
    $items = array_is_list($response) ? $response : ($response['projects'] ?? null);
    if (!is_array($items)) {
        throw new RuntimeException('Dokploy did not return a list of projects.');
    }
    $expectedName = eclouddokploy_project_name($clientId, $serviceId);
    $expectedDescription = 'Elearning Cloud WHMCS service ' . $serviceId;
    $matches = [];
    foreach ($items as $project) {
        if (!is_array($project) || ($project['name'] ?? null) !== $expectedName) {
            continue;
        }
        // Some Dokploy releases omit description from the list; a supplied
        // description must nevertheless agree with the original binding.
        $description = $project['description'] ?? null;
        if ($description !== null && $description !== ''
            && $description !== $expectedDescription) {
            throw new RuntimeException('Project description does not match this WHMCS service.');
        }
        $projectId = $project['projectId'] ?? null;
        if (!is_string($projectId)
            || preg_match('/^[A-Za-z0-9_-]{3,128}$/D', $projectId) !== 1) {
            throw new RuntimeException('Invalid Dokploy project ID during reconciliation.');
        }
        $matches[] = $projectId;
    }
    if (count($matches) > 1) {
        throw new RuntimeException('Duplicate project names: manual reconciliation required.');
    }
    return $matches[0] ?? null;
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

    // "new" is the valid pre-project state used by older module versions.
    // Before creating anything, read Dokploy's projects by exact service name.
    // This recovers a lost project.create response without another write.
    if (in_array($state, ['awaiting_setup', 'new', 'creating_project'], true)) {
        $found = eclouddokploy_find_exact_service_project(
            $api->get('project.all', []), $clientId, $serviceId
        );
        if ($found !== null) {
            if ($projectId !== '' && !hash_equals($projectId, $found)) {
                return 'El proyecto remoto no coincide con el proyecto registrado. Requiere revisión.';
            }
            $projectId = $found;
            eclouddokploy_set_state(
                $serviceId, 'project_created', ['project_id' => $projectId]
            );
            $state = 'project_created';
        } elseif ($state === 'creating_project') {
            // A lost remote response remains ambiguous if the read found
            // nothing. Never convert it into a second project.create.
            return 'No encontramos el proyecto pendiente en Dokploy. '
                . 'No se creará otro automáticamente; soporte debe revisar el registro de Dokploy.';
        } else {
            // Fresh "awaiting_setup" or legacy "new": no matching remote
            // project exists, so it is safe to attempt the first write.
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
    }

    if (in_array($state, ['project_created', 'creating_environment'], true)) {
        if ($projectId === ''
            || preg_match('/^[A-Za-z0-9_-]{3,128}$/D', $projectId) !== 1) {
            return 'El proyecto creado requiere conciliación administrativa.';
        }
        $found = eclouddokploy_find_production_environment(
            $api->get('environment.byProjectId', ['projectId' => $projectId]),
            $projectId
        );
        if ($found === null && $state === 'creating_environment') {
            // The prior request may still be finishing; avoid retrying.
            return 'El entorno production no aparece todavía en Dokploy. '
                . 'No se creó otro entorno; soporte debe comprobar el estado de la petición.';
        }
        if ($found === null) {
            eclouddokploy_set_state($serviceId, 'creating_environment');
            $created = $api->post('environment.create', [
                'name' => 'production', 'projectId' => $projectId,
            ]);
            $found = eclouddokploy_required_id($created, 'environmentId');
        }
        if ($envId !== '' && !hash_equals($envId, $found)) {
            return 'El entorno existente no coincide con el registrado. Requiere revisión.';
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
