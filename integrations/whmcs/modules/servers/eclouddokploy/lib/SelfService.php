<?php
declare(strict_types=1);

/**
 * Self-service installation flow. A WHMCS order reserves the server capacity
 * without deploying arbitrary demo code; the customer later imports a PUBLIC
 * GitHub repository through their existing WHMCS/Lagom service detail page.
 *
 * Secret environment variables are encrypted at rest using libsodium and the
 * administrator-managed password seed. No secret is sent to Smarty or logging.
 */

function eclouddokploy_setup_root(string $root): ?string
{
    $root = trim($root);
    if ($root === '') return '/';
    if ($root[0] !== '/' || strlen($root) > 190 || str_contains($root, '//')) return null;
    if ($root === '/') return '/';
    $path = rtrim($root, '/');
    foreach (explode('/', ltrim($path, '/')) as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..'
            || !preg_match('/^[A-Za-z0-9_.-]{1,60}$/D', $segment)) return null;
    }
    return $path;
}

function eclouddokploy_setup_build_payload(string $appId, string $preset, string $root): array
{
    if (!in_array($preset, ['nextjs', 'nodejs', 'dockerfile'], true)
        || eclouddokploy_setup_root($root) !== $root) {
        throw new InvalidArgumentException('Invalid framework or build directory.');
    }
    // All documented Dokploy application.saveBuildType fields are kept explicit
    // to support versions where null-valued parameters are required.
    return [
        'applicationId' => $appId,
        'buildType' => $preset === 'dockerfile' ? 'dockerfile' : 'nixpacks',
        'dockerfile' => $preset === 'dockerfile' ? 'Dockerfile' : null,
        'dockerContextPath' => $root,
        'dockerBuildStage' => null,
        'herokuVersion' => null,
        'railpackVersion' => null,
    ];
}

function eclouddokploy_setup_repo_parts(string $url): ?array
{
    if (!eclouddokploy_valid_git($url, 'main')) return null;
    $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
    if (!preg_match('~^/([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+?)(?:[.]git)?$~D', $path, $m)) return null;
    return ['owner' => $m[1], 'repo' => $m[2]];
}

/**
 * Public GitHub inspection only. Prevent SSRF by never accepting a hostname,
 * API base URL, token, or redirect from the browser. No private GitHub access.
 */
function eclouddokploy_setup_github_file(
    string $gitUrl, string $branch, string $root, string $file
): ?string {
    $repo = eclouddokploy_setup_repo_parts($gitUrl);
    if (!$repo || !eclouddokploy_valid_git($gitUrl, $branch)
        || eclouddokploy_setup_root($root) !== $root
        || !in_array($file, ['package.json', 'Dockerfile'], true)) return null;
    $path = ($root === '/' ? '' : ltrim($root, '/') . '/') . $file;
    $endpoint = 'https://api.github.com/repos/'
        . rawurlencode($repo['owner']) . '/' . rawurlencode($repo['repo'])
        . '/contents/' . implode('/', array_map('rawurlencode', explode('/', $path)))
        . '?ref=' . rawurlencode($branch);
    $ch = curl_init($endpoint);
    if ($ch === false) return null;
    try {
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 7,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Accept: application/vnd.github+json',
                'User-Agent: ElearningCloud-Import-Inspector/1.0',
            ],
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($status !== 200 || !is_string($response) || strlen($response) > 98304) return null;
        $payload = json_decode($response, true, 12);
        if (!is_array($payload) || ($payload['encoding'] ?? '') !== 'base64'
            || !is_string($payload['content'] ?? null)) return null;
        $decoded = base64_decode(preg_replace('/\s+/', '', $payload['content']), true);
        return is_string($decoded) && strlen($decoded) <= 65536 ? $decoded : null;
    } catch (Throwable $e) {
        return null;
    } finally {
        curl_close($ch);
    }
}

function eclouddokploy_setup_detect(string $gitUrl, string $branch, string $root): array
{
    $package = eclouddokploy_setup_github_file($gitUrl, $branch, $root, 'package.json');
    if ($package !== null) {
        $data = json_decode($package, true, 10);
        if (is_array($data)) {
            $dependencies = array_merge(
                is_array($data['dependencies'] ?? null) ? $data['dependencies'] : [],
                is_array($data['devDependencies'] ?? null) ? $data['devDependencies'] : []
            );
            if (array_key_exists('next', $dependencies)) {
                return ['preset' => 'nextjs', 'label' => 'Next.js detectado', 'source' => 'package.json'];
            }
            if (array_key_exists('express', $dependencies) || array_key_exists('fastify', $dependencies)
                || array_key_exists('@nestjs/core', $dependencies)) {
                return ['preset' => 'nodejs', 'label' => 'Node.js detectado', 'source' => 'package.json'];
            }
            return ['preset' => 'nodejs', 'label' => 'Proyecto JavaScript detectado', 'source' => 'package.json'];
        }
    }
    $dockerfile = eclouddokploy_setup_github_file($gitUrl, $branch, $root, 'Dockerfile');
    if ($dockerfile !== null) {
        return ['preset' => 'dockerfile', 'label' => 'Dockerfile detectado', 'source' => 'Dockerfile'];
    }
    return [
        'preset' => null,
        'label' => 'No se pudo detectar automáticamente. Elige Next.js, Node.js o Dockerfile.',
        'source' => '',
    ];
}

/** Validated key=value pairs. Never allow overrides of the module-managed DB. */
function eclouddokploy_setup_parse_env(string $raw): array
{
    if (strlen($raw) > 6144 || str_contains($raw, "\0")) {
        throw new InvalidArgumentException('Las variables de entorno superan el límite permitido.');
    }
    $entries = [];
    foreach (preg_split('/\r\n|\n|\r/', $raw) as $line) {
        if (trim($line) === '') continue;
        if (count($entries) >= 30 || !str_contains($line, '=')) {
            throw new InvalidArgumentException('Usa hasta 30 variables en formato NOMBRE=valor.');
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/D', $key)
            || in_array(strtoupper($key), ['DATABASE_URL', 'NODE_ENV', 'PORT'], true)
            || str_starts_with(strtoupper($key), 'ECLOUD_')
            || isset($entries[$key]) || strlen($value) > 1024) {
            throw new InvalidArgumentException('Nombre duplicado, reservado o valor demasiado largo.');
        }
        $entries[$key] = $value;
    }
    return $entries;
}

function eclouddokploy_setup_key(string $seed, int $serviceId): string
{
    if (!preg_match('/^[a-f0-9]{64}$/Di', $seed)
        || $serviceId < 1 || !function_exists('sodium_crypto_secretbox')) {
        throw new RuntimeException('Falta cifrado seguro de variables de entorno en PHP.');
    }
    return hash_hmac('sha256', 'ec-dokploy-self-service-env/v1/' . $serviceId, hex2bin($seed), true);
}

function eclouddokploy_setup_encrypt_env(array $values, string $seed, int $serviceId): array
{
    if ($values === []) return ['ciphertext' => null, 'nonce' => null];
    $plain = json_encode($values, JSON_THROW_ON_ERROR);
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $cipher = sodium_crypto_secretbox(
        $plain, $nonce, eclouddokploy_setup_key($seed, $serviceId)
    );
    sodium_memzero($plain);
    return ['ciphertext' => base64_encode($cipher), 'nonce' => base64_encode($nonce)];
}

function eclouddokploy_setup_decrypt_env(
    ?string $ciphertext, ?string $nonceText, string $seed, int $serviceId
): array {
    if ($ciphertext === null && $nonceText === null) return [];
    if (!$ciphertext || !$nonceText) {
        throw new RuntimeException('Encrypted environment data is incomplete.');
    }
    $cipher = base64_decode($ciphertext, true);
    $nonce = base64_decode($nonceText, true);
    if ($cipher === false || $nonce === false || strlen($nonce) !== SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        throw new RuntimeException('Invalid encrypted environment data.');
    }
    $json = sodium_crypto_secretbox_open(
        $cipher, $nonce, eclouddokploy_setup_key($seed, $serviceId)
    );
    if ($json === false) throw new RuntimeException('Cannot decrypt application variables.');
    try {
        $values = json_decode($json, true, 10, JSON_THROW_ON_ERROR);
        if (!is_array($values)) throw new RuntimeException('Invalid application variables payload.');
        // Validate decrypted content again, against unexpected stored inputs.
        foreach ($values as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                throw new RuntimeException('Invalid saved variable entry.');
            }
        }
        $validated = eclouddokploy_setup_parse_env(implode("\n",
            array_map(static fn($k, $v) => $k . '=' . $v, array_keys($values), array_values($values))
        ));
        return $validated;
    } finally {
        sodium_memzero($json);
    }
}

/** Emit dotenv-compatible quoted values; reserved backend values are appended separately. */
function eclouddokploy_setup_env_lines(array $env): array
{
    $lines = [];
    foreach ($env as $key => $value) {
        // One-line user values, safely quoted for dotenv; no accidental key injection.
        $encoded = '"' . str_replace(
            ["\\", '"', "\n", "\r"],
            ["\\\\", '\\"', '\\n', '\\r'],
            $value
        ) . '"';
        $lines[] = $key . '=' . $encoded;
    }
    return $lines;
}

function eclouddokploy_setup_prepare(array $request, ?array $detected = null): array
{
    $git = trim((string) ($request['ec_setup_git'] ?? ''));
    $branch = trim((string) ($request['ec_setup_branch'] ?? 'main'));
    $root = eclouddokploy_setup_root((string) ($request['ec_setup_root'] ?? '/'));
    $preset = (string) ($request['ec_setup_preset'] ?? 'auto');
    $port = filter_var($request['ec_setup_port'] ?? 3000, FILTER_VALIDATE_INT);
    if (!eclouddokploy_valid_git($git, $branch)
        || eclouddokploy_setup_repo_parts($git) === null) {
        throw new InvalidArgumentException('Introduce la dirección HTTPS de un repositorio público GitHub y una rama válida.');
    }
    if ($root === null) {
        throw new InvalidArgumentException('La carpeta del proyecto debe ser / o una ruta como /apps/web.');
    }
    if (!in_array($preset, ['auto', 'nextjs', 'nodejs', 'dockerfile'], true)) {
        throw new InvalidArgumentException('Selecciona un framework admitido.');
    }
    if ($port === false || $port < 1024 || $port > 65535) {
        throw new InvalidArgumentException('Puerto no válido. Para Next.js normalmente es 3000.');
    }
    if ($preset === 'auto') {
        $detected = $detected ?? eclouddokploy_setup_detect($git, $branch, $root);
        $preset = (string) ($detected['preset'] ?? '');
        if (!in_array($preset, ['nextjs', 'nodejs', 'dockerfile'], true)) {
            throw new InvalidArgumentException('No se identificó el proyecto. Selecciona un framework manualmente.');
        }
    }
    $env = eclouddokploy_setup_parse_env((string) ($request['ec_setup_env'] ?? ''));
    return [
        'git_url' => $git,
        'branch' => $branch,
        'root_path' => $root,
        'preset' => $preset,
        'port' => (int) $port,
        'env' => $env,
    ];
}

function eclouddokploy_setup_create_table(): void
{
    if (\WHMCS\Database\Capsule::schema()->hasTable('mod_ecloud_dokploy_setup')) return;
    \WHMCS\Database\Capsule::schema()->create('mod_ecloud_dokploy_setup', static function ($table) {
        $table->unsignedInteger('service_id')->primary();
        $table->string('git_url', 255);
        $table->string('branch', 100);
        $table->string('root_path', 191);
        $table->string('preset', 16);
        $table->unsignedInteger('port');
        $table->text('env_ciphertext')->nullable();
        $table->string('env_nonce', 64)->nullable();
        $table->timestamps();
    });
}

function eclouddokploy_setup_persist(int $serviceId, array $setup, string $seed): void
{
    eclouddokploy_setup_create_table();
    $encrypted = eclouddokploy_setup_encrypt_env($setup['env'], $seed, $serviceId);
    \WHMCS\Database\Capsule::table('mod_ecloud_dokploy_setup')->insert([
        'service_id' => $serviceId,
        'git_url' => $setup['git_url'],
        'branch' => $setup['branch'],
        'root_path' => $setup['root_path'],
        'preset' => $setup['preset'],
        'port' => $setup['port'],
        'env_ciphertext' => $encrypted['ciphertext'],
        'env_nonce' => $encrypted['nonce'],
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
}

function eclouddokploy_setup_saved(int $serviceId, string $seed): ?array
{
    if (!\WHMCS\Database\Capsule::schema()->hasTable('mod_ecloud_dokploy_setup')) return null;
    $row = \WHMCS\Database\Capsule::table('mod_ecloud_dokploy_setup')
        ->where('service_id', $serviceId)->first();
    if (!$row) return null;
    return [
        'git_url' => (string) $row->git_url,
        'branch' => (string) $row->branch,
        'root_path' => (string) $row->root_path,
        'preset' => (string) $row->preset,
        'port' => (int) $row->port,
        'env' => eclouddokploy_setup_decrypt_env(
            $row->env_ciphertext, $row->env_nonce, $seed, $serviceId
        ),
    ];
}

function eclouddokploy_setup_csrf(int $serviceId): string
{
    if ($serviceId < 1) throw new InvalidArgumentException('Invalid service.');
    if (!isset($_SESSION['ec_dokploy_setup_csrf'])
        || !is_array($_SESSION['ec_dokploy_setup_csrf'])) {
        $_SESSION['ec_dokploy_setup_csrf'] = [];
    }
    if (!isset($_SESSION['ec_dokploy_setup_csrf'][$serviceId])
        || !is_string($_SESSION['ec_dokploy_setup_csrf'][$serviceId])
        || strlen($_SESSION['ec_dokploy_setup_csrf'][$serviceId]) !== 64) {
        $_SESSION['ec_dokploy_setup_csrf'][$serviceId] = bin2hex(random_bytes(32));
    }
    return $_SESSION['ec_dokploy_setup_csrf'][$serviceId];
}

/** User-supplied setup fields never override the exact WHMCS service/domain/server. */
function eclouddokploy_setup_request_authorized(array $params): bool
{
    $id = (int) ($params['serviceid'] ?? 0);
    $owner = (int) ($params['userid'] ?? 0);
    return $id > 0 && $owner > 0 && $owner === (int) ($_SESSION['uid'] ?? 0)
        && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        && (int) ($_POST['id'] ?? 0) === $id
        && (string) ($_POST['modop'] ?? '') === 'custom'
        && (string) ($_POST['a'] ?? '') === 'InstallApp'
        && strlen((string) ($_POST['ec_setup_token'] ?? '')) === 64
        && hash_equals(eclouddokploy_setup_csrf($id), (string) $_POST['ec_setup_token'])
        && (string) ($_POST['ec_setup_confirm'] ?? '') === 'yes';
}

function eclouddokploy_setup_reserve(array $params, array $route, string $dbMode): string
{
    $id = (int) ($params['serviceid'] ?? 0);
    $owner = (int) ($params['userid'] ?? 0);
    $domain = strtolower(rtrim(trim((string) ($params['domain'] ?? '')), '.'));
    if ($id < 1 || $owner < 1 || !eclouddokploy_valid_domain($domain)) {
        return 'Falta dominio, servicio o propietario válido para preparar la instalación.';
    }
    if (!in_array($dbMode, ['mariadb', 'mysql', 'postgres', 'external', 'none'], true)) {
        return 'La base de datos de este plan no está configurada correctamente.';
    }
    $service = \WHMCS\Database\Capsule::table('tblhosting')
        ->where('id', $id)->where('userid', $owner)
        ->first(['domain', 'domainstatus', 'packageid', 'server']);
    if (!$service || strtolower(rtrim((string) $service->domain, '.')) !== $domain
        || !in_array((string) $service->domainstatus, ['Pending', 'Active'], true)
        || (int) $service->packageid !== (int) ($params['pid'] ?? 0)
        || (int) $service->server !== (int) $route['whmcs_server_id']) {
        return 'El dominio, propietario, servidor o producto de WHMCS no coincide.';
    }
    eclouddokploy_create_table();
    $lock = 'ecloud-dokploy-' . $id;
    $db = \WHMCS\Database\Capsule::connection();
    $acquired = $db->selectOne('SELECT GET_LOCK(?, 8) AS acquired', [$lock]);
    if (!$acquired || (int) $acquired->acquired !== 1) {
        return 'Este servicio ya se está preparando.';
    }
    try {
        if ($route['whmcs_server_id'] > 0
            && !eclouddokploy_server_has_capacity($route['whmcs_server_id'], $id)) {
            return 'Este VPS ha alcanzado su máximo de cuentas.';
        }
        $table = \WHMCS\Database\Capsule::table('mod_ecloud_dokploy_services');
        $row = $table->where('service_id', $id)->first();
        if ($row) {
            if ((int) $row->owner_client_id !== $owner
                || !is_string($row->route_fingerprint)
                || !hash_equals($route['fingerprint'], $row->route_fingerprint)
                || (string) $row->db_mode !== $dbMode) {
                return 'La cuenta existente no coincide con el servidor o el propietario.';
            }
            return 'success';
        }
        $table->insert([
            'service_id' => $id,
            'owner_client_id' => $owner,
            'state' => 'awaiting_setup',
            'db_mode' => $dbMode,
            'route_fingerprint' => $route['fingerprint'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return 'success';
    } finally {
        $db->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
    }
}

function eclouddokploy_install_from_wizard(array $params): string
{
    if (!eclouddokploy_auto_enabled($params)) {
        return 'La instalación asistida no está habilitada en este producto.';
    }
    if (!eclouddokploy_setup_request_authorized($params)) {
        return 'Instalación no autorizada o confirmación caducada.';
    }
    $id = (int) $params['serviceid'];
    $owner = (int) $params['userid'];
    $domain = strtolower(rtrim(trim((string) ($params['domain'] ?? '')), '.'));
    $dbMode = (string) ($params['configoption5'] ?? 'none');
    if (!eclouddokploy_valid_domain($domain)) {
        return 'Este servicio necesita un dominio válido antes de instalar.';
    }
    try {
        // Automatic first-use seed, encrypted with the WHMCS application key.
        // Existing pilot deployments preserve their historical env seed.
        $seed = eclouddokploy_auto_seed();
        $route = eclouddokploy_resolve_route($params);
        $hosting = \WHMCS\Database\Capsule::table('tblhosting')
            ->where('id', $id)->where('userid', $owner)
            ->first(['packageid', 'server', 'domainstatus', 'domain']);
        if (!$hosting || (string) $hosting->domainstatus !== 'Active'
            || (int) $hosting->packageid !== (int) ($params['pid'] ?? 0)
            || (int) $hosting->server !== (int) $route['whmcs_server_id']
            || strtolower(rtrim((string) $hosting->domain, '.')) !== $domain) {
            return 'El servicio App Ecloud debe estar activo y vinculado a tu cuenta.';
        }
        $db = \WHMCS\Database\Capsule::connection();
        $lock = 'ecloud-dokploy-' . $id;
        $locked = $db->selectOne('SELECT GET_LOCK(?, 8) AS acquired', [$lock]);
        if (!$locked || (int) $locked->acquired !== 1) {
            return 'Ya hay una instalación en curso para este servicio.';
        }
        try {
            eclouddokploy_create_table();
            $row = \WHMCS\Database\Capsule::table('mod_ecloud_dokploy_services')
                ->where('service_id', $id)->first();
            if (!$row || (int) $row->owner_client_id !== $owner
                || (string) $row->db_mode !== $dbMode
                || !is_string($row->route_fingerprint)
                || !hash_equals($row->route_fingerprint, $route['fingerprint'])) {
                return 'El servicio no está reservado en este servidor. Contacta con soporte.';
            }
            if (!eclouddokploy_server_has_capacity($route['whmcs_server_id'], $id)) {
                return 'El VPS asignado ha alcanzado su límite de cuentas.';
            }
            $state = (string) $row->state;
            if (!in_array($state, ['awaiting_setup', 'new'], true)) {
                return 'La instalación ya está iniciada. Consulta el estado en tu panel; no se duplicarán recursos.';
            }
            if ($state === 'awaiting_setup') {
                // Crash-safe save: if the DB persisted setup before a PHP
                // interruption, reuse it instead of a duplicate INSERT or
                // allowing a different repository on the second POST.
                $existing = eclouddokploy_setup_saved($id, $seed);
                if ($existing === null) {
                    try {
                        $setup = eclouddokploy_setup_prepare($_POST);
                    } catch (InvalidArgumentException $e) {
                        return $e->getMessage();
                    }
                    eclouddokploy_setup_persist($id, $setup, $seed);
                }
                eclouddokploy_set_state($id, 'new');
            }
            $setup = eclouddokploy_setup_saved($id, $seed);
            if ($setup === null) {
                return 'Falta la configuración de instalación. Contacta con soporte.';
            }
            $api = new ECloudDokployClient($route['url'], $route['api_key']);
            $outcome = eclouddokploy_create_locked(
                $api, $id, $owner, $setup['git_url'], $setup['branch'],
                $setup['port'], $domain, $seed, $dbMode,
                $route['remote_server_id'], $route['fingerprint'], $setup
            );
            // WHMCS custom actions accept only the literal "success" as a
            // success response. Dokploy merely accepted an async deploy; the
            // service page will still show "Despliegue solicitado", never live.
            $after = \WHMCS\Database\Capsule::table('mod_ecloud_dokploy_services')
                ->where('service_id', $id)->first(['state']);
            if ($after && (string) $after->state === 'deployment_requested'
                && str_starts_with($outcome, 'Despliegue solicitado.')) {
                return 'success';
            }
            return $outcome;
        } finally {
            $db->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
        }
    } catch (Throwable $e) {
        // Never show remote errors, token, repository secrets, DB connection or envs.
        return 'La instalación necesita revisión. Consulta el estado de la ficha o contacta con soporte.';
    }
}
