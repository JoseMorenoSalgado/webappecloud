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
require_once __DIR__ . '/lib/ProjectIsolation.php';
require_once __DIR__ . '/lib/CpanelMail.php';
require_once __DIR__ . '/lib/EmailOperations.php';
require_once __DIR__ . '/lib/SelfService.php';
require_once __DIR__ . '/lib/AutomaticSetup.php';
require_once __DIR__ . '/lib/AutoMail.php';
require_once __DIR__ . '/lib/OrderInstall.php';

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
            'Description' => 'Obsoleto. Ahora cada servicio crea su propio proyecto y entorno en Dokploy; puede quedar vacío.',
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
        'Instalación automática' => [
            'Type' => 'dropdown',
            'Options' => 'guiado,pedido,legado',
            'Default' => 'guiado',
            'Description' => 'guiado: reserva automática y cliente publica desde WHMCS. pedido: también despliega al primer pago si el pedido incluye el campo Repositorio GitHub. legado: modo anterior.',
        ],
    ];
}

/**
 * Explain the cause of WHMCS Test Connection without leaking any API response,
 * token, hostname, or network-internal detail.
 */
function eclouddokploy_safe_connection_error(Throwable $e): string
{
    $message = $e->getMessage();
    if (str_contains($message, 'Dokploy API key is missing')) {
        return 'Falta la API key de Dokploy en el campo Contraseña de WHMCS.';
    }
    if (preg_match('/^Dokploy read failed: project[.]all [(]HTTP ([0-9]{3})[)][.]$/D', $message, $m)) {
        switch ((int) $m[1]) {
            case 401:
                return 'HTTP 401: Dokploy rechazó la API key. Genera un token nuevo en Settings > Profile > API/CLI.';
            case 403:
                return 'HTTP 403: la API key no tiene permisos para consultar proyectos.';
            case 404:
                return 'HTTP 404: el dominio responde, pero /api/project.all no existe en ese destino. Verifica dominio y proxy.';
            case 429:
                return 'HTTP 429: demasiadas solicitudes; vuelve a probar más tarde.';
            case 502:
            case 503:
            case 504:
                return 'Dokploy no responde detrás del proxy HTTPS (HTTP ' . (int) $m[1] . ').';
            default:
                return 'Dokploy devolvió HTTP ' . (int) $m[1] . '. Revisa la API y el proxy del servidor.';
        }
    }
    if (preg_match('/^Dokploy transport failed [(]cURL ([0-9]+)[)][.]$/D', $message, $m)) {
        switch ((int) $m[1]) {
            case 6:
                return 'Error DNS: el servidor WHMCS no puede resolver el hostname de Dokploy.';
            case 7:
                return 'Error de red: el servidor WHMCS no puede conectar con Dokploy por HTTPS (443).';
            case 28:
                return 'Tiempo de espera agotado al conectar con Dokploy. Revisa firewall y red.';
            case 35:
                return 'Falló el establecimiento de la conexión TLS con Dokploy.';
            case 51:
            case 60:
                return 'Certificado HTTPS de Dokploy inválido o no confiable.';
            default:
                return 'Fallo de conexión con Dokploy (cURL ' . (int) $m[1] . ').';
        }
    }
    return 'No se pudo validar la API de Dokploy. Comprueba hostname HTTPS, token y respuesta JSON.';
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
        return ['success' => false, 'error' => eclouddokploy_safe_connection_error($e)];
    }
}

/** Expose no public client provisioning actions until health verification is integrated. */
function eclouddokploy_CreateAccount(array $params): string
{
    // Opt-in account reservation: purchase activates the hosting subscription,
    // the customer then imports THEIR code from the Lagom installation wizard.
    if (eclouddokploy_auto_enabled($params)) {
        try {
            $route = eclouddokploy_resolve_route($params);
            $reserved = eclouddokploy_setup_reserve(
                $params, $route, (string) ($params['configoption5'] ?? 'none')
            );
            if ($reserved !== 'success') return $reserved;
            // Optional full automation when the buyer entered the public GitHub
            // URL at checkout and this product opted into "pedido".
            return eclouddokploy_order_auto_install($params, $route);
        } catch (Throwable $e) {
            // Never leak server tokens or internal paths to WHMCS staff.
            return 'No se pudo preparar automáticamente el servicio. Comprueba el servidor asignado y la capacidad disponible.';
        }
    }
    if (getenv('ECLOUD_DOKPLOY_ENABLE_PROVISIONING') !== '1') {
        return 'El despliegue está desactivado en WHMCS. Habilitar solo en staging.';
    }
    try {
        $serviceId = (int) ($params['serviceid'] ?? 0);
        $clientId = (int) ($params['userid'] ?? 0);
        $gitUrl = trim((string) ($params['configoption2'] ?? ''));
        $branch = trim((string) ($params['configoption3'] ?? ''));
        $port = filter_var($params['configoption4'] ?? 3000, FILTER_VALIDATE_INT);
        $domain = strtolower(rtrim(trim((string) ($params['domain'] ?? '')), '.'));
        $dbMode = trim((string) ($params['configoption5'] ?? 'mariadb'));
        if (!in_array($dbMode, ['mariadb', 'mysql', 'postgres', 'external', 'none'], true)) {
            return 'Modo de base de datos no admitido.';
        }
        if ($serviceId <= 0 || $clientId <= 0) {
            return 'Configuración incompleta: servicio o propietario inválido.';
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
        $service = Capsule::table('tblhosting')->where('id', $serviceId)
            ->where('userid', $clientId)->first(['packageid', 'server', 'domain', 'domainstatus']);
        if (!$service || (int) $service->packageid !== (int) ($params['pid'] ?? 0)
            || (int) $service->server !== (int) $route['whmcs_server_id']
            || strtolower(rtrim((string) $service->domain, '.')) !== $domain
            || !in_array((string) $service->domainstatus, ['Pending', 'Active'], true)) {
            return 'El propietario, el producto o el servidor WHMCS no coincide. Instalación rechazada.';
        }
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
                $api, $serviceId, $clientId, $gitUrl, $branch, (int) $port, $domain, $secret, $dbMode,
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
        // Schema changes are additive; existing prototype accounts cannot be
        // adopted without explicit administrative reconciliation.
        $additions = [
            'owner_client_id' => 'unsignedInteger',
            'project_id' => 'string',
            'environment_id' => 'string',
        ];
        foreach ($additions as $column => $type) {
            if (Capsule::schema()->hasColumn('mod_ecloud_dokploy_services', $column)) continue;
            Capsule::schema()->table('mod_ecloud_dokploy_services', function ($table) use ($column, $type) {
                if ($type === 'unsignedInteger') $table->unsignedInteger($column)->nullable();
                else $table->string($column, 128)->nullable();
            });
        }
        if (!Capsule::schema()->hasColumn('mod_ecloud_dokploy_services', 'route_fingerprint')) {
            Capsule::schema()->table('mod_ecloud_dokploy_services', function ($table) {
                $table->string('route_fingerprint', 64)->nullable();
            });
        }
        return;
    }
    Capsule::schema()->create('mod_ecloud_dokploy_services', function ($table) {
        $table->unsignedInteger('service_id')->primary();
        $table->unsignedInteger('owner_client_id');
        $table->string('state', 48)->default('new');
        $table->string('db_mode', 16)->default('mariadb');
        $table->string('project_id', 128)->nullable();
        $table->string('environment_id', 128)->nullable();
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
    ECloudDokployClient $api, int $id, int $clientId, string $gitUrl,
    string $branch, int $port, string $domain, string $seed, string $dbMode,
    string $remoteServerId, string $routeFingerprint, array $setup = []
): string {
    $table = Capsule::table('mod_ecloud_dokploy_services');
    $stateRow = $table->where('service_id', $id)->first();
    if (!$stateRow) {
        $table->insert([
            'service_id' => $id, 'owner_client_id' => $clientId,
            'state' => 'new', 'db_mode' => $dbMode,
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
    if ((int) $stateRow->owner_client_id !== $clientId) {
        return 'La instalación no pertenece al cliente de WHMCS asignado.';
    }
    if ((string) $stateRow->db_mode !== $dbMode) {
        return 'El motor de base de datos de este servicio ya fue establecido; no puede cambiarse durante el aprovisionamiento.';
    }
    $state = (string) $stateRow->state;
    $unsafeStates = [
        'creating_project', 'creating_environment',
        'creating_db', 'deploying_db', 'creating_app',
        'creating_domain', 'requesting_deploy',
    ];
    if (in_array($state, $unsafeStates, true)) {
        return 'Operación posiblemente realizada. Revisar IDs de Dokploy antes de reintentar; no se duplicaron recursos.';
    }
    if ($state === 'deployment_requested') {
        return 'Despliegue solicitado. Verificar /api/health y DNS antes de activar el servicio en WHMCS.';
    }

    $projectId = (string) ($stateRow->project_id ?? '');
    $envId = (string) ($stateRow->environment_id ?? '');
    if ($state === 'new') {
        // If Dokploy accepts the create but the response disappears, the
        // creating_project state blocks retries until an admin reconciles it.
        eclouddokploy_set_state($id, 'creating_project');
        $project = $api->post(
            'project.create', eclouddokploy_project_create_payload($clientId, $id)
        );
        $projectId = eclouddokploy_required_id($project, 'projectId');
        eclouddokploy_set_state($id, 'project_created', ['project_id' => $projectId]);
        $state = 'project_created';
    }
    if ($projectId === '' || !preg_match('/^[A-Za-z0-9_-]{3,128}$/D', $projectId)) {
        return 'Falta el proyecto privado de este servicio. Se requiere conciliación administrativa.';
    }
    if ($state === 'project_created') {
        // Current Dokploy versions normally create production automatically
        // with a new project. Reuse only that environment of THIS project.
        $found = eclouddokploy_find_production_environment(
            $api->get('environment.byProjectId', ['projectId' => $projectId]),
            $projectId
        );
        if ($found === null) {
            eclouddokploy_set_state($id, 'creating_environment');
            $created = $api->post('environment.create', [
                'name' => 'production',
                'projectId' => $projectId,
            ]);
            $found = eclouddokploy_required_id($created, 'environmentId');
        }
        $envId = $found;
        eclouddokploy_set_state($id, 'environment_ready', ['environment_id' => $envId]);
        $state = 'environment_ready';
    }
    if ($envId === '' || !preg_match('/^[A-Za-z0-9_-]{3,128}$/D', $envId)) {
        return 'Falta el entorno privado de este servicio. Se requiere conciliación administrativa.';
    }

    $name = 'ecw-' . $id;
    $dbApp = 'ecw-db-' . $id;
    $dbName = 'ecw_' . $id;
    $dbUser = 'ecw_' . $id;
    $pass = eclouddokploy_password($seed, 'db-user', $id);
    $rootPass = eclouddokploy_password($seed, 'db-root', $id);
    $dbIdKey = ['mariadb' => 'mariadbId', 'mysql' => 'mysqlId', 'postgres' => 'postgresId'];
    $managed = isset($dbIdKey[$dbMode]);
    if ($state === 'environment_ready') {
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
            'customGitBuildPath' => (string) ($setup['root_path'] ?? '/'),
            'customGitSSHKeyId' => null,
            'watchPaths' => [],
            'enableSubmodules' => false,
        ]);
        $api->post('application.saveBuildType',
            $setup !== []
                ? eclouddokploy_setup_build_payload(
                    $appId, (string) $setup['preset'], (string) $setup['root_path']
                )
                : eclouddokploy_dockerfile_payload($appId)
        );
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
        if ($setup !== [] && !empty($setup['env'])) {
            $envLines = array_merge($envLines, eclouddokploy_setup_env_lines($setup['env']));
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
        'awaiting_setup' => ['Preparada para instalar', 'Importa tu repositorio público de GitHub en el asistente de instalación.'],
        'creating_project' => ['Creando proyecto privado', 'Se está reservando un proyecto exclusivo de este servicio.'],
        'project_created' => ['Proyecto privado registrado', 'Estamos preparando el entorno exclusivo del servicio.'],
        'creating_environment' => ['Creando entorno privado', 'Se está creando un entorno para el proyecto del cliente.'],
        'environment_ready' => ['Entorno privado configurado', 'Preparando los recursos de la aplicación.'],
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
        'ecDokployIsolationLabel' => '',
        'ecSetupEnabled' => false,
        'ecSetupCanInstall' => false,
        'ecSetupToken' => '',
        'ecSetupConfigured' => false,
        'ecSetupGitUrl' => '',
        'ecSetupBranch' => '',
        'ecSetupPreset' => '',
        'ecSetupRoot' => '',
        'ecSetupPort' => 3000,
        'ecSetupDomain' => '',
        'ecSetupEnvNames' => [],
        'ecSetupDbMode' => '',
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
        'ecMailLinked' => false,
        'ecMailAutoLink' => '',
        'ecMailAutoAccount' => '',
        'ecMailAvailable' => false,
        'ecMailCanWrite' => false,
        'ecMailCanDelete' => false,
        'ecMailDomain' => '',
        'ecMailWebmailUrl' => '',
        'ecMailAccounts' => [],
        'ecMailAccountCount' => 0,
        'ecMailMailboxLimit' => 0,
        'ecMailMaxQuotaMb' => 0,
        'ecMailToken' => '',
        'ecMailNotice' => 'Para administrar los buzones, activa y vincula un servicio de correo cPanel a este dominio.',
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
        $view['ecSetupEnabled'] = eclouddokploy_auto_enabled($params);
        $view['ecSetupDomain'] = $domain;
        $view['ecSetupDbMode'] = $view['ecDokployDbMode'];

        // First visit after an earlier module installation? Prepare the
        // account automatically without creating ANY Dokploy resources.
        if ($view['ecSetupEnabled'] && $active) {
            try {
                eclouddokploy_auto_reserve_existing($params, $clientId, $domain);
            } catch (Throwable $e) {
                // Display an informative pending state instead of leaking errors.
                $view['ecDokployLiveNotice'] =
                    'No se pudo preparar automáticamente la aplicación. Contacta con soporte.';
            }
        }

        if (!Capsule::schema()->hasTable('mod_ecloud_dokploy_services')) {
            $public = ['Sin instalación registrada', 'Este servicio aún no tiene un despliegue registrado en Dokploy.'];
        } else {
            $record = Capsule::table('mod_ecloud_dokploy_services')
                ->where('service_id', $serviceId)
                ->first(['state', 'application_id', 'route_fingerprint',
                    'owner_client_id', 'project_id', 'environment_id']);
            if ($record) {
                if (!eclouddokploy_route_matches($record->route_fingerprint, $route)
                    || (int) $record->owner_client_id !== $clientId) {
                    throw new RuntimeException('WHMCS service owner/host mismatch.');
                }
                $isolated = eclouddokploy_service_isolation_valid(
                    $record->owner_client_id, $record->project_id,
                    $record->environment_id, $clientId
                );
                // During project creation the identifiers are not known yet.
                // Progress can be displayed but no application action is enabled.
                $early = ['awaiting_setup', 'new', 'creating_project', 'project_created', 'creating_environment'];
                if (!$isolated && !in_array((string) $record->state, $early, true)) {
                    throw new RuntimeException('Legacy/shared project requires manual reconciliation.');
                }
                $public = eclouddokploy_public_state((string) $record->state);
                if ($view['ecSetupEnabled']
                    && in_array((string) $record->state, ['awaiting_setup', 'new'], true)) {
                    $view['ecSetupCanInstall'] = $active;
                    $view['ecSetupToken'] = eclouddokploy_setup_csrf($serviceId);
                }
                if ($view['ecSetupEnabled']
                    && Capsule::schema()->hasTable('mod_ecloud_dokploy_setup')) {
                    $saved = Capsule::table('mod_ecloud_dokploy_setup')
                        ->where('service_id', $serviceId)->first([
                            'git_url', 'branch', 'root_path', 'preset', 'port'
                        ]);
                    if ($saved) {
                        $view['ecSetupConfigured'] = true;
                        $view['ecSetupGitUrl'] = (string) $saved->git_url;
                        $view['ecSetupBranch'] = (string) $saved->branch;
                        $view['ecSetupRoot'] = (string) $saved->root_path;
                        $view['ecSetupPreset'] = (string) $saved->preset;
                        $view['ecSetupPort'] = (int) $saved->port;
                    }
                }
                if ($isolated) {
                    $view['ecDokployIsolationLabel'] =
                        eclouddokploy_project_name($clientId, $serviceId);
                }
                $view['ecDokployProvisioned'] =
                    $isolated && (string) $record->state === 'deployment_requested'
                    && !empty($record->application_id);
                if ($active && $view['ecDokployProvisioned']) {
                    $view['ecDokployCanRedeploy'] =
                        getenv('ECLOUD_DOKPLOY_ENABLE_CLIENT_REDEPLOY') === '1';
                    if (getenv('ECLOUD_DOKPLOY_ENABLE_CLIENT_READS') === '1') {
                        try {
                            $api = new ECloudDokployClient($route['url'], $route['api_key']);
                            $live = eclouddokploy_live_overview(
                                $api, (string) $record->application_id,
                                (string) $record->environment_id
                            );
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

        // Email works independently of whether the Dokploy app has deployed.
        // Both WHMCS services must be active, with the same owner and domain.
        if ($active && eclouddokploy_valid_domain($domain)) {
            try {
                // Zero-configuration link to an already purchased cPanel mail
                // service. Does not need an API token or administrator map.
                $foundMail = eclouddokploy_auto_cpanel_mail($clientId, $domain);
                if ($foundMail !== null) {
                    $view['ecMailAutoLink'] = 'clientarea.php?action=productdetails&id=' . (int) $foundMail['service_id'];
                    $view['ecMailAutoAccount'] = $foundMail['domain'];
                }
                $binding = eclouddokploy_email_binding($serviceId, $clientId, $domain);
                if ($binding !== null) {
                    $view['ecMailLinked'] = true;
                    $view['ecMailDomain'] = $binding['domain'];
                    $view['ecMailMailboxLimit'] = $binding['mailbox_limit'];
                    $view['ecMailMaxQuotaMb'] = $binding['max_quota_mb'];
                    $view['ecMailWebmailUrl'] = 'https://' . $binding['host'] . ':2096/';
                    if (getenv('ECLOUD_DOKPLOY_ENABLE_EMAIL_READS') === '1') {
                        $rows = eclouddokploy_email_list(
                            eclouddokploy_email_client($binding), $binding['domain']
                        );
                        $view['ecMailAccounts'] = $rows;
                        $view['ecMailAccountCount'] = count($rows);
                        $view['ecMailAvailable'] = true;
                        $view['ecMailCanWrite'] =
                            getenv('ECLOUD_DOKPLOY_ENABLE_EMAIL_WRITES') === '1';
                        $view['ecMailCanDelete'] = $view['ecMailCanWrite']
                            && getenv('ECLOUD_DOKPLOY_ENABLE_EMAIL_DELETE') === '1';
                        if ($view['ecMailCanWrite']) {
                            $view['ecMailToken'] = eclouddokploy_email_csrf($serviceId);
                        }
                        $view['ecMailNotice'] = '';
                    } else {
                        $view['ecMailNotice'] =
                            'El servicio de correo está vinculado, pero la consulta de buzones está pendiente de habilitación.';
                    }
                }
            } catch (Throwable $e) {
                $view['ecMailNotice'] =
                    'No se pudieron consultar los buzones de correo. Contacta con soporte.';
            }
        }
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
    $actions = [];
    if (getenv('ECLOUD_DOKPLOY_ENABLE_CLIENT_REDEPLOY') === '1') {
        $actions[] = 'Redeploy';
    }
    // Action itself checks this specific product and its WHMCS owner.
    $actions[] = 'InstallApp';
    if (getenv('ECLOUD_DOKPLOY_ENABLE_EMAIL_READS') === '1'
        && getenv('ECLOUD_DOKPLOY_ENABLE_EMAIL_WRITES') === '1') {
        $actions[] = 'EmailAction';
    }
    return $actions;
}

function eclouddokploy_Redeploy(array $params): string
{
    return eclouddokploy_redeploy_request($params);
}

/** Authorized corporate email operations on a separate cPanel hosting account. */
function eclouddokploy_EmailAction(array $params): string
{
    return eclouddokploy_email_action($params);
}

/**
 * Administrator-only recovery path for an existing active pilot service.
 * In WHMCS use the product's admin provisioning button once. This does not
 * contact Dokploy or create resources, only reserves a tenant-safe service row.
 */
function eclouddokploy_AdminCustomButtonArray(): array
{
    // No manual action is needed: CreateAccount + the client's first visit
    // prepare all eligible accounts automatically.
    return [];
}

function eclouddokploy_PrepareInstaller(array $params): string
{
    if (!eclouddokploy_auto_enabled($params)) {
        return 'El producto no utiliza el instalador guiado.';
    }
    try {
        return eclouddokploy_setup_reserve(
            $params, eclouddokploy_resolve_route($params),
            (string) ($params['configoption5'] ?? 'none')
        );
    } catch (Throwable $e) {
        return 'No se pudo preparar este servicio para el instalador.';
    }
}

/** First guided install: use only the hosting service's pinned server/domain. */
function eclouddokploy_InstallApp(array $params): string
{
    return eclouddokploy_install_from_wizard($params);
}
