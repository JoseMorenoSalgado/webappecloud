<?php
declare(strict_types=1);

/**
 * Elearning Cloud WebAppECloud provisioning module for WHMCS.
 * PILOT: intentionally gated, and does not mark services active before health verification.
 *
 * Installation path: WHMCS_ROOT/modules/servers/eclouddokploy/eclouddokploy.php
 */
if (!defined('WHMCS')) {
    exit('Direct access not allowed');
}

require_once __DIR__ . '/lib/DokployClient.php';
require_once __DIR__ . '/lib/ClientOperations.php';
require_once __DIR__ . '/lib/ServerRouting.php';

use WHMCS\Database\Capsule;

function eclouddokploy_MetaData(): array
{
    return ['DisplayName' => 'Elearning Cloud — Dokploy (piloto)', 'APIVersion' => '1.1', 'RequiresServer' => true, 'DefaultSSLPort' => '443'];
}

function eclouddokploy_ConfigOptions(): array
{
    return [
        'Dokploy Environment ID' => [
            'Type' => 'text', 'Size' => '48',
            'Description' => 'Con varios VPS independientes, introduce el Environment ID como Username en cada servidor WHMCS.',
        ],
        'Git Repository HTTPS' => [
            'Type' => 'text', 'Size' => '80',
            'Default' => 'https://github.com/JoseMorenoSalgado/webappecloud.git',
            'Description' => 'Repositorio público HTTPS; sin credenciales.',
        ],
        'Git Branch' => [
            'Type' => 'text', 'Size' => '30', 'Default' => 'main',
        ],
        'App Port' => [
            'Type' => 'text', 'Size' => '8', 'Default' => '3000',
        ],
        'Database Mode' => [
            'Type' => 'dropdown',
            'Options' => 'mariadb,mysql,postgres,external,none',
            'Default' => 'mariadb',
            'Description' => 'MariaDB, MySQL, PostgreSQL, existente o sin base de datos.',
        ],
    ];
}

function eclouddokploy_TestConnection(array $params): array
{
    try {
        $hostname = strtolower(trim((string) ($params['serverhostname'] ?? '')));
        $key = trim((string) ($params['serverpassword'] ?? ''));
        if (!eclouddokploy_valid_server_hostname($hostname)) {
            return ['success' => false, 'error' => 'Hostname de Dokploy inválido.'];
        }
        $client = new ECloudDokployClient('https://' . $hostname, $key);
        $projects = $client->get('project.all', []);
        if (!array_is_list($projects)) {
            return ['success' => false, 'error' => 'Respuesta de Dokploy inválida.'];
        }
        return ['success' => true, 'error' => ''];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => 'Conexión HTTPS o token Dokploy no válido.'];
    }
}

/** Expose no public client provisioning actions until health verification is integrated. */
function eclouddokploy_CreateAccount(array $params): string
{
    if (getenv('ECLOUD_DOKPLOY_ENABLE_PROVISIONING') !== '1') {
        return 'El despliegue está desactivado en WHMCS. Habilitar solo en staging.';
    }
    try {
        $serviceId = (int) ($params['serviceid'] ?? 0);
        $environmentId = trim((string) ($params['configoption1'] ?? ''));
        $gitUrl = trim((string) ($params['configoption2'] ?? ''));
        $branch = trim((string) ($params['configoption3'] ?? ''));
        $port = filter_var($params['configoption4'] ?? 3000, FILTER_VALIDATE_INT);
        $domain = strtolower(rtrim(trim((string) ($params['domain'] ?? '')), '.'));
        $dbMode = trim((string) ($params['configoption5'] ?? 'mariadb'));
        if (!in_array($dbMode, ['mariadb', 'mysql', 'postgres', 'external', 'none'], true)) {
            return 'Modo de base de datos no admitido.';
        }
        if ($serviceId <= 0) {
            return 'Configuración incompleta: Service ID incorrecto.';
        }
        if (!eclouddokploy_valid_git($gitUrl, $branch)) {
            return 'Repositorio Git inválido: solo HTTPS público de GitHub, rama válida.';
        }
        if ($port === false || $port < 1024 || $port > 65535 || !eclouddokploy_valid_domain($domain)) {
            return 'Dominio o puerto de aplicación inválido.';
        }

        $secret = trim((string) getenv('ECLOUD_DOKPLOY_PASSWORD_SEED'));
        if (!preg_match('/^[a-f0-9]{64}$/Di', $secret)) {
            return 'Configurar ECLOUD_DOKPLOY_PASSWORD_SEED como 32 bytes hexadecimales.';
        }
        // WHMCS assigns a specific server from the product's server group.
        // Its immutable route maps to the Dokploy instance + deployment node.
        $route = eclouddokploy_resolve_route($params);
        $environmentId = $route['environment_id'];
        $api = new ECloudDokployClient($route['url'], $route['api_key']);
        eclouddokploy_create_table();

        $lockName = 'ecloud-dokploy-' . $serviceId;
        $connection = Capsule::connection();
        $locked = $connection->selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lockName]);
        if (!$locked || (int) $locked->acquired !== 1) {
            return 'El aprovisionamiento de este cliente ya está en ejecución.';
        }
        try {
            if ($route['whmcs_server_id'] > 0 &&
                !eclouddokploy_server_has_capacity($route['whmcs_server_id'], $serviceId)) {
                return 'Este servidor alcanzó su límite de cuentas o su asignación no es válida. Verificar el grupo de servidores WHMCS; no se instalaron recursos adicionales.';
            }
            return eclouddokploy_create_locked(
                $api, $serviceId, $environmentId, $gitUrl, $branch, (int) $port, $domain, $secret, $dbMode,
                $route['remote_server_id'], $route['fingerprint']
            );
        } finally {
            $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
        }
    } catch (Throwable $e) {
        // Do not echo remote bodies, API tokens, passwords, or DATABASE_URL.
        return 'No se completó el despliegue. Revisar configuración y estado en Dokploy.';
    }
}

function eclouddokploy_valid_domain(string $host): bool
{
    if (strlen($host) > 253 || strpos($host, '.') === false) return false;
    foreach (explode('.', $host) as $label) {
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $label)) return false;
    }
    return true;
}

function eclouddokploy_valid_git(string $url, string $branch): bool
{
    $parts = parse_url($url);
    if (!$parts || ($parts['scheme'] ?? '') !== 'https' ||
        strtolower((string) ($parts['host'] ?? '')) !== 'github.com' ||
        isset($parts['port']) || isset($parts['user']) || isset($parts['pass']) ||
        isset($parts['fragment']) || isset($parts['query'])) return false;
    if (!preg_match('~^/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+(?:[.]git)?$~D', (string) ($parts['path'] ?? ''))) return false;
    return (bool) preg_match('~^[A-Za-z0-9._/-]{1,100}$~D', $branch);
}

/**
 * Existing DB secrets must be supplied from a root-managed JSON file outside
 * the webroot. Do not store SQL passwords in WHMCS configurable options.
 * Format: {"123":"mysql://user:password@host:3306/database"}.
 */
function eclouddokploy_external_database_url(int $serviceId): string
{
    $path = (string) getenv('ECLOUD_DOKPLOY_EXTERNAL_DB_MAP_FILE');
    if ($path === '' || $path[0] !== '/' || is_link($path) || !is_file($path) || !is_readable($path)) {
        throw new RuntimeException('Private external database map is unavailable.');
    }
    if (filesize($path) > 131072) {
        throw new RuntimeException('External database map too large.');
    }
    $map = json_decode((string) file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
    $url = is_array($map) ? ($map[(string) $serviceId] ?? null) : null;
    if (!is_string($url) || strpos($url, "\n") !== false || strpos($url, "\r") !== false || strlen($url) > 4096) {
        throw new RuntimeException('Database URL for this service is not configured.');
    }
    $parts = parse_url($url);
    if (!$parts || !in_array($parts['scheme'] ?? '', ['mysql', 'postgresql', 'postgres'], true)
        || empty($parts['host']) || empty($parts['user']) || empty($parts['pass'])
        || empty($parts['path']) || $parts['path'] === '/'
        || isset($parts['fragment'])) {
        throw new RuntimeException('Unsupported external database URL.');
    }
    return $url;
}

function eclouddokploy_create_table(): void
{
    if (Capsule::schema()->hasTable('mod_ecloud_dokploy_services')) {
        if (!Capsule::schema()->hasColumn('mod_ecloud_dokploy_services', 'route_fingerprint')) {
            Capsule::schema()->table('mod_ecloud_dokploy_services', function ($table) {
                $table->string('route_fingerprint', 64)->nullable();
            });
        }
        return;
    }
    Capsule::schema()->create('mod_ecloud_dokploy_services', function ($table) {
        $table->unsignedInteger('service_id')->primary();
        $table->string('state', 48)->default('new');
        $table->string('db_mode', 16)->default('mariadb');
        $table->string('mariadb_id', 128)->nullable();
        $table->string('application_id', 128)->nullable();
        $table->string('domain_id', 128)->nullable();
        $table->string('route_fingerprint', 64)->nullable();
        $table->timestamps();
    });
}

/** Updates are persisted after each stage. Never auto-retry ambiguous create operations. */
function eclouddokploy_set_state(int $serviceId, string $state, array $extra = []): void
{
    Capsule::table('mod_ecloud_dokploy_services')->where('service_id', $serviceId)
        ->update(array_merge($extra, ['state' => $state, 'updated_at' => date('Y-m-d H:i:s')]));
}

function eclouddokploy_password(string $seed, string $purpose, int $serviceId): string
{
    // No plaintext database secrets in the WHMCS database or git. Back up this master seed securely.
    return hash_hmac('sha256', 'ecloud-dokploy-v1/' . $purpose . '/' . $serviceId, hex2bin($seed));
}

function eclouddokploy_required_id(array $value, string $key): string
{
    $id = $value[$key] ?? null;
    if (!is_string($id) || $id === '' || strlen($id) > 128) {
        throw new RuntimeException('Dokploy did not return a ' . $key . '. Manual reconciliation required.');
    }
    return $id;
}


/**
 * Pure payload builders are unit-tested against Dokploy's documented API fields.
 * They do not call the remote server.
 */
function eclouddokploy_database_payload(
    string $dbMode, string $envId, string $dbApp, string $dbName,
    string $dbUser, string $password, string $rootPassword, string $remoteServerId = ''
): array {
    $images = [
        'mariadb' => 'mariadb:11.4',
        'mysql' => 'mysql:8.4',
        'postgres' => 'postgres:17',
    ];
    if (!isset($images[$dbMode])) {
        throw new InvalidArgumentException('Unsupported SQL engine.');
    }
    $payload = [
        'name' => $dbApp,
        'appName' => $dbApp,
        'environmentId' => $envId,
        'databaseName' => $dbName,
        'databaseUser' => $dbUser,
        'databasePassword' => $password,
        'dockerImage' => $images[$dbMode],
    ];
    if ($dbMode !== 'postgres') $payload['databaseRootPassword'] = $rootPassword;
    if ($remoteServerId !== '') $payload['serverId'] = $remoteServerId;
    return $payload;
}

function eclouddokploy_dockerfile_payload(string $applicationId): array
{
    return [
        'applicationId' => $applicationId,
        'buildType' => 'dockerfile',
        'dockerfile' => 'Dockerfile',
        'dockerContextPath' => '/',
        // Dokploy's schema requires these fields, even for Dockerfile builds.
        'dockerBuildStage' => null,
        'herokuVersion' => null,
        'railpackVersion' => null,
    ];
}

function eclouddokploy_create_locked(
    ECloudDokployClient $api, int $id, string $envId, string $gitUrl,
    string $branch, int $port, string $domain, string $seed, string $dbMode,
    string $remoteServerId, string $routeFingerprint
): string {
    $table = Capsule::table('mod_ecloud_dokploy_services');
    $stateRow = $table->where('service_id', $id)->first();
    if (!$stateRow) {
        $table->insert([
            'service_id' => $id, 'state' => 'new', 'db_mode' => $dbMode,
            'route_fingerprint' => $routeFingerprint,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $stateRow = $table->where('service_id', $id)->first();
    }
    // If a service already exists, never silently move it between Dokploy
    // instances, remote deployment nodes or environments.
    if (!is_string($stateRow->route_fingerprint) ||
        !hash_equals($routeFingerprint, $stateRow->route_fingerprint)) {
        return 'La ruta del servidor ha cambiado o no está vinculada. Requiere conciliación administrativa antes de continuar.';
    }
    if ((string) $stateRow->db_mode !== $dbMode) {
        return 'El motor de base de datos de este servicio ya fue establecido; no puede cambiarse durante el aprovisionamiento.';
    }
    $state = (string) $stateRow->state;
    $unsafeStates = [
        'creating_db', 'deploying_db', 'creating_app',
        'creating_domain', 'requesting_deploy',
    ];
    if (in_array($state, $unsafeStates, true)) {
        return 'Operación posiblemente realizada. Revisar IDs de Dokploy antes de reintentar; no se duplicaron recursos.';
    }
    if ($state === 'deployment_requested') {
        return 'Despliegue solicitado. Verificar /api/health y DNS antes de activar el servicio en WHMCS.';
    }

    $name = 'ecw-' . $id;
    $dbApp = 'ecw-db-' . $id;
    $dbName = 'ecw_' . $id;
    $dbUser = 'ecw_' . $id;
    $pass = eclouddokploy_password($seed, 'db-user', $id);
    $rootPass = eclouddokploy_password($seed, 'db-root', $id);
    $dbIdKey = ['mariadb' => 'mariadbId', 'mysql' => 'mysqlId', 'postgres' => 'postgresId'];
    $managed = isset($dbIdKey[$dbMode]);
    if ($state === 'new') {
        if (!$managed) {
            // External DB and DB-free services do not create any database in Dokploy.
            if ($dbMode === 'external') eclouddokploy_external_database_url($id);
            eclouddokploy_set_state($id, 'db_deployed');
            $state = 'db_deployed';
        } else {
            $payload = eclouddokploy_database_payload(
                $dbMode, $envId, $dbApp, $dbName, $dbUser, $pass, $rootPass, $remoteServerId
            );
            eclouddokploy_set_state($id, 'creating_db');
            $db = $api->post($dbMode . '.create', $payload);
            $dbId = eclouddokploy_required_id($db, $dbIdKey[$dbMode]);
            eclouddokploy_set_state($id, 'db_created', ['mariadb_id' => $dbId]);
            $state = 'db_created';
        }
    }
    $stateRow = $table->where('service_id', $id)->first();
    $dbId = (string) ($stateRow->mariadb_id ?? '');
    if ($managed && $dbId === '') return 'Falta ID de base de datos. Requiere conciliación manual.';

    if ($state === 'db_created' && $managed) {
        eclouddokploy_set_state($id, 'deploying_db');
        $api->post($dbMode . '.deploy', [$dbIdKey[$dbMode] => $dbId]);
        eclouddokploy_set_state($id, 'db_deployed');
        $state = 'db_deployed';
    }
    if ($state === 'db_deployed') {
        eclouddokploy_set_state($id, 'creating_app');
        $app = $api->post('application.create',
            eclouddokploy_application_create_payload($envId, $name, $remoteServerId)
        );
        $appId = eclouddokploy_required_id($app, 'applicationId');
        eclouddokploy_set_state($id, 'app_created', ['application_id' => $appId]);
        $state = 'app_created';
    }

    $stateRow = $table->where('service_id', $id)->first();
    $appId = (string) ($stateRow->application_id ?? '');
    if ($appId === '') return 'No hay ID de aplicación. Requiere conciliación manual.';

    if ($state === 'app_created' || $state === 'configuring_app') {
        eclouddokploy_set_state($id, 'configuring_app');
        $api->post('application.saveGitProvider', [
            'applicationId' => $appId,
            'customGitUrl' => $gitUrl,
            'customGitBranch' => $branch,
            'customGitBuildPath' => '/',
            'customGitSSHKeyId' => null,
            'watchPaths' => [],
            'enableSubmodules' => false,
        ]);
        $api->post('application.saveBuildType', eclouddokploy_dockerfile_payload($appId));
        $envLines = ['NODE_ENV=production', 'PORT=' . $port];
        if ($managed) {
            $details = $api->get($dbMode . '.one', [$dbIdKey[$dbMode] => $dbId]);
            $internalHost = (string) ($details['appName'] ?? '');
            if ($internalHost === '' || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/D', $internalHost)) {
                throw new RuntimeException('Cannot determine private database hostname; configure manually.');
            }
            $protocol = $dbMode === 'postgres' ? 'postgresql' : 'mysql';
            $dbPort = $dbMode === 'postgres' ? 5432 : 3306;
            $databaseUrl = $protocol . '://' . rawurlencode($dbUser) . ':' . rawurlencode($pass)
                . '@' . $internalHost . ':' . $dbPort . '/' . rawurlencode($dbName);
            $envLines[] = 'DATABASE_URL=' . $databaseUrl;
        } elseif ($dbMode === 'external') {
            $envLines[] = 'DATABASE_URL=' . eclouddokploy_external_database_url($id);
        }
        $api->post('application.saveEnvironment', [
            'applicationId' => $appId,
            'env' => implode("\n", $envLines) . "\n",
            'buildArgs' => '', 'buildSecrets' => '', 'createEnvFile' => false,
        ]);
        eclouddokploy_set_state($id, 'app_configured');
        $state = 'app_configured';
    }

    if ($state === 'app_configured') {
        eclouddokploy_set_state($id, 'creating_domain');
        $result = $api->post('domain.create', [
            'host' => $domain, 'applicationId' => $appId, 'port' => $port,
            'https' => true, 'certificateType' => 'letsencrypt',
        ]);
        $domainId = isset($result['domainId']) && is_string($result['domainId']) ? $result['domainId'] : null;
        eclouddokploy_set_state($id, 'domain_created', ['domain_id' => $domainId]);
        $state = 'domain_created';
    }

    if ($state === 'domain_created') {
        eclouddokploy_set_state($id, 'requesting_deploy');
        $api->post('application.deploy', ['applicationId' => $appId]);
        eclouddokploy_set_state($id, 'deployment_requested');
        return 'Despliegue solicitado. Revisar registros DNS, instalación y /api/health antes de activar WHMCS.';
    }
    return 'Estado de aprovisionamiento no reconocido. Revisar el registro del servicio.';
}


/**
 * Public-facing service status: labels describe provisioning progress only.
 * A deployment request does NOT prove that the website is healthy or online.
 */
function eclouddokploy_public_state(string $state): array
{
    $map = [
        'new' => ['Pendiente de instalación', 'Estamos preparando los recursos de tu aplicación.'],
        'creating_db' => ['Configurando base de datos', 'Se está solicitando la creación de la base de datos.'],
        'db_created' => ['Base de datos registrada', 'La base de datos está pendiente de inicio.'],
        'deploying_db' => ['Iniciando base de datos', 'El servidor está preparando el motor de base de datos.'],
        'db_deployed' => ['Preparando aplicación', 'La base de datos fue solicitada; falta configurar la aplicación.'],
        'creating_app' => ['Creando aplicación', 'Se está solicitando el entorno de ejecución.'],
        'app_created' => ['Aplicación registrada', 'Falta completar la configuración.'],
        'configuring_app' => ['Configurando aplicación', 'Se están configurando Git y el entorno.'],
        'app_configured' => ['Aplicación configurada', 'El dominio aún no está preparado.'],
        'creating_domain' => ['Configurando dominio', 'Se está preparando el dominio de la aplicación.'],
        'domain_created' => ['Dominio configurado', 'El despliegue aún no ha sido solicitado.'],
        'requesting_deploy' => ['Solicitando despliegue', 'La plataforma está recibiendo la solicitud de despliegue.'],
        'deployment_requested' => ['Despliegue solicitado', 'La aplicación está pendiente de verificar su instalación y disponibilidad.'],
    ];
    return $map[$state] ?? ['Revisión técnica', 'Nuestro equipo debe revisar el estado de instalación.'];
}

/**
 * Rendered natively within this Dokploy product's WHMCS service detail page.
 * Lagom keeps its own service navigation, billing and client authentication.
 * No Dokploy secrets or destructive/redeploy actions are sent to the browser.
 */
function eclouddokploy_ClientArea(array $params): array
{
    $serviceId = (int) ($params['serviceid'] ?? 0);
    $ownerId = (int) ($params['userid'] ?? 0);
    $clientId = (int) ($_SESSION['uid'] ?? 0);
    $domain = strtolower(rtrim(trim((string) ($params['domain'] ?? '')), '.'));
    $gitUrl = trim((string) ($params['configoption2'] ?? ''));
    $branch = trim((string) ($params['configoption3'] ?? ''));
    $dbMode = trim((string) ($params['configoption5'] ?? ''));
    $public = eclouddokploy_public_state('unverified');
    $view = [
        'ecDokployAuthorized' => false,
        'ecDokployServiceId' => 0,
        'ecDokployDomain' => '',
        'ecDokploySiteUrl' => '',
        'ecDokployGitUrl' => '',
        'ecDokployBranch' => '',
        'ecDokployDbMode' => '',
        'ecDokployState' => $public[0],
        'ecDokployDescription' => $public[1],
        'ecDokployProvisioned' => false,
        'ecDokployCanRedeploy' => false,
        'ecDokployCsrf' => '',
        'ecDokployLiveState' => '',
        'ecDokployLiveDescription' => '',
        'ecDokployLiveAvailable' => false,
        'ecDokployHistory' => [],
        'ecDokployLiveNotice' => '',
    ];
    if ($serviceId < 1 || $clientId < 1 || $ownerId !== $clientId) {
        return ['templatefile' => 'templates/clientarea', 'vars' => $view];
    }
    try {
        // Additional ownership/service binding in the WHMCS database.
        $hosting = Capsule::table('tblhosting')
            ->where('id', $serviceId)
            ->where('userid', $clientId)
            ->first(['id', 'domain', 'packageid', 'domainstatus', 'server']);
        if (!$hosting || (int) $hosting->packageid !== (int) ($params['pid'] ?? 0)) {
            return ['templatefile' => 'templates/clientarea', 'vars' => $view];
        }
        if (strtolower(rtrim((string) $hosting->domain, '.')) !== $domain ||
            (int) $hosting->server !== (int) ($params['serverid'] ?? 0)) {
            return ['templatefile' => 'templates/clientarea', 'vars' => $view];
        }
        $route = eclouddokploy_resolve_route($params);
        $view['ecDokployAuthorized'] = true;
        $view['ecDokployServiceId'] = $serviceId;
        $active = (string) $hosting->domainstatus === 'Active';
        if ($active && getenv('ECLOUD_DOKPLOY_ENABLE_CLIENT_REDEPLOY') === '1') {
            $view['ecDokployCsrf'] = eclouddokploy_csrf($serviceId);
        }
        if (eclouddokploy_valid_domain($domain)) {
            $view['ecDokployDomain'] = $domain;
            $view['ecDokploySiteUrl'] = 'https://' . $domain;
        }
        if (eclouddokploy_valid_git($gitUrl, $branch)) {
            $view['ecDokployGitUrl'] = $gitUrl;
            $view['ecDokployBranch'] = $branch;
        }
        $modes = [
            'mariadb' => 'MariaDB', 'mysql' => 'MySQL',
            'postgres' => 'PostgreSQL', 'external' => 'Base de datos externa',
            'none' => 'Sin base de datos',
        ];
        $view['ecDokployDbMode'] = $modes[$dbMode] ?? 'Sin confirmar';

        if (!Capsule::schema()->hasTable('mod_ecloud_dokploy_services')) {
            $public = ['Sin instalación registrada', 'Este servicio aún no tiene un despliegue registrado en Dokploy.'];
        } else {
            $record = Capsule::table('mod_ecloud_dokploy_services')
                ->where('service_id', $serviceId)
                ->first(['state', 'application_id', 'route_fingerprint']);
            if ($record) {
                if (!eclouddokploy_route_matches($record->route_fingerprint, $route)) {
                    throw new RuntimeException('Application host assignment mismatch.');
                }
                $public = eclouddokploy_public_state((string) $record->state);
                $view['ecDokployProvisioned'] =
                    (string) $record->state === 'deployment_requested'
                    && !empty($record->application_id);
                if ($active && $view['ecDokployProvisioned']) {
                    $view['ecDokployCanRedeploy'] =
                        getenv('ECLOUD_DOKPLOY_ENABLE_CLIENT_REDEPLOY') === '1';
                    if (getenv('ECLOUD_DOKPLOY_ENABLE_CLIENT_READS') === '1') {
                        try {
                            $api = new ECloudDokployClient($route['url'], $route['api_key']);
                            $live = eclouddokploy_live_overview($api, (string) $record->application_id);
                            $view['ecDokployLiveState'] = $live['state'];
                            $view['ecDokployLiveDescription'] = $live['description'];
                            $view['ecDokployHistory'] = $live['history'];
                            $view['ecDokployLiveAvailable'] = true;
                        } catch (Throwable $e) {
                            $view['ecDokployLiveNotice'] = 'No se pudieron consultar los datos recientes de Dokploy.';
                        }
                    }
                }
            } else {
                $public = ['Sin instalación registrada', 'Este servicio aún no tiene un despliegue registrado en Dokploy.'];
            }
        }
        if ((string) $hosting->domainstatus !== 'Active' &&
            (string) $hosting->domainstatus !== 'Pending') {
            $public = ['Servicio ' . (string) $hosting->domainstatus, 'La gestión de este servicio está limitada por su estado en WHMCS.'];
        }
        $view['ecDokployState'] = $public[0];
        $view['ecDokployDescription'] = $public[1];
    } catch (Throwable $e) {
        // Fail closed, avoiding application/database secrets in output or logs.
        $view['ecDokployAuthorized'] = false;
    }
    return ['templatefile' => 'templates/clientarea', 'vars' => $view];
}


/**
 * Exposes a client-only WHMCS custom action without adding buttons to other
 * products. The form is rendered inside the Dokploy service's own Lagom panel.
 */
function eclouddokploy_ClientAreaAllowedFunctions(): array
{
    return getenv('ECLOUD_DOKPLOY_ENABLE_CLIENT_REDEPLOY') === '1'
        ? ['Redeploy']
        : [];
}

function eclouddokploy_Redeploy(array $params): string
{
    return eclouddokploy_redeploy_request($params);
}
