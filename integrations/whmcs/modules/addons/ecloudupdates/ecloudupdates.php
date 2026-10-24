<?php
/**
 * WHMCS addon: Elearning Cloud Update Manager.
 * Foundation only: no public package download or update execution.
 */
if (!defined('WHMCS')) { exit('Direct access not allowed'); }

function ecloudupdates_config()
{
    return [
        'name' => 'Elearning Cloud Update Manager',
        'description' => 'Administración de versiones del CMS WebAppECloud.',
        'version' => '0.1.0',
        'author' => 'Elearning Cloud',
        'language' => 'spanish',
        'fields' => [],
    ];
}

function ecloudupdates_activate()
{
    try {
        $schema = \WHMCS\Database\Capsule::schema();
        if (!$schema->hasTable('mod_ecloudupdates_releases')) {
            $schema->create('mod_ecloudupdates_releases', function ($table) {
                $table->increments('id');
                $table->string('version', 64)->unique();
                $table->string('channel', 16)->default('stable');
                $table->string('category', 24)->default('feature');
                $table->string('title', 190);
                $table->text('notes');
                $table->string('sha256', 64)->nullable();
                $table->boolean('published')->default(false);
                $table->timestamps();
            });
        }
        return ['status' => 'success', 'description' => 'Tablas del gestor de versiones preparadas.'];
    } catch (\Throwable $e) {
        logActivity('Elearning Cloud Update Manager activation failed');
        return ['status' => 'error', 'description' => 'No se pudo preparar la base de datos del módulo.'];
    }
}

function ecloudupdates_deactivate()
{
    // Preserve releases and audit history on deactivation.
    return ['status' => 'success', 'description' => 'Módulo desactivado. Los datos se conservaron.'];
}

function ecloudupdates_escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Write a public metadata-only manifest, never a package or credentials. */
function ecloudupdates_write_manifest()
{
    $path = getenv('ECLOUD_UPDATE_MANIFEST_FILE');
    if (!$path || substr($path, 0, 1) !== '/' || is_link($path) || !is_dir(dirname($path))) {
        throw new \RuntimeException('Configure an absolute private manifest path');
    }
    $releases = \WHMCS\Database\Capsule::table('mod_ecloudupdates_releases')
        ->where('published', 1)->orderBy('id', 'desc')->limit(100)->get();
    $items = [];
    foreach ($releases as $row) {
        $items[] = [
            'version' => $row->version,
            'channel' => $row->channel,
            'category' => $row->category,
            'title' => $row->title,
            'notes' => $row->notes,
            'publishedAt' => (string) $row->updated_at,
        ];
    }
    $json = json_encode(['schemaVersion' => 1, 'releases' => $items], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (strlen($json) > 131072) throw new \RuntimeException('Manifest exceeds size limit');
    $temp = tempnam(dirname($path), '.manifest-');
    if ($temp === false) throw new \RuntimeException('Cannot create manifest');
    try {
        if (file_put_contents($temp, $json, LOCK_EX) === false) throw new \RuntimeException('Cannot write manifest');
        chmod($temp, 0600);
        if (!rename($temp, $path)) throw new \RuntimeException('Cannot publish manifest');
    } finally {
        if (file_exists($temp)) unlink($temp);
    }
}

function ecloudupdates_output($vars)
{
    $db = \WHMCS\Database\Capsule::table('mod_ecloudupdates_releases');
    $message = '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['ecloud_action'])) {
        // WHMCS administrative CSRF token; never bypass token verification.
        check_token('WHMCS.admin.default');
        $action = (string) $_POST['ecloud_action'];
        try {
            if ($action === 'create') {
                $version = trim((string) ($_POST['version'] ?? ''));
                $channel = (string) ($_POST['channel'] ?? '');
                $category = (string) ($_POST['category'] ?? '');
                $title = trim((string) ($_POST['title'] ?? ''));
                $notes = trim((string) ($_POST['notes'] ?? ''));
                if (!preg_match('/^\\d+\\.\\d+\\.\\d+(?:-[0-9A-Za-z.-]+)?$/D', $version) ||
                    !in_array($channel, ['stable', 'beta'], true) ||
                    !in_array($category, ['security', 'bugfix', 'performance', 'feature'], true) ||
                    $title === '' || strlen($title) > 190 || strlen($notes) > 4000) {
                    throw new \InvalidArgumentException('Datos de versión inválidos');
                }
                $db->insert([
                    'version' => $version, 'channel' => $channel, 'category' => $category,
                    'title' => $title, 'notes' => $notes, 'published' => 0,
                    'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
                ]);
                logActivity('ECloud Update Manager: draft release created ' . $version);
                $message = 'Borrador guardado. Aún no es visible para los clientes.';
            } elseif ($action === 'publish') {
                $id = filter_var($_POST['release_id'] ?? null, FILTER_VALIDATE_INT);
                if (!$id || !$db->where('id', $id)->exists()) throw new \InvalidArgumentException('Versión no encontrada');
                $db->where('id', $id)->update(['published' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
                ecloudupdates_write_manifest();
                logActivity('ECloud Update Manager: release published id=' . $id);
                $message = 'Versión publicada en el manifiesto.';
            } else {
                throw new \InvalidArgumentException('Operación desconocida');
            }
        } catch (\Throwable $e) {
            logActivity('ECloud Update Manager: release operation failed');
            $message = 'No se completó la operación. Revise la configuración o los datos.';
        }
    }
    $rows = \WHMCS\Database\Capsule::table('mod_ecloudupdates_releases')
        ->orderBy('id', 'desc')->limit(100)->get();
    $token = function_exists('generate_token') ? generate_token('plain') : '';
    echo '<div class="container-fluid"><h2>Elearning Cloud Update Manager</h2>';
    echo '<p>Publicación de metadatos de versiones. Todavía no se distribuyen ni instalan paquetes.</p>';
    if ($message) echo '<div class="alert alert-info">'.ecloudupdates_escape($message).'</div>';
    echo '<form method="post" class="well"><input type="hidden" name="token" value="'.ecloudupdates_escape($token).'">';
    echo '<input type="hidden" name="ecloud_action" value="create"><h3>Nueva versión (borrador)</h3>';
    echo '<div class="form-group"><label>Versión</label><input class="form-control" name="version" required placeholder="0.2.0"></div>';
    echo '<div class="form-group"><label>Canal</label><select class="form-control" name="channel"><option value="stable">Estable</option><option value="beta">Beta</option></select></div>';
    echo '<div class="form-group"><label>Categoría</label><select class="form-control" name="category"><option value="feature">Funcionalidad</option><option value="security">Seguridad</option><option value="bugfix">Corrección</option><option value="performance">Rendimiento</option></select></div>';
    echo '<div class="form-group"><label>Título</label><input class="form-control" name="title" maxlength="190" required></div>';
    echo '<div class="form-group"><label>Novedades</label><textarea class="form-control" name="notes" maxlength="4000" rows="4"></textarea></div>';
    echo '<button class="btn btn-primary" type="submit">Guardar borrador</button></form>';
    echo '<table class="table table-striped"><thead><tr><th>Versión</th><th>Canal</th><th>Título</th><th>Estado</th><th>Acción</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr><td>'.ecloudupdates_escape($row->version).'</td><td>'.ecloudupdates_escape($row->channel).'</td>';
        echo '<td>'.ecloudupdates_escape($row->title).'</td><td>'.($row->published ? 'Publicada' : 'Borrador').'</td><td>';
        if (!$row->published) {
            echo '<form method="post"><input type="hidden" name="token" value="'.ecloudupdates_escape($token).'">';
            echo '<input type="hidden" name="ecloud_action" value="publish"><input type="hidden" name="release_id" value="'.(int) $row->id.'">';
            echo '<button class="btn btn-default btn-sm" type="submit">Publicar</button></form>';
        }
        echo '</td></tr>';
    }
    echo '</tbody></table></div>';
}
