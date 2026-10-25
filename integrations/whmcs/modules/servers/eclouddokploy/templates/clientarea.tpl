{* Output in the existing WHMCS product-details view. Lagom keeps the parent page. *}
<style>
{literal}
/* UI/UX Pro Max: Elearning Cloud "Operations Calm" — native Lagom, no framework. */
.ec-dokploy{
  --ecd-primary:var(--primary,#1761ac);--ecd-on-primary:#fff;
  --ecd-heading:var(--text-heading-color,#172d49);
  --ecd-text:var(--text-color,#263c55);
  --ecd-muted:var(--text-muted,#475c74);
  --ecd-border:var(--border-color,#d7e1ec);
  --ecd-surface:var(--panel-bg,#fff);
  --ecd-soft:#f5f8fc;--ecd-warn-bg:#fff8eb;
  --ecd-radius:14px;--ecd-focus:#155cbd;--ecd-danger:#aa302a;
  font-family:inherit;font-size:15px;line-height:1.55;color:var(--ecd-text);
  width:100%;min-width:0;max-width:100%;margin:12px 0 28px
}
.ec-dokploy *{box-sizing:border-box}
.ec-dokploy :is(button,input,select,textarea,summary,a){touch-action:manipulation}
.ec-dokploy :is(button,input,select,textarea,summary,a):focus-visible{
  outline:3px solid var(--ecd-focus);outline-offset:3px
}
.ec-dokploy a{overflow-wrap:anywhere}
.ec-dokploy p,.ec-dokploy ul{margin:0}
.ec-dokploy h2,.ec-dokploy h3{color:var(--ecd-heading);font-weight:730;letter-spacing:-.025em;margin:0}
.ec-dokploy h2{font-size:clamp(21px,3vw,25px);line-height:1.24}
.ec-dokploy h3{font-size:20px;line-height:1.3}
.ec-dokploy h4{font-size:15px;margin:0;color:var(--ecd-heading)}
.ec-dokploy .ecd-kicker{font-size:12px;letter-spacing:.09em;text-transform:uppercase;color:var(--ecd-muted);font-weight:750;margin:0 0 5px}
.ec-dokploy .ecd-intro{font-size:14px;color:var(--ecd-muted);max-width:72ch;line-height:1.55}
.ec-dokploy .ecd-header{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin:0 0 17px}
.ec-dokploy .ecd-status-chip,.ec-dokploy .ecd-chip{
  display:inline-flex;align-items:center;gap:8px;border:1px solid var(--ecd-border);
  border-radius:999px;padding:7px 11px;font-weight:700;font-size:12px;
  color:var(--ecd-heading);background:var(--ecd-surface);line-height:1.3
}
.ec-dokploy .ecd-status-chip::before{content:"";width:8px;height:8px;flex:0 0 8px;border-radius:50%;background:var(--ecd-primary)}
.ec-dokploy .ecd-jumpnav{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 18px}
.ec-dokploy .ecd-jumpnav a{
 display:inline-flex;min-height:44px;align-items:center;justify-content:center;
 padding:9px 15px;border-radius:10px;border:1px solid var(--ecd-border);
 color:var(--ecd-heading);background:var(--ecd-surface);font-size:14px;font-weight:700;text-decoration:none
}
.ec-dokploy .ecd-jumpnav a:hover{border-color:var(--ecd-primary);color:var(--ecd-primary)}
.ec-dokploy :is(#ecd-status,#ecd-instalar,#ecd-detalles,#correo-corporativo){scroll-margin-top:24px}
.ec-dokploy .ecd-overview,.ec-dokploy .ecd-card,.ec-dokploy .ecd-setup,.ec-dokploy .ecd-technical,.ec-dokploy .ecd-mail-panel{
  border:1px solid var(--ecd-border);border-radius:var(--ecd-radius);background:var(--ecd-surface);
  padding:20px;box-shadow:0 2px 12px rgba(24,57,95,.025);min-width:0
}
.ec-dokploy .ecd-overview{margin-bottom:16px}
.ec-dokploy .ecd-overview-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap}
.ec-dokploy .ecd-overview strong{display:block;font-size:19px;line-height:1.35;font-weight:760;color:var(--ecd-heading);margin:4px 0 5px}
.ec-dokploy .ecd-overview p{font-size:14px;color:var(--ecd-muted)}
.ec-dokploy .ecd-overview-extra{margin-top:12px;font-size:13px}
.ec-dokploy .ecd-overview-extra strong{display:inline;font-size:inherit}
.ec-dokploy .ecd-journey{list-style:none;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;padding:0;margin:18px 0 0}
.ec-dokploy .ecd-journey li{
 display:flex;gap:8px;align-items:center;min-height:46px;
 border:1px solid var(--ecd-border);border-radius:10px;padding:8px 10px;
 font-size:13px;color:var(--ecd-muted);font-weight:660
}
.ec-dokploy .ecd-journey .ecd-number{
 display:inline-flex;width:25px;height:25px;align-items:center;justify-content:center;
 background:var(--ecd-soft);border:1px solid var(--ecd-border);border-radius:50%;
 font-size:12px;font-weight:800;color:var(--ecd-heading);flex:0 0 25px
}
.ec-dokploy .ecd-journey .is-done{color:var(--ecd-heading);border-color:var(--ecd-primary)}
.ec-dokploy .ecd-journey .is-done .ecd-number{background:var(--ecd-primary);color:#fff;border-color:var(--ecd-primary)}
.ec-dokploy .ecd-journey .is-current{background:var(--ecd-soft);border-color:var(--ecd-primary);color:var(--ecd-heading)}
.ec-dokploy .ecd-journey li span:last-child{min-width:0;overflow-wrap:break-word}
.ec-dokploy .ecd-overview-disclaimer{margin-top:12px!important;font-size:13px!important}
.ec-dokploy .ecd-setup{margin:0 0 16px;padding:22px}
.ec-dokploy .ecd-setup-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:16px}
.ec-dokploy .ecd-setup-top h3{font-size:21px;margin-bottom:5px}
.ec-dokploy .ecd-primary-form{margin-top:12px}
.ec-dokploy .ecd-main-field{margin:0 0 12px}
.ec-dokploy .ecd-field-label{display:block;color:var(--ecd-heading);font-weight:700;font-size:14px;margin-bottom:7px}
.ec-dokploy .ecd-setup-label{display:flex;flex-direction:column;gap:6px;min-width:0;font-size:14px;color:var(--ecd-heading);font-weight:700}
.ec-dokploy .ecd-setup-input,.ec-dokploy .ecd-mail-form input{
 display:block;width:100%;min-width:0;min-height:46px;padding:10px 13px;
 border:1px solid var(--ecd-border);border-radius:10px;background:var(--ecd-surface);
 color:var(--ecd-text);font:inherit;font-size:16px;line-height:1.45
}
.ec-dokploy .ecd-setup-input:focus,.ec-dokploy .ecd-mail-form input:focus{border-color:var(--ecd-primary)}
.ec-dokploy .ecd-setup-input[aria-invalid="true"]{border-color:var(--ecd-danger)}
.ec-dokploy textarea.ecd-setup-input{min-height:112px;resize:vertical}
.ec-dokploy .ecd-setup-hint,.ec-dokploy .ecd-mail-muted{font-size:13px;color:var(--ecd-muted);line-height:1.5;font-weight:400}
.ec-dokploy .ecd-field-error{display:none;margin-top:6px;font-size:13px;color:var(--ecd-danger);font-weight:700}
.ec-dokploy .ecd-field-error:not(:empty){display:block}
.ec-dokploy .ecd-form-error{display:none;border:1px solid var(--ecd-danger);border-radius:10px;padding:10px 13px;margin:12px 0;color:var(--ecd-danger);font-size:14px}
.ec-dokploy .ecd-form-error:not(:empty){display:block}
.ec-dokploy .ecd-progress-note{display:flex;align-items:center;gap:9px;margin:10px 0 14px;color:var(--ecd-muted);font-size:13px}
.ec-dokploy .ecd-progress-note::before{content:"";display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--ecd-primary);flex-shrink:0}
.ec-dokploy .ecd-advanced,.ec-dokploy .ecd-technical,.ec-dokploy .ecd-mail-action{
 border:1px solid var(--ecd-border);border-radius:11px;background:var(--ecd-surface)
}
.ec-dokploy .ecd-advanced{margin:16px 0;padding:0}
.ec-dokploy :is(.ecd-advanced,.ecd-technical,.ecd-mail-action)>summary{
 display:flex;align-items:center;justify-content:space-between;gap:14px;
 min-height:48px;padding:11px 14px;list-style:none;cursor:pointer;
 font-size:14px;font-weight:740;color:var(--ecd-heading)
}
.ec-dokploy :is(.ecd-advanced,.ecd-technical,.ecd-mail-action)>summary::-webkit-details-marker{display:none}
.ec-dokploy :is(.ecd-advanced,.ecd-technical,.ecd-mail-action)>summary::after{
 content:"+";font-size:22px;font-weight:500;line-height:1;color:var(--ecd-muted)
}
.ec-dokploy :is(.ecd-advanced,.ecd-technical,.ecd-mail-action)[open]>summary::after{content:"−"}
.ec-dokploy .ecd-advanced-inner{border-top:1px solid var(--ecd-border);padding:14px}
.ec-dokploy .ecd-setup-grid,.ec-dokploy .ecd-mail-fields,.ec-dokploy .ecd-review-grid{
 display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px
}
.ec-dokploy .ecd-setup-wide{grid-column:1/-1}
.ec-dokploy .ecd-setup-detection{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-top:12px}
.ec-dokploy .ecd-setup-detect-button{
 border:1px solid var(--ecd-border);border-radius:9px;min-height:44px;padding:10px 14px;
 color:var(--ecd-heading);background:var(--ecd-surface);font:inherit;font-weight:700;cursor:pointer
}
.ec-dokploy .ecd-setup-detect-button:disabled{cursor:wait;opacity:.65}
.ec-dokploy .ecd-setup-detect-result{font-size:13px;color:var(--ecd-muted);line-height:1.4}
.ec-dokploy .ecd-review-grid{margin:14px 0}
.ec-dokploy .ecd-review-box{min-width:0;background:var(--ecd-soft);border:1px solid var(--ecd-border);border-radius:11px;padding:13px;overflow-wrap:anywhere}
.ec-dokploy .ecd-review-box small{display:block;font-size:12px;color:var(--ecd-muted);font-weight:600;margin-bottom:4px}
.ec-dokploy .ecd-review-box strong{display:block;color:var(--ecd-heading);font-size:14px;font-weight:750}
.ec-dokploy .ecd-setup-note{background:var(--ecd-soft);border-left:3px solid var(--ecd-primary);padding:10px 13px;border-radius:0 8px 8px 0;color:var(--ecd-text);font-size:13px;line-height:1.55;margin:14px 0}
.ec-dokploy .ecd-setup-check,.ec-dokploy .ecd-confirm,.ec-dokploy .ecd-mail-checkbox{display:flex!important;align-items:flex-start;gap:10px;font-size:14px;line-height:1.45;cursor:pointer;margin:14px 0}
.ec-dokploy :is(.ecd-setup-check,.ecd-confirm,.ecd-mail-checkbox)>input{
 margin:2px 0 0;min-width:19px;width:19px;height:19px;accent-color:var(--ecd-primary);flex-shrink:0
}
.ec-dokploy .ecd-setup-cta,.ec-dokploy .ecd-action,.ec-dokploy .ecd-mail-btn{
 display:inline-flex;align-items:center;justify-content:center;gap:8px;
 min-height:48px;padding:11px 17px;border-radius:10px;border:1px solid var(--ecd-primary);
 background:var(--ecd-primary);color:var(--ecd-on-primary)!important;
 font:inherit;font-size:15px;font-weight:750;text-align:center;text-decoration:none!important;
 cursor:pointer;transition:filter .16s ease,border-color .16s ease,background-color .16s ease
}
.ec-dokploy .ecd-setup-cta{width:100%}
.ec-dokploy :is(.ecd-setup-cta,.ecd-action,.ecd-mail-btn):hover:not(:disabled){filter:brightness(.92)}
.ec-dokploy :is(.ecd-setup-cta,.ecd-action,.ecd-mail-btn):disabled{cursor:wait;opacity:.7}
.ec-dokploy .ecd-action-alt{background:transparent;color:var(--ecd-primary)!important;border-color:var(--ecd-border)}
.ec-dokploy .ecd-mail-danger{background:var(--ecd-danger);border-color:var(--ecd-danger)}
.ec-dokploy .ecd-actions{display:flex;gap:9px;flex-wrap:wrap;margin:14px 0 0}
.ec-dokploy .ecd-technical{margin:0 0 16px;padding:0}
.ec-dokploy .ecd-technical-content{padding:0 16px 16px}
.ec-dokploy .ecd-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px}
.ec-dokploy .ecd-card{padding:15px}
.ec-dokploy .ecd-card h4{color:var(--ecd-muted);font-size:13px;font-weight:650;margin:0 0 5px}
.ec-dokploy .ecd-value{font-weight:760;font-size:15px;color:var(--ecd-heading);overflow-wrap:anywhere}
.ec-dokploy .ecd-note{font-size:13px;color:var(--ecd-muted);margin-top:5px!important}
.ec-dokploy .ecd-deployments{list-style:none;padding:0;margin:0}
.ec-dokploy .ecd-deployments li{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:9px;padding:12px 0;border-bottom:1px solid var(--ecd-border)}
.ec-dokploy .ecd-deployments li:last-child{border-bottom:0}
.ec-dokploy .ecd-deployments small{font-size:13px;color:var(--ecd-muted)}
.ec-dokploy .ecd-ops{margin:0 0 16px}
.ec-dokploy .ecd-ops h3{font-size:18px;margin-bottom:7px}
.ec-dokploy .ecd-mail-panel{margin:0 0 16px;padding:20px}
.ec-dokploy .ecd-mail-head{display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin:0 0 15px}
.ec-dokploy .ecd-mail-list{display:grid;gap:10px}
.ec-dokploy .ecd-mail-item{background:var(--ecd-soft);border:1px solid var(--ecd-border);border-radius:11px;padding:14px}
.ec-dokploy .ecd-mail-item-header{display:flex;justify-content:space-between;flex-wrap:wrap;gap:9px}
.ec-dokploy .ecd-mail-address{font-size:15px;font-weight:760;color:var(--ecd-heading);overflow-wrap:anywhere}
.ec-dokploy .ecd-mail-form{display:grid;gap:12px;padding:0 13px 14px}
.ec-dokploy .ecd-mail-form label{display:grid;gap:6px;font-size:14px;font-weight:650}
.ec-dokploy .ecd-mail-action{margin-top:10px}
.ec-dokploy .ecd-mail-btn{width:max-content;max-width:100%}
.ec-dokploy .ecd-smallprint{font-size:13px;color:var(--ecd-muted);margin-top:14px!important}
.ec-dokploy .ecd-danger-note{border-left:3px solid #ad6824;background:var(--ecd-warn-bg);padding:12px 14px;margin-top:12px}
.ec-dokploy .ecd-icon-dot{display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--ecd-primary)}
@media(max-width:720px){
  .ec-dokploy{font-size:16px}
  .ec-dokploy .ecd-overview,.ec-dokploy .ecd-card,.ec-dokploy .ecd-setup,.ec-dokploy .ecd-mail-panel{padding:15px}
  .ec-dokploy .ecd-grid,.ec-dokploy .ecd-setup-grid,.ec-dokploy .ecd-review-grid,.ec-dokploy .ecd-mail-fields{grid-template-columns:1fr}
  .ec-dokploy .ecd-setup-wide{grid-column:auto}
  .ec-dokploy .ecd-journey li{flex-direction:column;align-items:flex-start;gap:5px;font-size:12px;padding:9px}
  .ec-dokploy .ecd-actions{display:grid;grid-template-columns:1fr}
  .ec-dokploy .ecd-mail-btn{width:100%}
}
@media(max-width:365px){
  .ec-dokploy .ecd-journey{gap:5px}
  .ec-dokploy .ecd-journey li{font-size:11px;padding:7px}
  .ec-dokploy .ecd-jumpnav a{flex:1;min-width:0;padding:8px}
}
@media(prefers-reduced-motion:reduce){.ec-dokploy *{transition:none!important;animation:none!important}}
html[data-theme="dark"] .ec-dokploy,.dark .ec-dokploy,[data-theme="dark"] .ec-dokploy{
 --ecd-surface:#172338;--ecd-soft:#203047;--ecd-border:#3f5067;
 --ecd-heading:#f0f6ff;--ecd-text:#e5eef8;--ecd-muted:#d3dfeb;
 --ecd-primary:#7cb7ff;--ecd-on-primary:#0b2748;--ecd-focus:#a0caff;
 --ecd-warn-bg:#352b19;--ecd-danger:#ff8b85
}
{/literal}
</style>
<section class="ec-dokploy" aria-label="Administración de aplicación web">
  <header class="ecd-header">
    <div>
      <p class="ecd-kicker">Elearning Cloud · Hosting de aplicaciones</p>
      <h2>Mi aplicación web</h2>
      <p class="ecd-intro">Instala, consulta y administra tu proyecto desde un solo lugar.</p>
    </div>
    <span class="ecd-status-chip">Servicio #{$ecDokployServiceId|escape:'html'}</span>
  </header>
  {if !$ecDokployAuthorized}
    <div class="alert alert-warning" role="alert">
      No podemos verificar los permisos de esta cuenta. Vuelve a Mis servicios o solicita ayuda a soporte.
    </div>
  {else}
    <nav class="ecd-jumpnav" aria-label="Secciones del servicio">
      <a href="#ecd-status">Estado</a>
      <a href="#ecd-instalar">Mi aplicación</a>
      <a href="#correo-corporativo">Correo</a>
    </nav>
    <section class="ecd-overview" id="ecd-status" aria-labelledby="ecd-status-title">
      <div class="ecd-overview-head">
        <div>
          <p class="ecd-kicker">Estado de instalación</p>
          <strong id="ecd-status-title">{$ecDokployState|escape:'html'}</strong>
          <p>{$ecDokployDescription|escape:'html'}</p>
        </div>
        {if $ecDokployDomain}
          <span class="ecd-status-chip">{$ecDokployDomain|escape:'html'}</span>
        {/if}
      </div>
      <ol class="ecd-journey" aria-label="Etapas de instalación">
        <li class="{if $ecDokployPhase >= 1}is-done{else}is-current{/if}">
          <span class="ecd-number" aria-hidden="true">1</span>
          <span>Proyecto privado</span>
        </li>
        <li class="{if $ecDokployPhase >= 2}is-done{elseif $ecDokployPhase == 1}is-current{/if}">
          <span class="ecd-number" aria-hidden="true">2</span>
          <span>Aplicación y datos</span>
        </li>
        <li class="{if $ecDokployPhase >= 3}is-done{elseif $ecDokployPhase == 2}is-current{/if}">
          <span class="ecd-number" aria-hidden="true">3</span>
          <span>Solicitud de despliegue</span>
        </li>
      </ol>
      <p class="ecd-overview-disclaimer">Este progreso indica operaciones registradas, no una comprobación de que la web esté en línea. DNS, HTTPS y salud se verifican por separado.</p>
    </section>
    {if $ecDokployLiveAvailable}
      <div class="ecd-setup-note" role="status">
        <strong>Última consulta a Dokploy: {$ecDokployLiveState|escape:'html'}</strong>
        <p>{$ecDokployLiveDescription|escape:'html'}</p>
      </div>
    {elseif $ecDokployLiveNotice}
      <div class="alert alert-info" role="status">{$ecDokployLiveNotice|escape:'html'}</div>
    {/if}

    {if $ecSetupEnabled}
      <section class="ecd-setup" id="ecd-instalar" aria-labelledby="ecd-install-title">
        <div class="ecd-setup-top">
          <div>
            <p class="ecd-kicker">Tu siguiente paso</p>
            <h3 id="ecd-install-title">
              {if $ecSetupConfigured}Repositorio configurado
              {elseif $ecSetupCanInstall}Importa tu proyecto desde GitHub
              {elseif $ecDokployPhase >= 2}Instalación en proceso
              {else}Configuración pendiente{/if}
            </h3>
            <p class="ecd-intro">
              {if $ecSetupConfigured}
                Tu código está asociado a este servicio. No necesitas instalarlo nuevamente.
              {elseif $ecSetupCanInstall}
                Pega tu repositorio público y Elearning Cloud se encarga de preparar la aplicación y su base de datos.
              {else}
                Estamos revisando la instalación. Puedes consultar el estado o pedir ayuda a nuestro equipo.
              {/if}
            </p>
          </div>
          {if $ecSetupConfigured}<span class="ecd-chip">Origen guardado</span>
          {elseif $ecSetupCanInstall}<span class="ecd-chip">Sin configuración técnica obligatoria</span>
          {else}<span class="ecd-chip">En revisión</span>{/if}
        </div>
        {if $ecSetupConfigured}
          <div class="ecd-review-grid" aria-label="Resumen del repositorio">
            <div class="ecd-review-box"><small>Repositorio</small><strong>{$ecSetupGitUrl|escape:'html'}</strong></div>
            <div class="ecd-review-box"><small>Rama</small><strong>{$ecSetupBranch|escape:'html'}</strong></div>
            <div class="ecd-review-box"><small>Tipo de proyecto</small><strong>{$ecSetupPreset|escape:'html'}</strong></div>
            <div class="ecd-review-box"><small>Carpeta y puerto</small><strong>{$ecSetupRoot|escape:'html'} · {$ecSetupPort|escape:'html'}</strong></div>
          </div>
          {if $ecSetupCanInstall}
            <p class="ecd-setup-note">La configuración quedó guardada. Si todavía no empezó la instalación, puedes solicitarla nuevamente con los mismos valores.</p>
            <form method="post" data-ecd-submit-form action="clientarea.php?action=productdetails&amp;id={$ecDokployServiceId|escape:'html'}">
              <input type="hidden" name="id" value="{$ecDokployServiceId|escape:'html'}">
              <input type="hidden" name="modop" value="custom">
              <input type="hidden" name="a" value="InstallApp">
              <input type="hidden" name="token" value="{$token|escape:'html'}">
              <input type="hidden" name="ec_setup_token" value="{$ecSetupToken|escape:'html'}">
              <input type="hidden" name="ec_setup_confirm" value="yes">
              <button type="submit" class="ecd-setup-cta" data-ecd-submit>Continuar instalación guardada</button>
              <p class="ecd-progress-note" data-ecd-submit-notice role="status" aria-live="polite">Solo se continuará si aún no existe una operación remota en curso.</p>
            </form>
          {else}
            <p class="ecd-setup-note">Se ha registrado la configuración. Consulta el estado de instalación; la plataforma no duplicará recursos.</p>
          {/if}
        {elseif $ecSetupCanInstall}
          <form id="ecd-install-wizard" data-ecd-submit-form method="post" action="clientarea.php?action=productdetails&amp;id={$ecDokployServiceId|escape:'html'}" novalidate>
            <input type="hidden" name="id" value="{$ecDokployServiceId|escape:'html'}">
            <input type="hidden" name="modop" value="custom">
            <input type="hidden" name="a" value="InstallApp">
            <input type="hidden" name="token" value="{$token|escape:'html'}">
            <input type="hidden" name="ec_setup_token" value="{$ecSetupToken|escape:'html'}">
            <div class="ecd-primary-form">
              <div class="ecd-main-field">
                <label class="ecd-field-label" for="ecd-repository">Enlace de tu repositorio GitHub</label>
                <input id="ecd-repository" class="ecd-setup-input" data-ecd-git name="ec_setup_git"
                       type="url" inputmode="url" placeholder="https://github.com/usuario/proyecto"
                       required maxlength="255" autocomplete="url" autocapitalize="none"
                       aria-describedby="ecd-repo-help ecd-repo-error" spellcheck="false">
                <p id="ecd-repo-help" class="ecd-setup-hint">Debe ser un repositorio público. No necesitas compartir tu contraseña ni una clave API.</p>
                <p id="ecd-repo-error" class="ecd-field-error" data-ecd-repo-error aria-live="polite"></p>
              </div>
              <div class="ecd-setup-detection">
                <button type="button" class="ecd-setup-detect-button" data-ecd-scan>Identificar framework</button>
                <span data-ecd-detection class="ecd-setup-detect-result" role="status" aria-live="polite">Opcional: intentaremos detectar Next.js o Node.js.</span>
              </div>
            </div>
            <details class="ecd-advanced" data-ecd-advanced>
              <summary>Personalizar la instalación (opcional)</summary>
              <div class="ecd-advanced-inner">
                <div class="ecd-setup-grid">
                  <label class="ecd-setup-label">
                    Rama de GitHub
                    <input class="ecd-setup-input" data-ecd-branch name="ec_setup_branch"
                           type="text" value="main" required maxlength="100"
                           pattern="[A-Za-z0-9._/-]+" autocapitalize="none" spellcheck="false">
                    <span class="ecd-setup-hint">Por defecto: main.</span>
                  </label>
                  <label class="ecd-setup-label">
                    Carpeta raíz
                    <input class="ecd-setup-input" data-ecd-root name="ec_setup_root"
                           type="text" value="/" required maxlength="190"
                           autocapitalize="none" spellcheck="false">
                    <span class="ecd-setup-hint">Para monorepos, por ejemplo /apps/web.</span>
                  </label>
                  <label class="ecd-setup-label">
                    Tecnología
                    <select class="ecd-setup-input" data-ecd-preset name="ec_setup_preset">
                      <option value="auto" selected>Detectar automáticamente</option>
                      <option value="nextjs">Next.js · Nixpacks</option>
                      <option value="nodejs">Node.js · Nixpacks</option>
                      <option value="dockerfile">Dockerfile · Avanzado</option>
                    </select>
                    <span class="ecd-setup-hint">Next.js y Node.js no necesitan Dockerfile.</span>
                  </label>
                  <label class="ecd-setup-label">
                    Puerto interno
                    <input class="ecd-setup-input" name="ec_setup_port" type="number"
                           min="1024" max="65535" value="3000" required inputmode="numeric">
                    <span class="ecd-setup-hint">3000 por defecto para Next.js.</span>
                  </label>
                  <label class="ecd-setup-label ecd-setup-wide">
                    Variables de entorno (opcional)
                    <textarea class="ecd-setup-input" name="ec_setup_env" rows="4"
                              maxlength="6144" autocomplete="off" spellcheck="false"
                              placeholder="API_URL=https://api.example.com&#10;APP_SECRET=valor-privado"></textarea>
                    <span class="ecd-setup-hint">Formato CLAVE=valor, una por línea. Se cifran antes de guardarse. DATABASE_URL, PORT y NODE_ENV los administra Elearning Cloud. Las variables NEXT_PUBLIC_ son públicas.</span>
                  </label>
                </div>
              </div>
            </details>
            <div class="ecd-review-grid" aria-label="Configuración de tu alojamiento">
              <div class="ecd-review-box">
                <small>Dominio del servicio</small>
                <strong>{$ecSetupDomain|escape:'html'}</strong>
              </div>
              <div class="ecd-review-box">
                <small>Base de datos incluida</small>
                <strong>{$ecSetupDbMode|escape:'html'}</strong>
              </div>
            </div>
            <p class="ecd-setup-note">La publicación requiere DNS correcto. Solicitar un despliegue no garantiza que la web ya esté operativa; podrás comprobar su estado desde este panel.</p>
            <div class="ecd-form-error" data-ecd-form-error role="alert" tabindex="-1"></div>
            <label class="ecd-setup-check">
              <input type="checkbox" name="ec_setup_confirm" value="yes" required data-ecd-confirm>
              <span>Confirmo que tengo autorización para publicar este repositorio.</span>
            </label>
            <button class="ecd-setup-cta" type="submit" data-ecd-submit>
              Instalar mi aplicación
            </button>
            <p class="ecd-progress-note" role="status" aria-live="polite" data-ecd-submit-notice>El sistema utilizará el proyecto privado asociado a este servicio.</p>
          </form>
        {else}
          <p class="ecd-setup-note">Todavía no está habilitada la instalación para este servicio. Solicita asistencia y revisaremos el estado sin duplicar proyectos.</p>
          <div class="ecd-actions"><a class="ecd-action ecd-action-alt" href="submitticket.php">Solicitar ayuda para instalar</a></div>
        {/if}
      </section>
    {/if}
    <details class="ecd-technical" id="ecd-detalles">
      <summary>Detalles técnicos de mi alojamiento</summary>
      <div class="ecd-technical-content">
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
      </div>
    </details>
    {if $ecDokployLiveAvailable}
      <div class="ecd-card ecd-ops" id="ecd-historial">
        <h3>Historial de despliegues</h3>
        {if $ecDokployHistory}
          <ul class="ecd-deployments">
            {foreach from=$ecDokployHistory item=deployment}
              <li><strong>{$deployment.status|escape:'html'}</strong><small>{$deployment.date|escape:'html'}</small></li>
            {/foreach}
          </ul>
        {else}
          <p class="ecd-note">Dokploy no devolvió registros recientes de despliegue.</p>
        {/if}
      </div>
    {/if}
    {if $ecDokployCanRedeploy}
      <div class="ecd-card ecd-ops">
        <h3>Actualizar desde Git</h3>
        <p class="ecd-note">Se solicitará un nuevo despliegue de la rama configurada.
        El sitio podría experimentar una interrupción durante el proceso.</p>
        <form method="post" action="clientarea.php?action=productdetails&id={$ecDokployServiceId|escape:'html'}">
          <input type="hidden" name="id" value="{$ecDokployServiceId|escape:'html'}">
          <input type="hidden" name="modop" value="custom">
          <input type="hidden" name="a" value="Redeploy">
          <input type="hidden" name="token" value="{$token|escape:'html'}">
          <input type="hidden" name="ec_redeploy_token" value="{$ecDokployCsrf|escape:'html'}">
          <label class="ecd-confirm">
            <input type="checkbox" name="ec_confirm" value="yes" required>
            <span>Confirmo que deseo solicitar otro despliegue de mi aplicación.</span>
          </label>
          <button type="submit" class="ecd-action ecd-action-button">Volver a desplegar desde Git</button>
        </form>
        <p class="ecd-smallprint">Máximo una solicitud cada 15 minutos. El resultado se confirma desde el historial.</p>
      </div>
    {/if}

    <section class="ecd-mail-panel" id="correo-corporativo" aria-label="Correo corporativo">
      <header class="ecd-mail-head">
        <div>
          <p class="ecd-kicker">Elearning Cloud · Hosting de correo</p>
          <h3>Correo corporativo</h3>
          <p class="ecd-intro">Tus buzones funcionan en cPanel, independientemente de la aplicación alojada en Dokploy.</p>
        </div>
        {if $ecMailAvailable && $ecMailWebmailUrl}
          <a class="ecd-action ecd-action-alt" href="{$ecMailWebmailUrl|escape:'html'}" target="_blank" rel="noopener noreferrer">Abrir Webmail</a>
        {/if}
      </header>
      {if !$ecMailLinked}
        <div class="ecd-overview">
          {if $ecMailAutoLink}
            <strong>Tu correo corporativo está disponible</strong>
            <p>Encontramos automáticamente el hosting de correo {$ecMailAutoAccount|escape:'html'} en tu cuenta.</p>
            <div class="ecd-actions">
              <a class="ecd-action" href="{$ecMailAutoLink|escape:'html'}">Administrar correo en WHMCS</a>
            </div>
            <p class="ecd-smallprint">El correo está alojado en cPanel y se administra con el mismo inicio de sesión WHMCS.</p>
          {else}
            <strong>Agrega correo corporativo a tu web</strong>
            <p>Si contratas un servicio cPanel para este dominio, se enlazará automáticamente a esta ficha. Sin configuraciones de acceso adicionales.</p>
            <p style="margin-top:10px"><a href="submitticket.php">Solicitar correo corporativo</a></p>
          {/if}
        </div>
      {elseif !$ecMailAvailable}
        <div class="alert alert-info" role="status">{$ecMailNotice|escape:'html'}</div>
      {else}
        <div class="ecd-grid">
          <div class="ecd-card">
            <h4>Dominio del correo</h4>
            <p class="ecd-value">{$ecMailDomain|escape:'html'}</p>
          </div>
          <div class="ecd-card">
            <h4>Buzones utilizados</h4>
            <p class="ecd-value">{$ecMailAccountCount|escape:'html'} de {$ecMailMailboxLimit|escape:'html'}</p>
            <p class="ecd-note">Cuota máxima por buzón: {$ecMailMaxQuotaMb|escape:'html'} MB</p>
          </div>
        </div>
        {if $ecMailAccounts}
          <div class="ecd-mail-list">
            {foreach from=$ecMailAccounts item=mail}
              <div class="ecd-mail-item">
                <div class="ecd-mail-item-header">
                  <span class="ecd-mail-address">{$mail.address|escape:'html'}</span>
                  <span class="ecd-mail-muted">Cuota: {$mail.quota|escape:'html'}</span>
                </div>
                {if $ecMailCanWrite}
                  <details class="ecd-mail-action">
                    <summary>Cambiar contraseña</summary>
                    <form class="ecd-mail-form" method="post" action="clientarea.php?action=productdetails&id={$ecDokployServiceId|escape:'html'}">
                      <input type="hidden" name="id" value="{$ecDokployServiceId|escape:'html'}">
                      <input type="hidden" name="modop" value="custom">
                      <input type="hidden" name="a" value="EmailAction">
                      <input type="hidden" name="ec_mail_op" value="password">
                      <input type="hidden" name="ec_mail_local" value="{$mail.local|escape:'html'}">
                      <input type="hidden" name="token" value="{$token|escape:'html'}">
                      <input type="hidden" name="ec_mail_csrf" value="{$ecMailToken|escape:'html'}">
                      <div class="ecd-mail-fields">
                        <label>Contraseña nueva
                          <input name="ec_mail_password" type="password" required minlength="14" maxlength="128" autocomplete="new-password">
                        </label>
                        <label>Repetir contraseña
                          <input name="ec_mail_confirm_password" type="password" required minlength="14" maxlength="128" autocomplete="new-password">
                        </label>
                      </div>
                      <p class="ecd-mail-muted">Mínimo 14 caracteres: mayúscula, minúscula, número y símbolo.</p>
                      <button class="ecd-mail-btn" type="submit">Actualizar contraseña</button>
                    </form>
                  </details>
                  <details class="ecd-mail-action">
                    <summary>Modificar cuota</summary>
                    <form class="ecd-mail-form" method="post" action="clientarea.php?action=productdetails&id={$ecDokployServiceId|escape:'html'}">
                      <input type="hidden" name="id" value="{$ecDokployServiceId|escape:'html'}">
                      <input type="hidden" name="modop" value="custom">
                      <input type="hidden" name="a" value="EmailAction">
                      <input type="hidden" name="ec_mail_op" value="quota">
                      <input type="hidden" name="ec_mail_local" value="{$mail.local|escape:'html'}">
                      <input type="hidden" name="token" value="{$token|escape:'html'}">
                      <input type="hidden" name="ec_mail_csrf" value="{$ecMailToken|escape:'html'}">
                      <label>Cuota en MB (100 a {$ecMailMaxQuotaMb|escape:'html'})
                        <input name="ec_mail_quota" type="number" min="100" max="{$ecMailMaxQuotaMb|escape:'html'}" value="{$ecMailMaxQuotaMb|escape:'html'}" required>
                      </label>
                      <button class="ecd-mail-btn" type="submit">Guardar cuota</button>
                    </form>
                  </details>
                  {if $ecMailCanDelete}
                    <details class="ecd-mail-action">
                      <summary>Eliminar buzón (permanente)</summary>
                      <form class="ecd-mail-form" method="post" action="clientarea.php?action=productdetails&id={$ecDokployServiceId|escape:'html'}">
                        <input type="hidden" name="id" value="{$ecDokployServiceId|escape:'html'}">
                        <input type="hidden" name="modop" value="custom">
                        <input type="hidden" name="a" value="EmailAction">
                        <input type="hidden" name="ec_mail_op" value="delete">
                        <input type="hidden" name="ec_mail_local" value="{$mail.local|escape:'html'}">
                        <input type="hidden" name="token" value="{$token|escape:'html'}">
                        <input type="hidden" name="ec_mail_csrf" value="{$ecMailToken|escape:'html'}">
                        <p class="ecd-mail-muted">Esta acción puede borrar permanentemente todos los mensajes de {$mail.address|escape:'html'}.</p>
                        <label>Escribe: ELIMINAR {$mail.address|escape:'html'}
                          <input name="ec_mail_delete_confirmation" type="text" autocomplete="off" required>
                        </label>
                        <label class="ecd-mail-checkbox">
                          <input type="checkbox" name="ec_mail_ack" value="yes" required>
                          Confirmo que entiendo que el buzón y sus mensajes se eliminarán.
                        </label>
                        <button class="ecd-mail-btn ecd-mail-danger" type="submit">Eliminar buzón permanentemente</button>
                      </form>
                    </details>
                  {/if}
                {/if}
              </div>
            {/foreach}
          </div>
        {else}
          <div class="ecd-overview"><strong>Aún no hay buzones</strong><p>Puedes crear el primero si tu servicio de correo permite administrar cuentas.</p></div>
        {/if}
        {if $ecMailCanWrite && $ecMailAccountCount < $ecMailMailboxLimit}
          <details class="ecd-mail-action" open>
            <summary>Crear nuevo buzón</summary>
            <form class="ecd-mail-form" method="post" action="clientarea.php?action=productdetails&id={$ecDokployServiceId|escape:'html'}">
              <input type="hidden" name="id" value="{$ecDokployServiceId|escape:'html'}">
              <input type="hidden" name="modop" value="custom">
              <input type="hidden" name="a" value="EmailAction">
              <input type="hidden" name="ec_mail_op" value="create">
              <input type="hidden" name="token" value="{$token|escape:'html'}">
              <input type="hidden" name="ec_mail_csrf" value="{$ecMailToken|escape:'html'}">
              <div class="ecd-mail-fields">
                <label>Nombre de la cuenta
                  <input name="ec_mail_local" type="text" placeholder="ventas" pattern="[a-zA-Z0-9._-]+" required maxlength="63" autocapitalize="none" autocomplete="off">
                </label>
                <label>Dominio (fijo)
                  <input type="text" value="@{$ecMailDomain|escape:'html'}" disabled>
                </label>
                <label>Contraseña
                  <input name="ec_mail_password" type="password" minlength="14" maxlength="128" required autocomplete="new-password">
                </label>
                <label>Confirmar contraseña
                  <input name="ec_mail_confirm_password" type="password" minlength="14" maxlength="128" required autocomplete="new-password">
                </label>
                <label>Cuota en MB
                  <input name="ec_mail_quota" type="number" min="100" max="{$ecMailMaxQuotaMb|escape:'html'}" value="{$ecMailMaxQuotaMb|escape:'html'}" required>
                </label>
              </div>
              <p class="ecd-mail-muted">Contraseña de al menos 14 caracteres, con mayúscula, minúscula, número y símbolo. No se almacenará en WHMCS.</p>
              <button class="ecd-mail-btn" type="submit">Crear cuenta de correo</button>
            </form>
          </details>
        {elseif !$ecMailCanWrite}
          <div class="alert alert-info" role="status">La consulta de correo está disponible. Crear o modificar buzones requiere habilitación administrativa.</div>
        {/if}
      {/if}
    </section>
    <div class="ecd-actions">
      {if $ecDokployProvisioned && $ecDokploySiteUrl}
        <a class="ecd-action" href="{$ecDokploySiteUrl|escape:'html'}" target="_blank" rel="noopener noreferrer">
          Visitar dominio configurado
        </a>
      {/if}
      <a class="ecd-action ecd-action-alt" href="submitticket.php">Solicitar asistencia</a>
    </div>
    <p class="ecd-smallprint">Los cambios del servicio se registran en WHMCS. La disponibilidad pública de la aplicación requiere comprobar el despliegue, el dominio y HTTPS.</p>
  {/if}
</section>

<script>
{literal}
(function () {
  const wrap = document.querySelector('.ec-dokploy');
  const form = wrap && wrap.querySelector('#ecd-install-wizard');
  if (!form) return;
  const git = form.querySelector('[data-ecd-git]');
  const branch = form.querySelector('[data-ecd-branch]');
  const root = form.querySelector('[data-ecd-root]');
  const preset = form.querySelector('[data-ecd-preset]');
  const button = form.querySelector('[data-ecd-scan]');
  const status = form.querySelector('[data-ecd-detection]');
  if (!button) return;
  button.addEventListener('click', async function () {
    const match = /^https:\/\/github\.com\/([A-Za-z0-9_.-]+)\/([A-Za-z0-9_.-]+?)(?:\.git)?\/?$/.exec(git.value.trim());
    const currentBranch = branch.value.trim();
    const path = root.value.trim().replace(/^\/+|\/+$/g, '');
    if (!match || !/^[A-Za-z0-9._/-]{1,100}$/.test(currentBranch)
        || path.split('/').some(function(p){return p==='..'||p==='.';})
        || !/^[A-Za-z0-9_.\/-]*$/.test(path)) {
      status.textContent = 'Introduce una URL de GitHub, rama y carpeta válidas.';
      return;
    }
    button.disabled = true;
    status.textContent = 'Buscando package.json público en GitHub…';
    const base = 'https://api.github.com/repos/' + encodeURIComponent(match[1])
      + '/' + encodeURIComponent(match[2]) + '/contents/'
      + (path ? path.split('/').map(encodeURIComponent).join('/') + '/' : '');
    try {
      const resp = await fetch(base + 'package.json?ref=' + encodeURIComponent(currentBranch), {
        headers: {'Accept':'application/vnd.github+json'},
        credentials:'omit', redirect:'error'
      });
      if (resp.ok) {
        const info = await resp.json();
        const encoded = (info.content || '').replace(/\s+/g, '');
        const pkg = JSON.parse(decodeURIComponent(Array.prototype.map.call(atob(encoded), function (c) {
          return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
        }).join('')));
        const deps = Object.assign({}, pkg.dependencies || {}, pkg.devDependencies || {});
        if (Object.prototype.hasOwnProperty.call(deps, 'next')) {
          preset.value = 'nextjs';
          status.textContent = 'Next.js detectado. Configuración automática lista.';
        } else {
          preset.value = 'nodejs';
          status.textContent = 'Proyecto Node.js detectado. Configuración automática lista.';
        }
      } else {
        status.textContent = 'No se encontró package.json. Elige Dockerfile si tu repositorio lo incluye.';
      }
    } catch (e) {
      status.textContent = 'No se pudo consultar GitHub. Elige el tipo de proyecto manualmente.';
    } finally {
      button.disabled = false;
    }
  });
})();
{/literal}
</script>
