<?php
declare(strict_types=1);

/**
 * WHMCS native Admin Services Tab + Module Commands.
 * No second login, no Dokploy credentials in admin HTML, and no client
 * impersonation. A support agent configures a service-scoped draft, then
 * explicitly chooses either "Crear proyecto" or "Instalar aplicación".
 *
 * Only normal WHMCS administrators can invoke these paths. Payment, service
 * ownership, fixed WHMCS server and project state are checked again on write.
 */

/** Minimal WHMCS audit trail: no tokens, repositories, passwords or env values. */
function eclouddokploy_admin_audit(string $event, array $params): void
{
    $messages = [
        'draft_saved' => 'Soporte guardó los ajustes GitHub del servicio',
        'project_ready' => 'Soporte creó o verificó el proyecto Dokploy',
        'deploy_requested' => 'Soporte solicitó la instalación de la aplicación',
    ];
    if (!isset($messages[$event])
        || (int) ($_SESSION['adminid'] ?? 0) <= 0
        || !function_exists('logActivity')) return;
    $id = (int) ($params['serviceid'] ?? 0);
    $owner = (int) ($params['userid'] ?? 0);
    if ($id < 1 || $owner < 1) return;
    logActivity(
        'Elearning Cloud: ' . $messages[$event] . ' #' . $id
        . ' (admin #' . (int) $_SESSION['adminid'] . ')',
        $owner
    );
}

function eclouddokploy_admin_authorized(array $params): bool
{
    return (int) ($_SESSION['adminid'] ?? 0) > 0
        && (int) ($params['serviceid'] ?? 0) > 0
        && (int) ($params['userid'] ?? 0) > 0
        && eclouddokploy_auto_enabled($params);
}

function eclouddokploy_admin_service(array $params, bool $requireActive = true): ?array
{
    if (!eclouddokploy_admin_authorized($params)) return null;
    $id = (int) $params['serviceid'];
    $owner = (int) $params['userid'];
    $domain = strtolower(rtrim(trim((string) ($params['domain'] ?? '')), '.'));
    if (!eclouddokploy_valid_domain($domain)) return null;
    $row = \WHMCS\Database\Capsule::table('tblhosting')
        ->where('id', $id)->where('userid', $owner)
        ->first(['id', 'domain', 'domainstatus', 'packageid', 'server']);
    if (!$row || (int) $row->packageid !== (int) ($params['pid'] ?? 0)
        || (int) $row->server !== (int) ($params['serverid'] ?? 0)
        || (int) $row->server < 1
        || strtolower(rtrim((string) $row->domain, '.')) !== $domain) {
        return null;
    }
    $allowed = $requireActive ? ['Active'] : ['Active', 'Pending'];
    if (!in_array((string) $row->domainstatus, $allowed, true)) return null;
    return ['service_id' => $id, 'owner_id' => $owner, 'domain' => $domain];
}

/** Validation is pure and does NOT fetch any URL; only Install validates online. */
function eclouddokploy_admin_validate_fields(array $fields): ?array
{
    $git = trim((string) ($fields[0] ?? ''));
    if ($git === '') return null;
    $branch = trim((string) ($fields[1] ?? 'main'));
    $root = eclouddokploy_setup_root((string) ($fields[2] ?? '/'));
    $preset = strtolower(trim((string) ($fields[3] ?? 'nextjs')));
    $port = filter_var($fields[4] ?? 3000, FILTER_VALIDATE_INT);
    if (!eclouddokploy_valid_git($git, $branch)
        || eclouddokploy_setup_repo_parts($git) === null) {
        throw new InvalidArgumentException('Repositorio o rama GitHub inválidos. Usa una URL HTTPS pública.');
    }
    if ($root === null || !in_array($preset, ['auto', 'nextjs', 'nodejs', 'dockerfile'], true)
        || $port === false || $port < 1024 || $port > 65535) {
        throw new InvalidArgumentException('La carpeta, framework o puerto de la aplicación no es válido.');
    }
    $rawSecrets = (string) ($fields[5] ?? '');
    if (strlen($rawSecrets) > 6144) {
        throw new InvalidArgumentException('Las variables de entorno son demasiado extensas.');
    }
    return [
        'git_url' => $git,
        'branch' => $branch,
        'root_path' => $root,
        'preset' => $preset,
        'port' => (int) $port,
        'env_raw' => $rawSecrets,
    ];
}

function eclouddokploy_admin_draft_table(): void
{
    $schema = \WHMCS\Database\Capsule::schema();
    if ($schema->hasTable('mod_ecloud_dokploy_admin_drafts')) return;
    $schema->create('mod_ecloud_dokploy_admin_drafts', static function ($table) {
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

function eclouddokploy_admin_draft(int $serviceId): ?array
{
    if ($serviceId < 1 || !\WHMCS\Database\Capsule::schema()
        ->hasTable('mod_ecloud_dokploy_admin_drafts')) return null;
    $row = \WHMCS\Database\Capsule::table('mod_ecloud_dokploy_admin_drafts')
        ->where('service_id', $serviceId)->first();
    if (!$row) return null;
    return [
        'git_url' => (string) $row->git_url,
        'branch' => (string) $row->branch,
        'root_path' => (string) $row->root_path,
        'preset' => (string) $row->preset,
        'port' => (int) $row->port,
        'env_ciphertext' => $row->env_ciphertext === null ? null : (string) $row->env_ciphertext,
        'env_nonce' => $row->env_nonce === null ? null : (string) $row->env_nonce,
    ];
}

function eclouddokploy_admin_draft_to_setup(array $draft, string $seed, int $id): array
{
    $env = eclouddokploy_setup_decrypt_env(
        $draft['env_ciphertext'] ?? null,
        $draft['env_nonce'] ?? null,
        $seed,
        $id
    );
    // Validate again at install time. "auto" requests GitHub's public
    // inspection on the server: no customer-supplied hostname / redirect.
    return eclouddokploy_setup_prepare([
        'ec_setup_git' => $draft['git_url'],
        'ec_setup_branch' => $draft['branch'],
        'ec_setup_root' => $draft['root_path'],
        'ec_setup_preset' => $draft['preset'],
        'ec_setup_port' => (string) $draft['port'],
        'ec_setup_env' => implode("\n", array_map(
            static fn($k, $v) => $k . '=' . $v,
            array_keys($env),
            array_values($env)
        )),
    ]);
}

function eclouddokploy_admin_state(int $serviceId): ?object
{
    if ($serviceId < 1 || !\WHMCS\Database\Capsule::schema()
        ->hasTable('mod_ecloud_dokploy_services')) return null;
    return \WHMCS\Database\Capsule::table('mod_ecloud_dokploy_services')
        ->where('service_id', $serviceId)
        ->first(['state', 'owner_client_id', 'project_id',
            'environment_id', 'route_fingerprint', 'application_id']);
}

function eclouddokploy_admin_editable_state(?object $row): bool
{
    return $row === null || in_array((string) $row->state,
        ['awaiting_setup', 'new', 'project_created', 'environment_ready'], true);
}

/** Once the installer persisted the Git source, both admin and client edits lock. */
function eclouddokploy_admin_existing_setup(int $serviceId): ?array
{
    if ($serviceId < 1 || !\WHMCS\Database\Capsule::schema()
        ->hasTable('mod_ecloud_dokploy_setup')) return null;
    $row = \WHMCS\Database\Capsule::table('mod_ecloud_dokploy_setup')
        ->where('service_id', $serviceId)
        ->first(['git_url', 'branch', 'root_path', 'preset', 'port']);
    if (!$row) return null;
    return [
        'git_url' => (string) $row->git_url,
        'branch' => (string) $row->branch,
        'root_path' => (string) $row->root_path,
        'preset' => (string) $row->preset,
        'port' => (int) $row->port,
    ];
}

function eclouddokploy_admin_can_edit(int $serviceId, ?object $state): bool
{
    return eclouddokploy_admin_editable_state($state)
        && eclouddokploy_admin_existing_setup($serviceId) === null;
}

function eclouddokploy_admin_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function eclouddokploy_admin_select(string $name, string $selected, array $options, bool $disabled): string
{
    $html = '<select class="form-control ecd-admin-control" aria-label="Tipo de aplicación" name="modulefields['
        . eclouddokploy_admin_html($name) . ']"' . ($disabled ? ' disabled' : '') . '>';
    foreach ($options as $value => $label) {
        $html .= '<option value="' . eclouddokploy_admin_html($value) . '"'
            . ($selected === $value ? ' selected' : '') . '>'
            . eclouddokploy_admin_html($label) . '</option>';
    }
    return $html . '</select>';
}

function eclouddokploy_admin_tab_fields(array $params): array
{
    $service = eclouddokploy_admin_service($params, false);
    if ($service === null) {
        return ['Proyecto Dokploy' => 'No autorizado o servicio no asociado al módulo guiado.'];
    }
    $id = $service['service_id'];
    $state = eclouddokploy_admin_state($id);
    $installedSource = eclouddokploy_admin_existing_setup($id);
    $draft = $installedSource ?? eclouddokploy_admin_draft($id);
    $editable = eclouddokploy_admin_can_edit($id, $state);
    $status = eclouddokploy_public_state((string) ($state->state ?? 'awaiting_setup'));
    $hasProject = $state && !empty($state->project_id) && !empty($state->environment_id);
    // CSS is scoped to the native module fields, never WHMCS/Lagom globally.
    $style = '<style>'
        . '.ecd-admin-panel{--ecap-blue:#155ba7;--ecap-ink:#18304d;--ecap-muted:#496078;'
        . '--ecap-edge:#d7e2ee;background:#f7fafd;border:1px solid var(--ecap-edge);'
        . 'border-radius:14px;padding:17px;margin:4px 0 14px;line-height:1.55;color:var(--ecap-ink);max-width:100%;}'
        . '.ecd-admin-panel *{box-sizing:border-box}'
        . '.ecd-admin-panel h4{font-size:18px;font-weight:750;color:var(--ecap-ink);margin:0 0 6px}'
        . '.ecd-admin-panel p{margin:0 0 8px;font-size:13px;color:var(--ecap-muted)}'
        . '.ecd-admin-pill{display:inline-flex;align-items:center;gap:7px;border:1px solid var(--ecap-edge);'
        . 'background:#fff;color:#18304d;border-radius:30px;padding:5px 11px;font-size:12px;font-weight:750}'
        . '.ecd-admin-pill:before{content:"";width:8px;height:8px;border-radius:50%;background:var(--ecap-blue)}'
        . '.ecd-admin-meta{display:flex;flex-wrap:wrap;gap:8px;margin:13px 0}'
        . '.ecd-admin-meta span{border:1px solid var(--ecap-edge);border-radius:9px;'
        . 'background:#fff;padding:7px 10px;font-size:13px;overflow-wrap:anywhere}'
        . '.ecd-admin-tasks{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px;margin-top:14px}'
        . '.ecd-admin-task{background:#fff;border:1px solid var(--ecap-edge);border-radius:10px;padding:12px;font-size:13px}'
        . '.ecd-admin-task strong{display:block;color:var(--ecap-ink);margin-bottom:5px}'
        . '.ecd-admin-task small{font-size:12px;color:var(--ecap-muted);display:block;line-height:1.45}'
        . '.ecd-admin-panel code{color:#223c5f;font-size:12px;overflow-wrap:anywhere}'
        . '.ecd-admin-control{display:block;min-height:46px!important;max-width:650px!important;'
        . 'width:100%!important;font-size:16px!important;border-radius:9px!important;'
        . 'border:1px solid #b6c6d7!important;padding:9px 12px!important;}'
        . '.ecd-admin-control:focus-visible{outline:3px solid #1b68ba!important;outline-offset:2px}'
        . '.ecd-admin-help{display:block;font-size:13px!important;line-height:1.5;'
        . 'color:#435971!important;max-width:650px;margin-top:6px}'
        . '.ecd-admin-instructions{max-width:650px;background:#f7fafd;border-left:3px solid #155ba7;'
        . 'padding:12px 14px;border-radius:0 9px 9px 0;line-height:1.6;font-size:13px;}'
        . '.ecd-admin-instructions ol{padding-left:18px;margin:6px 0}'
        . '.ecd-admin-panel .ecd-admin-ids{font-size:12px;overflow-wrap:anywhere}'
        . '@media(max-width:680px){.ecd-admin-tasks{grid-template-columns:1fr}.ecd-admin-panel{padding:13px}}'
        . '@media(prefers-reduced-motion:reduce){.ecd-admin-panel *{transition:none!important}}'
        . '</style>';
    $value = $style . '<section class="ecd-admin-panel" aria-label="Asistente de soporte Dokploy">'
        . '<span class="ecd-admin-pill">'
        . ($hasProject ? 'Proyecto privado creado' : 'Proyecto pendiente de crear')
        . '</span>'
        . '<h4 style="margin-top:9px">' . eclouddokploy_admin_html($status[0]) . '</h4>'
        . '<p>' . eclouddokploy_admin_html($status[1]) . '</p>'
        . '<div class="ecd-admin-meta">'
        . '<span><strong>Dominio:</strong> ' . eclouddokploy_admin_html($service['domain']) . '</span>'
        . '<span><strong>Cliente:</strong> #' . (int) $service['owner_id'] . '</span>'
        . '<span><strong>Servicio:</strong> #' . $id . '</span>'
        . '</div>';
    if ($hasProject) {
        $value .= '<p class="ecd-admin-ids">Proyecto: <code>'
            . eclouddokploy_admin_html((string) $state->project_id)
            . '</code> · Production: <code>'
            . eclouddokploy_admin_html((string) $state->environment_id)
            . '</code></p>';
    }
    $value .= '<div class="ecd-admin-tasks">'
        . '<div class="ecd-admin-task"><strong>1. Crear / verificar proyecto</strong>'
        . '<small>En «Comandos del módulo». Solo crea el proyecto y production. No necesita GitHub.</small></div>'
        . '<div class="ecd-admin-task"><strong>2. Instalar por el cliente</strong>'
        . '<small>Introduce GitHub abajo, guarda la ficha y pulsa «Instalar aplicación del cliente».</small></div>'
        . '</div>'
        . '<p style="margin-top:12px">Se requiere servicio y primera factura pagados. La solicitud de despliegue no confirma que el sitio esté online.</p>'
        . '</section>';
    $disabled = $editable ? '' : ' disabled';
    $repo = (string) ($draft['git_url'] ?? '');
    $branch = (string) ($draft['branch'] ?? 'main');
    $root = (string) ($draft['root_path'] ?? '/');
    $preset = (string) ($draft['preset'] ?? 'nextjs');
    $port = (int) ($draft['port'] ?? 3000);

    $input = static function (int $i, string $type, string $value,
        string $placeholder, string $attrs = '') use ($disabled): string {
        return '<input class="form-control ecd-admin-control" name="modulefields[' . $i
            . ']" type="' . $type . '" value="' . eclouddokploy_admin_html($value)
            . '" placeholder="' . eclouddokploy_admin_html($placeholder)
            . '" ' . $attrs . $disabled . '>';
    };

    $message = $_SESSION['ec_dokploy_admin_notice'][$id] ?? '';
    unset($_SESSION['ec_dokploy_admin_notice'][$id]);
    if (is_string($message) && $message !== '') {
        $noticeClass = str_starts_with($message, 'Configuración de soporte guardada.')
            ? 'alert-success' : 'alert-warning';
        $value .= '<p class="alert ' . $noticeClass . '" role="status">'
            . eclouddokploy_admin_html($message) . '</p>';
    }
    $note = $editable
        ? 'Guardar cambios registra únicamente la configuración GitHub en WHMCS; '
            . 'no instala ni borra recursos en Dokploy. Para instalar, usa «Comandos del módulo» '
            . 'y elige «Instalar aplicación del cliente». El comando «Crear / verificar proyecto Dokploy» '
            . 'funciona incluso con GitHub vacío.'
        : 'Este servicio ya tiene una instalación guardada o en curso: el repositorio original '
            . 'se mantiene bloqueado para impedir una reinstalación accidental. '
            . 'Si necesitas cambiarlo, abre una incidencia de conciliación.';

    return [
        'Proyecto Dokploy · Centro de ayuda' => $value,
        'Repositorio GitHub del cliente' => $input(0, 'url', $repo,
            'https://github.com/usuario/proyecto.git',
            ' aria-label="Repositorio GitHub público" maxlength="255" autocomplete="off" autocapitalize="none"')
            . '<small class="ecd-admin-help">Solo el repositorio del cliente. '
            . 'Por ejemplo, https://github.com/cliente/mi-web. '
            . 'Este campo es opcional si solo vas a crear el proyecto.</small>',
        'Rama GitHub' => $input(1, 'text', $branch, 'main',
            ' aria-label="Rama GitHub" maxlength="100"')
            . '<small class="ecd-admin-help">Normalmente main.</small>',
        'Carpeta de la aplicación' => $input(2, 'text', $root, '/',
            ' aria-label="Carpeta raíz de la aplicación" maxlength="190"')
            . '<small class="ecd-admin-help">Si es un monorepo, usa /apps/web. Por defecto /.</small>',
        'Framework y compilación' => eclouddokploy_admin_select('3', $preset, [
            'nextjs' => 'Next.js · Automático (sin Dockerfile)',
            'nodejs' => 'Node.js · Automático (Nixpacks)',
            'dockerfile' => 'Dockerfile · Avanzado',
            'auto' => 'Detectar en GitHub',
        ], !$editable)
            . '<small class="ecd-admin-help">Selecciona Next.js en sitios Next.js; no requiere Dockerfile.</small>',
        'Puerto interno' => $input(4, 'number', (string) $port, '3000',
            ' aria-label="Puerto interno" min="1024" max="65535"')
            . '<small class="ecd-admin-help">El puerto es 3000 para Next.js salvo que el cliente use otro.</small>',
        'Variables de entorno · Avanzado' =>
            '<textarea name="modulefields[5]" rows="3" maxlength="6144" '
            . 'class="form-control ecd-admin-control" aria-label="Variables de entorno" '
            . 'placeholder="API_URL=https://api.ejemplo.com&#10;APP_SECRET=..."'
            . $disabled . '></textarea>'
            . '<small class="ecd-admin-help">Opcional. Cada línea: CLAVE=valor. '
            . 'Se guardan cifradas, no se vuelven a mostrar. Si dejas vacío, se conservan. '
            . 'Nunca pegues claves en tickets ni envíes aquí la API key de Dokploy.</small>',
        'Siguiente paso' => '<input type="hidden" name="modulefields[6]" value="'
            . $id . '"><div class="ecd-admin-instructions" role="note">'
            . eclouddokploy_admin_html($note) . '</div>',
    ];
}

function eclouddokploy_admin_save_fields(array $params): void
{
    if (!eclouddokploy_admin_authorized($params)
        || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
    $service = eclouddokploy_admin_service($params, false);
    if ($service === null) return;
    $id = $service['service_id'];
    $fields = $_POST['modulefields'] ?? null;
    if (!is_array($fields) || (int) ($fields[6] ?? 0) !== $id) return;

    try {
        if (!eclouddokploy_admin_can_edit($id, eclouddokploy_admin_state($id))) {
            throw new RuntimeException('No se puede cambiar Git después de comenzar la instalación.');
        }
        $form = eclouddokploy_admin_validate_fields($fields);
        if ($form === null) return; // No Git data to save yet.
        $old = eclouddokploy_admin_draft($id);
        $crypto = ['ciphertext' => $old['env_ciphertext'] ?? null,
            'nonce' => $old['env_nonce'] ?? null];
        if (trim($form['env_raw']) !== '') {
            $secrets = eclouddokploy_setup_parse_env($form['env_raw']);
            $crypto = eclouddokploy_setup_encrypt_env(
                $secrets, eclouddokploy_auto_seed(), $id
            );
        }
        $values = [
            'git_url' => $form['git_url'],
            'branch' => $form['branch'],
            'root_path' => $form['root_path'],
            'preset' => $form['preset'],
            'port' => $form['port'],
            'env_ciphertext' => $crypto['ciphertext'],
            'env_nonce' => $crypto['nonce'],
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $db = \WHMCS\Database\Capsule::connection();
        $lock = 'ecloud-dokploy-' . $id;
        $got = $db->selectOne('SELECT GET_LOCK(?, 8) AS acquired', [$lock]);
        if (!$got || (int) $got->acquired !== 1) {
            throw new RuntimeException('Ya se está preparando este servicio.');
        }
        try {
            // Recheck under the same lock held by the deployment handler.
            if (!eclouddokploy_admin_can_edit($id, eclouddokploy_admin_state($id))) {
                throw new RuntimeException('La instalación empezó. Los ajustes ya están protegidos.');
            }
            eclouddokploy_admin_draft_table();
            if ($old === null) $values['created_at'] = date('Y-m-d H:i:s');
            \WHMCS\Database\Capsule::table('mod_ecloud_dokploy_admin_drafts')
                ->updateOrInsert(['service_id' => $id], $values);
            if (!isset($_SESSION['ec_dokploy_admin_notice'])
                || !is_array($_SESSION['ec_dokploy_admin_notice'])) {
                $_SESSION['ec_dokploy_admin_notice'] = [];
            }
            $_SESSION['ec_dokploy_admin_notice'][$id] =
                'Configuración de soporte guardada. Elige un comando del módulo para continuar.';
            eclouddokploy_admin_audit('draft_saved', $params);
        } finally {
            $db->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
        }
    } catch (Throwable $e) {
        // No credentials or supplied source text are written to logs.
        if (!isset($_SESSION['ec_dokploy_admin_notice'])
            || !is_array($_SESSION['ec_dokploy_admin_notice'])) {
            $_SESSION['ec_dokploy_admin_notice'] = [];
        }
        $_SESSION['ec_dokploy_admin_notice'][$id] =
            $e instanceof InvalidArgumentException
                ? $e->getMessage()
                : 'No se guardaron los datos de GitHub. Revisa el estado del servicio.';
    }
}

function eclouddokploy_admin_create_project(array $params): string
{
    $service = eclouddokploy_admin_service($params, false);
    if ($service === null) return 'No hay una sesión administrativa o un servicio válido.';
    try {
        // Existing paid service, pinned WHMCS server; no manually entered URL.
        $route = eclouddokploy_resolve_route($params);
        $reserved = eclouddokploy_setup_reserve(
            $params, $route, (string) ($params['configoption5'] ?? 'none')
        );
        if ($reserved !== 'success') return $reserved;
        $result = eclouddokploy_order_prepare_project($params, $route);
        if ($result === 'success') {
            eclouddokploy_admin_audit('project_ready', $params);
        }
        return $result;
    } catch (Throwable $e) {
        return 'No se pudo crear o verificar el proyecto privado. Comprueba el pago y conexión.';
    }
}

function eclouddokploy_admin_install_app(array $params): string
{
    $service = eclouddokploy_admin_service($params, true);
    if ($service === null) return 'El servicio debe estar activo y pertenecer al cliente asignado.';
    $id = $service['service_id'];
    $owner = $service['owner_id'];
    if (!eclouddokploy_order_invoice_paid($id, $owner)) {
        return 'La factura inicial no está pagada; no se despliegan recursos sin autorización.';
    }
    try {
        $draft = eclouddokploy_admin_draft($id);
        $seed = eclouddokploy_auto_seed();
        $originalSetup = eclouddokploy_setup_saved($id, $seed);
        if ($draft === null && $originalSetup === null) {
            return 'Primero introduce el repositorio GitHub y guarda la ficha del servicio.';
        }
        // Reuse customer's immutable configuration if the guided install was
        // already saved; otherwise use the support agent's protected draft.
        $setup = $originalSetup
            ?? eclouddokploy_admin_draft_to_setup($draft, $seed, $id);
        $route = eclouddokploy_resolve_route($params);
        $prepared = eclouddokploy_admin_create_project($params);
        if ($prepared !== 'success') return $prepared;

        $db = \WHMCS\Database\Capsule::connection();
        $lock = 'ecloud-dokploy-' . $id;
        $got = $db->selectOne('SELECT GET_LOCK(?, 8) AS acquired', [$lock]);
        if (!$got || (int) $got->acquired !== 1) {
            return 'Ya hay una instalación o cambio de configuración en curso.';
        }
        try {
            $state = eclouddokploy_admin_state($id);
            if (!$state || !is_string($state->route_fingerprint)
                || !hash_equals($route['fingerprint'], $state->route_fingerprint)
                || (int) $state->owner_client_id !== $owner) {
                return 'La cuenta o el servidor asignado no coinciden.';
            }
            if ((string) $state->state === 'deployment_requested') {
                return 'La aplicación ya tiene un despliegue solicitado. No se creó otra.';
            }
            if ((string) $state->state !== 'environment_ready') {
                return 'El despliegue ya se inició o necesita conciliación; no se duplicarán recursos.';
            }
            if (!eclouddokploy_server_has_capacity((int) $route['whmcs_server_id'], $id)) {
                return 'El servidor alcanzó su límite de cuentas.';
            }
            $saved = eclouddokploy_setup_saved($id, $seed);
            if ($saved === null) {
                eclouddokploy_setup_persist($id, $setup, $seed);
                $saved = $setup;
            }
            // The original setup is immutable once stored, even if another
            // administrator saved a different draft meanwhile.
            $api = new ECloudDokployClient($route['url'], $route['api_key']);
            $result = eclouddokploy_create_locked(
                $api, $id, $owner, $saved['git_url'], $saved['branch'],
                $saved['port'], $service['domain'], $seed,
                (string) ($params['configoption5'] ?? 'none'),
                $route['remote_server_id'], $route['fingerprint'], $saved
            );
            $after = eclouddokploy_admin_state($id);
            if ($after && (string) $after->state === 'deployment_requested'
                && str_starts_with($result, 'Despliegue solicitado.')) {
                eclouddokploy_admin_audit('deploy_requested', $params);
                return 'success';
            }
            return $result;
        } finally {
            $db->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
        }
    } catch (InvalidArgumentException $e) {
        return 'Los datos del repositorio no son válidos; edítalos y guarda antes de instalar.';
    } catch (Throwable $e) {
        // Do not echo server exceptions; could include secret parameters.
        return 'La instalación requiere revisión. Consulta el estado antes de repetir el comando.';
    }
}
