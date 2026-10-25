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
