<?php
declare(strict_types=1);

/** Offline, no-network smoke tests for module validation and secret derivation. */
define('WHMCS', true);
require_once dirname(__DIR__) . '/eclouddokploy.php';

function check(bool $ok, string $label): void
{
    if (!$ok) throw new RuntimeException('Test failed: ' . $label);
}

check(eclouddokploy_valid_domain('example.com'), 'valid domain');
check(eclouddokploy_valid_domain('app.example.com'), 'valid subdomain');
check(!eclouddokploy_valid_domain('bad_domain.com'), 'reject underscore');
check(!eclouddokploy_valid_domain('example.com/path'), 'reject domain path');
check(eclouddokploy_valid_git('https://github.com/JoseMorenoSalgado/webappecloud.git', 'main'), 'valid Git URL');
check(!eclouddokploy_valid_git('http://github.com/owner/repo', 'main'), 'reject plain HTTP');
check(!eclouddokploy_valid_git('https://github.com/owner/repo?token=secret', 'main'), 'reject query token');
check(!eclouddokploy_valid_git('https://git.bad.example/owner/repo', 'main'), 'reject untrusted host');
check(in_array('mariadb', ['mariadb', 'mysql', 'postgres', 'external', 'none'], true), 'MariaDB option available');
check(in_array('mysql', ['mariadb', 'mysql', 'postgres', 'external', 'none'], true), 'MySQL option available');
check(in_array('postgres', ['mariadb', 'mysql', 'postgres', 'external', 'none'], true), 'Postgres option available');
$seed = str_repeat('ab', 32);
$one = eclouddokploy_password($seed, 'db-user', 123);
check(strlen($one) === 64, 'derived password length');
check(hash_equals($one, eclouddokploy_password($seed, 'db-user', 123)), 'deterministic retries');
check(!hash_equals($one, eclouddokploy_password($seed, 'db-root', 123)), 'separate database root secret');
check(!hash_equals($one, eclouddokploy_password($seed, 'db-user', 124)), 'separate client credentials');

try {
    new ECloudDokployClient('http://localhost:3000', 'x');
    throw new RuntimeException('Test failed: accepted HTTP');
} catch (RuntimeException $e) {
    check($e->getMessage() !== 'Test failed: accepted HTTP', 'reject HTTP Dokploy API');
}
$externalMap = tempnam(sys_get_temp_dir(), 'external-db-test-');
file_put_contents($externalMap, json_encode(['123' => 'postgresql://app:pass@private-db.example:5432/appdb']));
putenv('ECLOUD_DOKPLOY_EXTERNAL_DB_MAP_FILE=' . $externalMap);
check(eclouddokploy_external_database_url(123) === 'postgresql://app:pass@private-db.example:5432/appdb', 'external PostgreSQL connection');
try {
    eclouddokploy_external_database_url(124);
    throw new RuntimeException('Test failed: missing service DB config');
} catch (RuntimeException $e) {
    check($e->getMessage() !== 'Test failed: missing service DB config', 'external DB isolated by service');
}
file_put_contents($externalMap, json_encode(['123' => "mysql://bad:pass@host/db\ninjected=1"]));
try {
    eclouddokploy_external_database_url(123);
    throw new RuntimeException('Test failed: accepted multiline secret');
} catch (RuntimeException $e) {
    check($e->getMessage() !== 'Test failed: accepted multiline secret', 'reject env injection');
}
unlink($externalMap);

// The independent product overview is scoped and read-only in this pilot.
$clientState = eclouddokploy_public_state('deployment_requested');
check($clientState[0] === 'Despliegue solicitado', 'deployment is not labeled online');
$unknownState = eclouddokploy_public_state('unexpected-state');
check($unknownState[0] === 'Revisión técnica', 'unknown state fails closed');
$_SESSION['uid'] = 0;
$clientArea = eclouddokploy_ClientArea([
    'serviceid' => 123,
    'userid' => 123,
    'pid' => 12,
    'domain' => 'example.com',
]);
check($clientArea['templatefile'] === 'templates/clientarea', 'native WHMCS module template');
check($clientArea['vars']['ecDokployAuthorized'] === false, 'unauthenticated visitor denied');
check(!array_key_exists('password', $clientArea['vars']), 'no secrets in template variables');


$running = eclouddokploy_application_status('running');
check($running[0] === 'Compilando o desplegando', 'live status mapping');
check(eclouddokploy_application_status('unexpected')[0] === 'Estado no disponible', 'unknown live status protected');
$history = eclouddokploy_deployment_rows([
    ['status' => 'success', 'createdAt' => '2026-10-24T18:00:00Z', 'secret' => 'do-not-show'],
    ['status' => 'failed', 'createdAt' => 'garbage'],
]);
check(count($history) === 2, 'deployment history count');
check($history[0]['status'] === 'Correcto', 'sanitized deployment status');
check($history[0]['date'] === '2026-10-24 18:00 UTC', 'UTC date normalization');
check(!isset($history[0]['secret']), 'no raw Dokploy field rendered');
check($history[1]['date'] === 'Fecha no disponible', 'reject invalid dates');
check(count(eclouddokploy_deployment_rows(array_fill(0, 20, ['status' => 'done']))) === 5, 'deployment list bounded');
$_SESSION['uid'] = 123;
$csrf = eclouddokploy_csrf(555);
check(strlen($csrf) === 64 && $csrf === eclouddokploy_csrf(555), 'per-service stable csrf');
check($csrf !== eclouddokploy_csrf(556), 'per-service separate csrf');
putenv('ECLOUD_DOKPLOY_ENABLE_CLIENT_REDEPLOY=0');
check(eclouddokploy_ClientAreaAllowedFunctions() === [], 'no client action by default');
check(str_contains(eclouddokploy_redeploy_request(['serviceid' => 555]), 'no está habilitada'), 'action fails closed');
putenv('ECLOUD_DOKPLOY_ENABLE_CLIENT_REDEPLOY=1');
check(eclouddokploy_ClientAreaAllowedFunctions() === ['Redeploy'], 'optional client action registered');
$_SERVER['REQUEST_METHOD'] = 'GET';
check(str_contains(eclouddokploy_redeploy_request(['serviceid' => 555, 'userid' => 123]), 'no autorizada'), 'client action refuses GET');
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['id' => '555', 'modop' => 'custom', 'a' => 'Redeploy', 'ec_confirm' => 'yes', 'ec_redeploy_token' => 'bad'];
check(str_contains(eclouddokploy_redeploy_request(['serviceid' => 555, 'userid' => 123]), 'expiró'), 'reject invalid csrf before DB call');
$_SESSION['uid'] = 456;
check(str_contains(eclouddokploy_redeploy_request(['serviceid' => 555, 'userid' => 123]), 'no autorizada'), 'reject other WHMCS user');


// Match the documented field requirements for Dokploy database.create.
foreach (['mariadb', 'mysql', 'postgres'] as $mode) {
    $payload = eclouddokploy_database_payload(
        $mode, 'env-test', 'db-test', 'dbtest', 'dbuser', 'db-password', 'root-password'
    );
    foreach (['name', 'environmentId', 'databaseName', 'databaseUser', 'databasePassword', 'dockerImage'] as $required) {
        check(isset($payload[$required]), $mode . ' required field: ' . $required);
    }
    check(str_starts_with($payload['dockerImage'], $mode . ':'), $mode . ' image pinned');
    check($mode !== 'postgres' || !isset($payload['databaseRootPassword']), 'no PG root password');
    check($mode === 'postgres' || $payload['databaseRootPassword'] === 'root-password', 'SQL root password');
}
try {
    eclouddokploy_database_payload('mongodb', 'env', 'app', 'db', 'user', 'pass', 'root');
    throw new RuntimeException('Test failed: unsupported engine accepted');
} catch (InvalidArgumentException $e) {
    check(true, 'unsupported SQL engine denied');
}
$buildPayload = eclouddokploy_dockerfile_payload('app-id-123');
foreach (['applicationId', 'buildType', 'dockerfile', 'dockerContextPath', 'dockerBuildStage', 'herokuVersion', 'railpackVersion'] as $field) {
    check(array_key_exists($field, $buildPayload), 'required buildType schema field: ' . $field);
}
check($buildPayload['buildType'] === 'dockerfile', 'Dockerfile build');

echo "Dokploy provisioning validations passed\n";
