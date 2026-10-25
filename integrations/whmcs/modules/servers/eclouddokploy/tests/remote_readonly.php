<?php
declare(strict_types=1);

/**
 * Read-only Dokploy smoke test to run ON THE WHMCS STAGING SERVER.
 * Never logs API tokens, raw API responses, application names or credentials.
 * No writes, no app creation/deletion/redeployment, no WHMCS DB access.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/lib/DokployClient.php';

function print_check(string $status, string $code, array $checks = []): void
{
    echo json_encode([
        'status' => $status,
        'code' => $code,
        'checks' => $checks,
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
}

if (PHP_VERSION_ID < 80200 || !extension_loaded('curl') || !extension_loaded('json')) {
    print_check('fail', 'missing_php_requirements');
    exit(2);
}

$host = trim((string) getenv('ECLOUD_DOKPLOY_URL'));
$token = trim((string) getenv('ECLOUD_DOKPLOY_API_KEY'));
if ($host === '' || $token === '') {
    print_check('not_run', 'server_environment_not_configured');
    exit(2);
}

try {
    $api = new ECloudDokployClient($host, $token);
    // Authentication, TLS, network and basic JSON response shape.
    // GET project.all has no mutations, but might reveal sensitive details:
    // discard body, report only a bounded count.
    $projects = $api->get('project.all', []);
    if (!array_is_list($projects)) {
        throw new RuntimeException('Unexpected projects response shape');
    }
    $checks = [
        'php' => 'ok',
        'tls_and_api_auth' => 'ok',
        'projects_api' => 'ok',
        'projects_visible' => count($projects),
    ];

    // Optional: only when a *non-production* application ID is provided.
    $id = trim((string) getenv('ECLOUD_DOKPLOY_TEST_APPLICATION_ID'));
    if ($id !== '') {
        if (!preg_match('/^[a-zA-Z0-9_-]{3,128}$/D', $id)) {
            throw new InvalidArgumentException('Invalid test application ID');
        }
        $application = $api->get('application.one', ['applicationId' => $id]);
        if (!is_string($application['applicationId'] ?? null)
            || !hash_equals($id, $application['applicationId'])) {
            throw new RuntimeException('Application ID mismatch');
        }
        $checks['test_application_lookup'] = 'ok';
        $deployments = $api->get('deployment.all', ['applicationId' => $id]);
        $list = array_is_list($deployments)
            ? $deployments
            : ($deployments['deployments'] ?? null);
        if (!is_array($list)) throw new RuntimeException('Unexpected deployment response shape');
        $checks['test_application_deployments_api'] = 'ok';
        $checks['test_application_deployments_seen'] = min(1000, count($list));
    }
    print_check('pass', 'read_only_api_reachable', $checks);
} catch (Throwable $e) {
    // Do NOT echo $e or its message: external APIs can return secrets.
    print_check('fail', 'api_connection_or_contract_failed');
    exit(1);
}
