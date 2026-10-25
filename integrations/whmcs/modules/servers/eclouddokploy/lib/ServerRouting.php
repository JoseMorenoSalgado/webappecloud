<?php
declare(strict_types=1);

/**
 * Routes a WHMCS service to exactly one Dokploy control plane and deploy node.
 * Secrets are stored outside of the WHMCS webroot, indexed by WHMCS server ID.
 *
 * One WHMCS server entry == one placement target (Dokploy instance + node).
 * The same Dokploy URL may legitimately appear in multiple entries, each with
 * a distinct remote_server_id.
 */

function eclouddokploy_load_server_map(): array
{
    $path = trim((string) getenv('ECLOUD_DOKPLOY_SERVER_MAP_FILE'));
    if ($path === '' || !str_starts_with($path, '/') ||
        is_link($path) || !is_file($path) || !is_readable($path) ||
        filesize($path) > 262144 || ((fileperms($path) & 0077) !== 0)) {
        throw new RuntimeException('Dokploy server map missing or permissions are unsafe.');
    }
    $decoded = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($decoded) || !isset($decoded['servers']) || !is_array($decoded['servers'])) {
        throw new RuntimeException('Dokploy server map must contain a servers object.');
    }
    return $decoded['servers'];
}

function eclouddokploy_route_fingerprint(array $route): string
{
    return hash('sha256', json_encode([
        (int) $route['whmcs_server_id'],
        (string) $route['url'],
        (string) $route['environment_id'],
        (string) $route['remote_server_id'],
    ], JSON_THROW_ON_ERROR));
}

/**
 * Accepts WHMCS-generated serverid, never a user-supplied browser field.
 * Legacy environment-variable mode is limited to unassigned services,
 * and only when explicitly opted in by the administrator.
 */
function eclouddokploy_resolve_route(array $params): array
{
    $id = filter_var($params['serverid'] ?? 0, FILTER_VALIDATE_INT);
    $id = $id === false ? 0 : (int) $id;
    if ($id < 0) throw new RuntimeException('Invalid WHMCS server ID.');

    $path = trim((string) getenv('ECLOUD_DOKPLOY_SERVER_MAP_FILE'));
    if ($path === '') {
        if ($id !== 0 || getenv('ECLOUD_DOKPLOY_ALLOW_LEGACY_SINGLE') !== '1') {
            throw new RuntimeException('Configure the WHMCS-to-Dokploy server map.');
        }
        $route = [
            'whmcs_server_id' => 0,
            'url' => trim((string) getenv('ECLOUD_DOKPLOY_URL')),
            'api_key' => trim((string) getenv('ECLOUD_DOKPLOY_API_KEY')),
            'environment_id' => trim((string) ($params['configoption1'] ?? '')),
            'remote_server_id' => '',
        ];
    } else {
        if ($id < 1) throw new RuntimeException('The WHMCS service must have an assigned server.');
        $map = eclouddokploy_load_server_map();
        $config = $map[(string) $id] ?? null;
        if (!is_array($config)) {
            throw new RuntimeException('This WHMCS server has no configured Dokploy route.');
        }
        $route = [
            'whmcs_server_id' => $id,
            'url' => trim((string) ($config['url'] ?? '')),
            'api_key' => trim((string) ($config['api_key'] ?? '')),
            'environment_id' => trim((string) ($config['environment_id'] ?? '')),
            'remote_server_id' => trim((string) ($config['remote_server_id'] ?? '')),
        ];
    }

    if (!preg_match('/^[A-Za-z0-9_-]{4,128}$/D', $route['environment_id'])) {
        throw new RuntimeException('Invalid Dokploy environment ID for assigned server.');
    }
    if ($route['remote_server_id'] !== '' &&
        !preg_match('/^[A-Za-z0-9_-]{3,128}$/D', $route['remote_server_id'])) {
        throw new RuntimeException('Invalid Dokploy remote server ID.');
    }

    // Constructor enforces HTTPS, private token and safe URL properties.
    new ECloudDokployClient($route['url'], $route['api_key']);
    $route['fingerprint'] = eclouddokploy_route_fingerprint($route);
    return $route;
}

/** Existing services can never be silently re-homed to a new server. */
function eclouddokploy_route_matches(?string $persistedFingerprint, array $route): bool
{
    return is_string($persistedFingerprint) &&
        preg_match('/^[a-f0-9]{64}$/D', $persistedFingerprint) === 1 &&
        hash_equals($persistedFingerprint, $route['fingerprint']);
}

function eclouddokploy_application_create_payload(
    string $environmentId, string $name, string $remoteServerId
): array {
    $payload = [
        'name' => $name,
        'appName' => $name,
        'environmentId' => $environmentId,
        'sourceType' => 'git',
    ];
    if ($remoteServerId !== '') $payload['serverId'] = $remoteServerId;
    return $payload;
}
