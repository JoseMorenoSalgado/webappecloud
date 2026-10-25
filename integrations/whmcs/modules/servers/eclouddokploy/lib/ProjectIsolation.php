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

/**
 * Discover one WHMCS tenant project without loading every Dokploy service.
 * Dokploy project.search returns {items: [...], total: n} and is intentionally
 * much lighter than project.all (which includes nested service records).
 *
 * A search result is never assumed complete if truncated; before any create
 * the module must have an authoritative negative lookup. Unsupported older
 * versions (HTTP 404/405) fall back to the earlier read-only project.all.
 *
 * @return ?string Dokploy's real projectId, or null after verified absence.
 */
function eclouddokploy_lookup_service_project(
    ECloudDokployClient $api, int $clientId, int $serviceId
): ?string {
    $name = eclouddokploy_project_name($clientId, $serviceId);
    try {
        $response = $api->get('project.search', [
            'name' => $name, 'limit' => 100, 'offset' => 0,
        ]);
    } catch (RuntimeException $e) {
        // Never fall back after auth/permission errors or ambiguous 500s.
        // Older Dokploy API releases may not expose project.search at all.
        if (!preg_match(
            '/^Dokploy request failed: project[.]search [(]HTTP (404|405)[)][.]$/D',
            $e->getMessage()
        )) {
            throw $e;
        }
        return eclouddokploy_find_exact_service_project(
            $api->get('project.all', []), $clientId, $serviceId
        );
    }
    $items = $response['items'] ?? null;
    $total = $response['total'] ?? null;
    if (!is_array($items) || !array_is_list($items)
        || !is_int($total) || $total < 0 || $total > count($items)
        || count($items) > 100) {
        // Unexpected data must never be interpreted as "no project".
        throw new RuntimeException('Dokploy project.search returned an incomplete or unknown result.');
    }
    return eclouddokploy_find_exact_service_project($items, $clientId, $serviceId);
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

    // ALL administration paths start with a read-only lookup by the exact
    // WHMCS service name, even if local state says environment_ready. This
    // catches orphaned/stale IDs and recovers a Dokploy project that already
    // exists without making another project.create request.
    $preProject = ['awaiting_setup', 'new', 'creating_project'];
    $preEnvironment = ['project_created', 'creating_environment'];
    $readyStates = [
        'environment_ready', 'creating_db', 'db_created', 'deploying_db',
        'db_deployed', 'creating_app', 'app_created', 'configuring_app',
        'app_configured', 'creating_domain', 'domain_created',
        'requesting_deploy', 'deployment_requested',
    ];
    if (!in_array($state, array_merge($preProject, $preEnvironment, $readyStates), true)) {
        return 'Este servicio tiene un estado desconocido. No se modificaron recursos Dokploy.';
    }

    $remoteProjectId = eclouddokploy_lookup_service_project(
        $api, $clientId, $serviceId
    );
    if ($remoteProjectId !== null) {
        if ($projectId !== '' && !hash_equals($projectId, $remoteProjectId)) {
            return 'El ID guardado en WHMCS no corresponde al proyecto existente en Dokploy. '
                . 'Se requiere revisión; no se vinculó otro proyecto.';
        }
        $projectId = $remoteProjectId;
        if (in_array($state, $preProject, true)) {
            // Dokploy has the real project: reconcile the lost response first.
            // This also covers old services saved as awaiting_setup or new.
            eclouddokploy_set_state($serviceId, 'project_created', [
                'project_id' => $projectId,
            ]);
            $state = 'project_created';
        } elseif ((string) ($row->project_id ?? '') === '') {
            // State may already be project_created/environment_ready, but
            // the project ID was not persisted by an older installation.
            // Restore only the missing ID; do not change an advanced state.
            eclouddokploy_set_state($serviceId, $state, [
                'project_id' => $projectId,
            ]);
        }
    } elseif ($projectId !== '') {
        return 'WHMCS tiene un ID de proyecto, pero no aparece en Dokploy '
            . 'para este servicio. No se creó otro.';
    } elseif ($state === 'creating_project') {
        // A missing response remains ambiguous if the read found nothing.
        // Never turn it into another project.create blindly.
        return 'No encontramos el proyecto pendiente en Dokploy. '
            . 'No se creará otro automáticamente; soporte debe revisar el registro de Dokploy.';
    } elseif (in_array($state, ['awaiting_setup', 'new'], true)) {
        // First write only after a successful authoritative negative read.
        eclouddokploy_set_state($serviceId, 'creating_project');
        $created = $api->post(
            'project.create', eclouddokploy_project_create_payload($clientId, $serviceId)
        );
        $projectId = eclouddokploy_required_id($created, 'projectId');
        if (preg_match('/^[A-Za-z0-9_-]{3,128}$/D', $projectId) !== 1) {
            return 'Dokploy devolvió un identificador de proyecto no válido.';
        }
        eclouddokploy_set_state($serviceId, 'project_created', [
            'project_id' => $projectId,
        ]);
        $state = 'project_created';
    } else {
        return 'El proyecto registrado no aparece en Dokploy. '
            . 'No se crearon recursos; revisa la conexión y el estado remoto.';
    }

    if ($projectId === ''
        || preg_match('/^[A-Za-z0-9_-]{3,128}$/D', $projectId) !== 1) {
        return 'No se pudo recuperar un ID válido del proyecto Dokploy.';
    }

    // Verify production even if WHMCS already stored an environment ID.
    // The response is strictly scoped to the reconciled projectId.
    $remoteEnvironmentId = eclouddokploy_find_production_environment(
        $api->get('environment.byProjectId', ['projectId' => $projectId]),
        $projectId
    );
    if ($remoteEnvironmentId !== null) {
        if ($envId !== '' && !hash_equals($envId, $remoteEnvironmentId)) {
            return 'El entorno production no coincide con el ID registrado en WHMCS. '
                . 'No se cambió su vinculación.';
        }
        $envId = $remoteEnvironmentId;
        if (in_array($state, $preEnvironment, true)) {
            eclouddokploy_set_state($serviceId, 'environment_ready', [
                'environment_id' => $envId,
            ]);
            $state = 'environment_ready';
        } elseif ((string) ($row->environment_id ?? '') === '') {
            // Repair a missing ID in a later state without rewinding app/DB.
            eclouddokploy_set_state($serviceId, $state, [
                'environment_id' => $envId,
            ]);
        }
    } elseif ($envId !== '') {
        return 'WHMCS guarda un entorno production que no aparece en el proyecto '
            . 'Dokploy. No se creó otro.';
    } elseif ($state === 'creating_environment') {
        return 'El entorno production no aparece todavía en Dokploy. '
            . 'No se creó otro entorno; soporte debe comprobar el estado de la petición.';
    } elseif ($state === 'project_created') {
        // The project exists but Dokploy did not automatically add production.
        eclouddokploy_set_state($serviceId, 'creating_environment');
        $created = $api->post('environment.create', [
            'name' => 'production', 'projectId' => $projectId,
        ]);
        $envId = eclouddokploy_required_id($created, 'environmentId');
        if (preg_match('/^[A-Za-z0-9_-]{3,128}$/D', $envId) !== 1) {
            return 'Dokploy devolvió un identificador de entorno no válido.';
        }
        eclouddokploy_set_state($serviceId, 'environment_ready', [
            'environment_id' => $envId,
        ]);
        $state = 'environment_ready';
    } else {
        return 'Falta el entorno production del proyecto existente. '
            . 'No se creó otro recurso automáticamente.';
    }

    // This says only "project and environment verified"; it never claims
    // the application, GitHub source, database or website is published.
    return 'success';
}
