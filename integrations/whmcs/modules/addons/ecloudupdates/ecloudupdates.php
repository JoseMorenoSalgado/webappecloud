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

function ecloudupdates_output($vars)
{
    $rows = \WHMCS\Database\Capsule::table('mod_ecloudupdates_releases')
        ->orderBy('id', 'desc')->limit(100)->get();
    echo '<div class="container-fluid"><h2>Elearning Cloud Update Manager</h2>';
    echo '<p>Gestión interna de versiones. La distribución de paquetes todavía no está habilitada.</p>';
    echo '<table class="table table-striped"><thead><tr><th>Versión</th><th>Canal</th><th>Título</th><th>Estado</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr><td>'.htmlspecialchars($row->version, ENT_QUOTES, 'UTF-8').'</td>';
        echo '<td>'.htmlspecialchars($row->channel, ENT_QUOTES, 'UTF-8').'</td>';
        echo '<td>'.htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8').'</td>';
        echo '<td>'.($row->published ? 'Publicada' : 'Borrador').'</td></tr>';
    }
    echo '</tbody></table></div>';
}
