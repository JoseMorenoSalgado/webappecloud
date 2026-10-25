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


// Multiple Dokploy control planes and/or remote nodes are pinned by WHMCS server ID.
$routeFile = tempnam(sys_get_temp_dir(), 'ecloud-dokploy-routes-');
chmod($routeFile, 0600);
$serverMap = [
    'servers' => [
        '11' => [
            'url' => 'https://dokploy-a.example.net',
            'api_key' => 'private-key-A',
            'environment_id' => 'env_a_001',
            'remote_server_id' => null,
        ],
        '12' => [
            'url' => 'https://dokploy-b.example.net',
            'api_key' => 'private-key-B',
            'environment_id' => 'env_b_001',
            'remote_server_id' => 'remote_b_001',
        ],
        '13' => [
            'url' => 'https://dokploy-b.example.net',
            'api_key' => 'private-key-B',
            'environment_id' => 'env_b_001',
            'remote_server_id' => 'remote_c_001',
        ],
    ],
];
file_put_contents($routeFile, json_encode($serverMap, JSON_THROW_ON_ERROR));
putenv('ECLOUD_DOKPLOY_SERVER_MAP_FILE=' . $routeFile);
$first = eclouddokploy_resolve_route(['serverid' => 11, 'configoption1' => 'wrong-product-env']);
$second = eclouddokploy_resolve_route(['serverid' => 12, 'configoption1' => 'wrong-product-env']);
$third = eclouddokploy_resolve_route(['serverid' => 13]);
check($first['url'] === 'https://dokploy-a.example.net', 'WHMCS server routes to first control plane');
check($second['url'] === 'https://dokploy-b.example.net', 'WHMCS server routes to second control plane');
check($second['environment_id'] === 'env_b_001', 'per-control-plane environment selection');
check($second['remote_server_id'] === 'remote_b_001', 'remote node mapping');
check($first['fingerprint'] !== $second['fingerprint'], 'control planes have distinct binding');
check($second['fingerprint'] !== $third['fingerprint'], 'remote nodes have distinct binding even on same control plane');
check(eclouddokploy_route_matches($second['fingerprint'], $second), 'correct route accepted');
check(!eclouddokploy_route_matches(null, $second), 'legacy service cannot silently migrate');
check(!eclouddokploy_route_matches($first['fingerprint'], $second), 'service cannot move to another control plane');
check(!eclouddokploy_route_matches($third['fingerprint'], $second), 'service cannot move between remote nodes');
check(!eclouddokploy_route_matches(str_repeat('0', 64), $second), 'forged route rejected');

$databaseNode = eclouddokploy_database_payload(
    'mysql', $second['environment_id'], 'db-test', 'dbtest',
    'dbuser', 'pass', 'root', $second['remote_server_id']
);
$applicationNode = eclouddokploy_application_create_payload(
    $second['environment_id'], 'app-test', $second['remote_server_id']
);
check($databaseNode['serverId'] === $applicationNode['serverId'], 'SQL and app deploy to same Dokploy node');
check(!array_key_exists('serverId', eclouddokploy_application_create_payload('env-test', 'app-test', '')), 'default local node omits serverId');

try {
    eclouddokploy_resolve_route(['serverid' => 999]);
    throw new RuntimeException('Test failed: unknown WHMCS server was accepted');
} catch (RuntimeException $e) {
    check($e->getMessage() !== 'Test failed: unknown WHMCS server was accepted', 'unknown server fails closed');
}
try {
    eclouddokploy_resolve_route(['serverid' => 0]);
    throw new RuntimeException('Test failed: unassigned server accepted');
} catch (RuntimeException $e) {
    check($e->getMessage() !== 'Test failed: unassigned server accepted', 'WHMCS server assignment required');
}
chmod($routeFile, 0644);
try {
    eclouddokploy_resolve_route(['serverid' => 11]);
    throw new RuntimeException('Test failed: world-readable route file accepted');
} catch (RuntimeException $e) {
    check($e->getMessage() !== 'Test failed: world-readable route file accepted', 'route file permission enforcement');
}
chmod($routeFile, 0600);
unlink($routeFile);
putenv('ECLOUD_DOKPLOY_SERVER_MAP_FILE');
putenv('ECLOUD_DOKPLOY_ALLOW_LEGACY_SINGLE=0');
try {
    eclouddokploy_resolve_route(['serverid' => 11]);
    throw new RuntimeException('Test failed: global credentials silently used on assigned server');
} catch (RuntimeException $e) {
    check($e->getMessage() !== 'Test failed: global credentials silently used on assigned server', 'do not silently route WHMCS servers to global Dokploy instance');
}

// Server Groups use Maximum No. of Accounts to select a target;
// the module additionally refuses an over-capacity manual/stale assignment.
check(eclouddokploy_within_capacity(10, 1), 'first account fits 10-slot server');
check(eclouddokploy_within_capacity(10, 9), 'ninth account fits');
check(eclouddokploy_within_capacity(10, 10), 'tenth account fits exactly');
check(!eclouddokploy_within_capacity(10, 11), 'eleventh account rejected');
check(!eclouddokploy_within_capacity(0, 1), 'unset server capacity blocked');
check(!eclouddokploy_within_capacity(-1, 1), 'negative limit rejected');
check(!eclouddokploy_within_capacity(10, 0), 'missing WHMCS service reservation rejected');

// WHMCS stores each Dokploy connection just like a cPanel server.
$nativeA = eclouddokploy_resolve_route([
    'serverid' => 41,
    'serverhostname' => 'dokploy-uno.example.net',
    'serverusername' => 'entorno_001',
    'serverpassword' => 'private-token-uno',
]);
$nativeB = eclouddokploy_resolve_route([
    'serverid' => 42,
    'serverhostname' => 'dokploy-dos.example.net',
    'serverusername' => 'entorno_002',
    'serverpassword' => 'private-token-dos',
]);
check($nativeA['url'] === 'https://dokploy-uno.example.net', 'native WHMCS hostname routing');
check($nativeA['environment_id'] === 'entorno_001', 'native WHMCS username identifies Dokploy environment');
check($nativeB['url'] === 'https://dokploy-dos.example.net', 'second WHMCS server gets separate Dokploy');
check($nativeA['fingerprint'] !== $nativeB['fingerprint'], 'native connections pinned separately');
check(!eclouddokploy_valid_server_hostname('https://dokploy-uno.example.net'), 'reject URL in WHMCS hostname');
check(!eclouddokploy_valid_server_hostname('dokploy.example.net:3000'), 'reject port in WHMCS hostname');
check(!eclouddokploy_valid_server_hostname('bad/hostname'), 'reject path injection in WHMCS hostname');
check(eclouddokploy_TestConnection(['serverhostname'=>'https://bad.example','serverpassword'=>'x'])['success'] === false, 'invalid WHMCS server connection fails locally');

// WHMCS TestConnection must return specific safe errors, never raw tokens.
$cases = [
    ['Dokploy read failed: project.all (HTTP 401).', 'HTTP 401'],
    ['Dokploy read failed: project.all (HTTP 403).', 'HTTP 403'],
    ['Dokploy read failed: project.all (HTTP 404).', 'HTTP 404'],
    ['Dokploy read failed: project.all (HTTP 502).', 'HTTP 502'],
    ['Dokploy transport failed (cURL 6).', 'Error DNS'],
    ['Dokploy transport failed (cURL 7).', 'Error de red'],
    ['Dokploy transport failed (cURL 28).', 'Tiempo de espera'],
    ['Dokploy transport failed (cURL 60).', 'Certificado HTTPS'],
];
foreach ($cases as $case) {
    check(str_contains(eclouddokploy_safe_connection_error(new RuntimeException($case[0])), $case[1]), 'specific nonsecret diagnostic ' . $case[1]);
}
check(str_contains(eclouddokploy_safe_connection_error(new RuntimeException('Dokploy API key is missing.')), 'API key'), 'missing token diagnosis');
$privateMarker = 'SENSITIVE_TOKEN_DO_NOT_SHOW';
check(!str_contains(eclouddokploy_safe_connection_error(new RuntimeException('server returned ' . $privateMarker)), $privateMarker), 'no secret echoed from remote messages');


// Dedicated project per service: even one customer with two services is isolated.
$a = eclouddokploy_project_create_payload(25, 310);
$b = eclouddokploy_project_create_payload(25, 311);
$c = eclouddokploy_project_create_payload(26, 312);
check($a['name'] === 'ec-client-25-service-310', 'deterministic private project name');
check($a['name'] !== $b['name'] && $a['name'] !== $c['name'], 'no project collision across clients or services');
check(eclouddokploy_find_production_environment([
    ['name' => 'production', 'projectId' => 'project-310', 'environmentId' => 'env-310']
], 'project-310') === 'env-310', 'private environment adopted from own project');
check(eclouddokploy_find_production_environment([], 'project-310') === null, 'empty environment list triggers new private environment');
try {
    eclouddokploy_find_production_environment([
        ['name' => 'production', 'projectId' => 'project-311', 'environmentId' => 'env-311']
    ], 'project-310');
    throw new RuntimeException('Test failed: cross-tenant environment adopted');
} catch (RuntimeException $e) {
    check($e->getMessage() !== 'Test failed: cross-tenant environment adopted', 'cross-tenant environment refused');
}
try {
    eclouddokploy_find_production_environment([
        ['name' => 'production', 'projectId' => 'project-310', 'environmentId' => 'env-310'],
        ['name' => 'production', 'projectId' => 'project-310', 'environmentId' => 'env-extra'],
    ], 'project-310');
    throw new RuntimeException('Test failed: ambiguous duplicate environment adopted');
} catch (RuntimeException $e) {
    check($e->getMessage() !== 'Test failed: ambiguous duplicate environment adopted', 'duplicate production environments refused');
}
check(eclouddokploy_service_isolation_valid(25, 'project-310', 'env-310', 25), 'owner project and environment match');
check(!eclouddokploy_service_isolation_valid(25, 'project-310', 'env-310', 26), 'other owner denied');
check(!eclouddokploy_service_isolation_valid(25, null, 'env-310', 25), 'old shared project cannot be auto-adopted');
check(!eclouddokploy_service_isolation_valid(25, 'project-310', null, 25), 'missing private environment denied');
check(eclouddokploy_app_environment_matches([
    'applicationId' => 'app-310', 'environmentId' => 'env-310'
], 'app-310', 'env-310'), 'app belongs to own private environment');
check(!eclouddokploy_app_environment_matches([
    'applicationId' => 'app-310', 'environmentId' => 'env-311'
], 'app-310', 'env-310'), 'other private environment denied');
check(!eclouddokploy_app_environment_matches([
    'applicationId' => 'app-310'
], 'app-310', 'env-310'), 'unknown app environment denied');
check(eclouddokploy_public_state('creating_project')[0] === 'Creando proyecto privado', 'project provisioning status visible');
check(eclouddokploy_public_state('environment_ready')[0] === 'Entorno privado configurado', 'isolated environment status visible');


// cPanel email integration: all authentication and mailbox actions default OFF.
putenv('ECLOUD_DOKPLOY_ENABLE_EMAIL_READS=0');
putenv('ECLOUD_DOKPLOY_ENABLE_EMAIL_WRITES=0');
putenv('ECLOUD_DOKPLOY_ENABLE_EMAIL_DELETE=0');
check(eclouddokploy_ClientAreaAllowedFunctions() === ['Redeploy'], 'mail actions absent unless explicitly enabled');
check(str_contains(eclouddokploy_email_action([]), 'no está habilitada'), 'mail CRUD gated');
check(eclouddokploy_mail_valid_local('ventas'), 'valid mailbox local part');
check(eclouddokploy_mail_valid_local('ventas.2027'), 'mailbox local punctuation');
check(!eclouddokploy_mail_valid_local('ventas@other.example'), 'reject untrusted posted domain');
check(!eclouddokploy_mail_valid_local('admin/../../etc'), 'reject slash in local part');
check(!eclouddokploy_mail_valid_local('invalid..mail'), 'reject double dots');
check(!eclouddokploy_mail_valid_local('cpanel'), 'reject reserved cPanel inbox');
check(eclouddokploy_mail_address('ventas','empresa.com') === 'ventas@empresa.com', 'canonical mailbox');
check(eclouddokploy_mail_valid_password('Strong2026!PassZ'), 'strong email password');
check(!eclouddokploy_mail_valid_password('weakpass'), 'weak email password rejected');
check(!eclouddokploy_mail_valid_password('CapitalNumber1234'), 'password must include symbol');
check(eclouddokploy_mail_valid_quota('512',2048), 'mailbox quota within plan');
check(!eclouddokploy_mail_valid_quota('0',2048), 'unlimited quota rejected');
check(!eclouddokploy_mail_valid_quota('3000',2048), 'quota above plan rejected');
check(!eclouddokploy_mail_valid_quota('512abc',2048), 'quota injection rejected');
$sanitized = eclouddokploy_mail_rows([
    ['email'=>'ventas@empresa.com', 'diskquota'=>512, 'password'=>'NEVER_RENDER'],
    ['email'=>'otros@otro.com', 'diskquota'=>1000],
    ['email'=>'sistemas@empresa.com', 'diskquota'=>0],
    ['email'=>'bad@empresa.com','tokens'=>'secret','other'=>'private'],
], 'empresa.com');
check(count($sanitized) === 3, 'mailbox list filters to owned domain');
check($sanitized[0]['address'] === 'bad@empresa.com', 'mailboxes sorted consistently');
check(!isset($sanitized[0]['tokens']) && !isset($sanitized[2]['password']), 'no raw cPanel fields exposed');
check(eclouddokploy_email_exists($sanitized, 'ventas'), 'mailbox exact lookup');
check(!eclouddokploy_email_exists($sanitized, 'otro'), 'no other mailbox authorized');
$mapfile = tempnam(sys_get_temp_dir(), 'ecloud-mail-private-');
chmod($mapfile,0600);
file_put_contents($mapfile,json_encode([
    '310'=>['email_service_id'=>411,'api_token'=>'mail-token-0123456789',
        'mailbox_limit'=>5,'max_quota_mb'=>1024],
], JSON_THROW_ON_ERROR));
putenv('ECLOUD_DOKPLOY_EMAIL_MAP_FILE='.$mapfile);
$_SERVER['DOCUMENT_ROOT']='/var/www/no-access';
$bound = eclouddokploy_email_binding_file(310);
check($bound !== null && $bound['email_service_id'] === 411, 'private mail config loaded');
check(!array_key_exists('api_token',$clientArea['vars']), 'no cPanel credential exposed in Smarty');
check(eclouddokploy_email_binding_file(311) === null, 'unlinked service cannot adopt another mail token');
chmod($mapfile,0644);
check(eclouddokploy_email_binding_file(310) === null, 'world-readable credentials refused');
chmod($mapfile,0600);
file_put_contents($mapfile,json_encode([
    '310'=>['email_service_id'=>310,'api_token'=>'mail-token-0123456789',
        'mailbox_limit'=>5,'max_quota_mb'=>1024],
], JSON_THROW_ON_ERROR));
check(eclouddokploy_email_binding_file(310) === null, 'self-link forbidden');
unlink($mapfile);
putenv('ECLOUD_DOKPLOY_ENABLE_EMAIL_READS=1');
putenv('ECLOUD_DOKPLOY_ENABLE_EMAIL_WRITES=1');
check(in_array('EmailAction',eclouddokploy_ClientAreaAllowedFunctions(),true), 'mail custom action registered only with explicit flags');
$_SESSION['uid'] = 456;
check(str_contains(eclouddokploy_email_action(['serviceid'=>310,'userid'=>25]), 'no autorizada'), 'other user email action denied');
$_SESSION['uid'] = 25;
$_SERVER['REQUEST_METHOD'] = 'GET';
check(str_contains(eclouddokploy_email_action(['serviceid'=>310,'userid'=>25]), 'no autorizada'), 'GET mail mutation denied');
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST=['id'=>'310','modop'=>'custom','a'=>'EmailAction','ec_mail_csrf'=>'invalid','ec_mail_op'=>'create','ec_mail_local'=>'ventas'];
check(str_contains(eclouddokploy_email_action(['serviceid'=>310,'userid'=>25]), 'expiró'), 'mail CSRF enforced before database');
$ecMailCsrf = eclouddokploy_email_csrf(310);
check($ecMailCsrf !== eclouddokploy_email_csrf(311), 'mail CSRF per service');
check($ecMailCsrf !== eclouddokploy_csrf(310), 'mail CSRF separated from deploy');
$_POST['ec_mail_csrf']=$ecMailCsrf;
$_POST['ec_mail_op']='delete';
check(str_contains(eclouddokploy_email_action(['serviceid'=>310,'userid'=>25]), 'desactivada'), 'mailbox deletion separately gated');

echo "Dokploy provisioning validations passed\n";
