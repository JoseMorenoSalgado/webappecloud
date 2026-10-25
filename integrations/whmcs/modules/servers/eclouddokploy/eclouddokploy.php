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

use WHMCS\Database\Capsule;

function eclouddokploy_MetaData(): array
{
    return ['DisplayName' => 'Elearning Cloud — Dokploy (piloto)', 'APIVersion' => '1.1'];
}

function eclouddokploy_ConfigOptions(): array
{
    return [
        'Dokploy Environment ID' => [
            'Type' => 'text', 'Size' => '48',
            'Description' => 'ID de un entorno existente de Dokploy. No incluir API keys.',
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
    ];
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
        if ($serviceId <= 0 || !preg_match('/^[A-Za-z0-9_-]{4,128}$/D', $environmentId)) {
            return 'Configuración incompleta: Service ID o Environment ID incorrecto.';
        }
        if (!eclouddokploy_valid_git($gitUrl, $branch)) {
            return 'Repositorio Git inválido: solo HTTPS público de GitHub, rama válida.';
        }
        if ($port === false || $port < 1024 || $port > 65535 || !eclouddokploy_valid_domain($domain)) {
            return 'Dominio o puerto de aplicación inválido.';
        }

        $secret = trim((string) getenv('ECLOUD_DOKPLOY_PASSWORD_SEED'));
        $baseUrl = trim((string) getenv('ECLOUD_DOKPLOY_URL'));
        $apiKey = trim((string) getenv('ECLOUD_DOKPLOY_API_KEY'));
        if (!preg_match('/^[a-f0-9]{64}$/Di', $secret)) {
            return 'Configurar ECLOUD_DOKPLOY_PASSWORD_SEED como 32 bytes hexadecimales.';
        }
        $api = new ECloudDokployClient($baseUrl, $apiKey);
        eclouddokploy_create_table();

        $lockName = 'ecloud-dokploy-' . $serviceId;
        $connection = Capsule::connection();
        $locked = $connection->selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lockName]);
        if (!$locked || (int) $locked->acquired !== 1) {
            return 'El aprovisionamiento de este cliente ya está en ejecución.';
        }
        try {
            return eclouddokploy_create_locked(
                $api, $serviceId, $environmentId, $gitUrl, $branch, (int) $port, $domain, $secret
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

function eclouddokploy_create_table(): void
{
    if (Capsule::schema()->hasTable('mod_ecloud_dokploy_services')) return;
    Capsule::schema()->create('mod_ecloud_dokploy_services', function ($table) {
        $table->unsignedInteger('service_id')->primary();
        $table->string('state', 48)->default('new');
        $table->string('mariadb_id', 128)->nullable();
        $table->string('application_id', 128)->nullable();
        $table->string('domain_id', 128)->nullable();
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

function eclouddokploy_create_locked(
    ECloudDokployClient $api, int $id, string $envId, string $gitUrl,
    string $branch, int $port, string $domain, string $seed
): string {
    $table = Capsule::table('mod_ecloud_dokploy_services');
    $stateRow = $table->where('service_id', $id)->first();
    if (!$stateRow) {
        $table->insert([
            'service_id' => $id, 'state' => 'new',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $stateRow = $table->where('service_id', $id)->first();
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
    if ($state === 'new') {
        eclouddokploy_set_state($id, 'creating_db');
        $db = $api->post('mariadb.create', [
            'name' => $dbApp, 'appName' => $dbApp,
            'environmentId' => $envId, 'dockerImage' => 'mariadb:11.4',
            'databaseName' => $dbName, 'databaseUser' => $dbUser,
            'databasePassword' => $pass, 'databaseRootPassword' => $rootPass,
        ]);
        $dbId = eclouddokploy_required_id($db, 'mariadbId');
        eclouddokploy_set_state($id, 'db_created', ['mariadb_id' => $dbId]);
        $state = 'db_created';
    }
    $stateRow = $table->where('service_id', $id)->first();
    $dbId = (string) ($stateRow->mariadb_id ?? '');
    if ($dbId === '') return 'No hay ID de MariaDB. Requiere conciliación manual.';

    if ($state === 'db_created') {
        eclouddokploy_set_state($id, 'deploying_db');
        $api->post('mariadb.deploy', ['mariadbId' => $dbId]);
        eclouddokploy_set_state($id, 'db_deployed');
        $state = 'db_deployed';
    }
    if ($state === 'db_deployed') {
        eclouddokploy_set_state($id, 'creating_app');
        $app = $api->post('application.create', [
            'name' => $name, 'appName' => $name,
            'environmentId' => $envId, 'sourceType' => 'git',
        ]);
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
        $api->post('application.saveBuildType', [
            'applicationId' => $appId, 'buildType' => 'dockerfile',
            'dockerfile' => 'Dockerfile', 'dockerContextPath' => '/',
        ]);
        $details = $api->get('mariadb.one', ['mariadbId' => $dbId]);
        $internalHost = (string) ($details['appName'] ?? '');
        if ($internalHost === '' || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/D', $internalHost)) {
            throw new RuntimeException('Cannot determine private MariaDB hostname; configure manually.');
        }
        $databaseUrl = 'mysql://' . rawurlencode($dbUser) . ':' . rawurlencode($pass)
            . '@' . $internalHost . ':3306/' . rawurlencode($dbName);
        $api->post('application.saveEnvironment', [
            'applicationId' => $appId,
            'env' => 'DATABASE_URL=' . $databaseUrl . "\n"
                . 'NODE_ENV=production' . "\n"
                . 'PORT=' . $port . "\n",
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
