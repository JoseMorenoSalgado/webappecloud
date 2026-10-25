{* Output in the existing WHMCS product-details view. Lagom keeps the parent page. *}
<style>
{literal}
.ec-dokploy{--ecd-text:var(--text-color,#203047);--ecd-heading:var(--text-heading-color,#173258);--ecd-muted:var(--text-muted,#52637a);--ecd-border:var(--border-color,#dce4ed);--ecd-primary:var(--primary,#1762ab);color:var(--ecd-text);font:inherit;line-height:1.55;margin:16px 0 24px}
.ec-dokploy *{box-sizing:border-box}
.ec-dokploy h3{font-size:20px;line-height:1.3;color:var(--ecd-heading);font-weight:700;margin:0 0 6px}
.ec-dokploy p{margin:0}
.ec-dokploy .ecd-header{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:18px}
.ec-dokploy .ecd-kicker{font-size:12px;text-transform:uppercase;letter-spacing:.08em;font-weight:750;color:var(--ecd-muted);margin:0 0 7px}
.ec-dokploy .ecd-intro{color:var(--ecd-muted);font-size:14px}
.ec-dokploy .ecd-state{border:1px solid var(--ecd-border);background:var(--panel-bg,#fff);border-radius:12px;padding:18px 20px;margin-bottom:15px}
.ec-dokploy .ecd-state strong{color:var(--ecd-heading);font-size:16px;display:block;margin-bottom:4px}
.ec-dokploy .ecd-state p{color:var(--ecd-muted);font-size:14px}
.ec-dokploy .ecd-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:16px}
.ec-dokploy .ecd-card{border:1px solid var(--ecd-border);border-radius:12px;background:var(--panel-bg,#fff);padding:18px;min-width:0}
.ec-dokploy .ecd-card h4{margin:0 0 9px;color:var(--ecd-muted);font-size:13px;font-weight:700}
.ec-dokploy .ecd-card .ecd-value{font-size:15px;font-weight:700;color:var(--ecd-heading);overflow-wrap:anywhere;word-break:break-word}
.ec-dokploy .ecd-card .ecd-note{font-size:13px;color:var(--ecd-muted);margin-top:6px}
.ec-dokploy .ecd-actions{display:flex;flex-wrap:wrap;gap:10px;margin:18px 0 0}
.ec-dokploy .ecd-action{display:inline-flex;align-items:center;justify-content:center;min-height:44px;border:1px solid var(--ecd-primary);border-radius:9px;padding:10px 16px;font-size:14px;font-weight:700;text-align:center;text-decoration:none!important;background:var(--ecd-primary);color:#fff!important}
.ec-dokploy .ecd-action:hover,.ec-dokploy .ecd-action:focus{filter:brightness(.88)}
.ec-dokploy .ecd-action-alt{color:var(--ecd-primary)!important;background:transparent;border-color:var(--ecd-border)}
.ec-dokploy .ecd-smallprint{margin-top:16px;color:var(--ecd-muted);font-size:13px}
.ec-dokploy a:focus-visible{outline:3px solid #579fef;outline-offset:3px}
@media(max-width:720px){.ec-dokploy .ecd-grid{grid-template-columns:1fr}.ec-dokploy .ecd-actions{display:grid;grid-template-columns:1fr}.ec-dokploy .ecd-state,.ec-dokploy .ecd-card{padding:16px}.ec-dokploy h3{font-size:19px}}
{/literal}
</style>
<section class="ec-dokploy" aria-label="Administración de aplicación web">
  <header class="ecd-header">
    <div>
      <p class="ecd-kicker">Elearning Cloud · Aplicaciones web</p>
      <h3>Administrar aplicación</h3>
      <p class="ecd-intro">Este servicio utiliza Dokploy y se administra por separado de tus cuentas de correo.</p>
    </div>
  </header>
  {if !$ecDokployAuthorized}
    <div class="alert alert-warning" role="status">
      No se pudo verificar el acceso a los detalles de esta aplicación. Contacta con soporte.
    </div>
  {else}
    <div class="ecd-state" role="status">
      <p class="ecd-kicker">Estado de instalación</p>
      <strong>{$ecDokployState|escape:'html'}</strong>
      <p>{$ecDokployDescription|escape:'html'}</p>
    </div>
    <div class="ecd-grid">
      <div class="ecd-card">
        <h4>Dominio de la aplicación</h4>
        <p class="ecd-value">{if $ecDokployDomain}{$ecDokployDomain|escape:'html'}{else}Pendiente de configurar{/if}</p>
        <p class="ecd-note">Sitio web alojado de forma independiente en Dokploy.</p>
      </div>
      <div class="ecd-card">
        <h4>Base de datos contratada</h4>
        <p class="ecd-value">{$ecDokployDbMode|escape:'html'}</p>
        <p class="ecd-note">Cada servicio mantiene su propia configuración.</p>
      </div>
      <div class="ecd-card">
        <h4>Repositorio Git</h4>
        <p class="ecd-value">
          {if $ecDokployGitUrl}
            <a href="{$ecDokployGitUrl|escape:'html'}" target="_blank" rel="noopener noreferrer">Ver repositorio</a>
          {else}No configurado{/if}
        </p>
        <p class="ecd-note">Origen del código de la aplicación.</p>
      </div>
      <div class="ecd-card">
        <h4>Rama de despliegue</h4>
        <p class="ecd-value">{if $ecDokployBranch}{$ecDokployBranch|escape:'html'}{else}No configurada{/if}</p>
        <p class="ecd-note">La rama se administra conforme al plan contratado.</p>
      </div>
    </div>
    <div class="ecd-actions">
      {if $ecDokployProvisioned && $ecDokploySiteUrl}
        <a class="ecd-action" href="{$ecDokploySiteUrl|escape:'html'}" target="_blank" rel="noopener noreferrer">
          Visitar dominio configurado
        </a>
      {/if}
      <a class="ecd-action ecd-action-alt" href="submitticket.php">Solicitar asistencia</a>
    </div>
    <p class="ecd-smallprint">
      El estado mostrado corresponde al aprovisionamiento registrado en WHMCS, no a un
      monitoreo en tiempo real. Las actualizaciones y acciones de Dokploy requieren
      validación antes de habilitarse para clientes.
    </p>
  {/if}
</section>
