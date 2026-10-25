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
.ec-dokploy .ecd-deployments{list-style:none;padding:0;margin:0}
.ec-dokploy .ecd-deployments li{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;padding:11px 0;border-bottom:1px solid var(--ecd-border)}
.ec-dokploy .ecd-deployments li:last-child{border-bottom:0}
.ec-dokploy .ecd-deployments strong{font-size:14px;color:var(--ecd-heading)}
.ec-dokploy .ecd-deployments small{font-size:13px;color:var(--ecd-muted)}
.ec-dokploy .ecd-action-button{font:inherit;cursor:pointer}
.ec-dokploy .ecd-confirm{display:flex;gap:10px;align-items:flex-start;font-size:14px;line-height:1.5;margin-bottom:12px}
.ec-dokploy .ecd-confirm input{width:18px;height:18px;flex-shrink:0;margin-top:2px;accent-color:var(--ecd-primary)}
.ec-dokploy .ecd-danger-note{background:var(--panel-bg,#fff);border-left:3px solid #ad6824;padding:12px 14px;margin-top:12px}
.ec-dokploy .ecd-ops{margin-top:16px}
.ec-dokploy a:focus-visible{outline:3px solid #579fef;outline-offset:3px}

.ec-dokploy .ecd-mail-panel{margin-top:20px;border-top:1px solid var(--ecd-border);padding-top:20px}
.ec-dokploy .ecd-mail-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px}
.ec-dokploy .ecd-mail-list{display:grid;gap:10px}
.ec-dokploy .ecd-mail-item{border:1px solid var(--ecd-border);border-radius:10px;padding:14px;background:var(--panel-bg,#fff)}
.ec-dokploy .ecd-mail-item-header{display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:8px}
.ec-dokploy .ecd-mail-address{overflow-wrap:anywhere;font-weight:700}
.ec-dokploy .ecd-mail-muted{font-size:13px;color:var(--ecd-muted)}
.ec-dokploy .ecd-mail-form{display:grid;gap:12px}
.ec-dokploy .ecd-mail-form label{display:grid;gap:5px;font-size:13px;font-weight:600}
.ec-dokploy .ecd-mail-form input{width:100%;min-width:0;border:1px solid var(--ecd-border);border-radius:7px;padding:10px 12px;min-height:43px;color:var(--ecd-text);background:var(--panel-bg,#fff)}
.ec-dokploy .ecd-mail-form input:focus-visible{outline:2px solid var(--ecd-primary);outline-offset:2px}
.ec-dokploy .ecd-mail-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.ec-dokploy .ecd-mail-action{border:1px solid var(--ecd-border);border-radius:9px;padding:10px 12px;margin-top:8px}
.ec-dokploy .ecd-mail-action summary{font-weight:700;cursor:pointer;font-size:13px}
.ec-dokploy .ecd-mail-action[open] summary{margin-bottom:12px}
.ec-dokploy .ecd-mail-btn{background:var(--ecd-primary);color:#fff;border:0;border-radius:8px;min-height:43px;padding:10px 15px;font:inherit;font-weight:700;cursor:pointer}
.ec-dokploy .ecd-mail-danger{background:#a32727}
.ec-dokploy .ecd-mail-checkbox{display:flex!important;flex-direction:row;align-items:flex-start;gap:9px}
.ec-dokploy .ecd-mail-checkbox input{width:17px;min-height:17px;height:17px;margin-top:2px;flex-shrink:0}
@media(max-width:720px){.ec-dokploy .ecd-mail-fields{grid-template-columns:1fr}}

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
    {if $ecDokployLiveAvailable}
      <div class="ecd-state" role="status">
        <p class="ecd-kicker">Última consulta a Dokploy</p>
        <strong>{$ecDokployLiveState|escape:'html'}</strong>
        <p>{$ecDokployLiveDescription|escape:'html'}</p>
      </div>
    {elseif $ecDokployLiveNotice}
      <div class="alert alert-info" role="status">{$ecDokployLiveNotice|escape:'html'}</div>
    {/if}
    {if $ecDokployIsolationLabel}
      <div class="ecd-state" role="status">
        <p class="ecd-kicker">Proyecto independiente</p>
        <strong>{$ecDokployIsolationLabel|escape:'html'}</strong>
        <p>Esta aplicación y su base de datos pertenecen a un proyecto Dokploy vinculado únicamente a tu servicio contratado.</p>
      </div>
    {/if}
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
    {if $ecDokployLiveAvailable}
      <div class="ecd-card ecd-ops">
        <h4>Despliegues recientes</h4>
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
        <h4>Actualizar desde Git</h4>
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
        <div class="ecd-state">
          <strong>Correo pendiente de vinculación</strong>
          <p>Para utilizar esta sección, contrata o vincula un servicio de correo cPanel activo para el mismo dominio. Se factura por separado de App Ecloud.</p>
          <p style="margin-top:10px"><a href="submitticket.php">Solicitar activación de correo</a></p>
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
          <div class="ecd-state"><strong>Aún no hay buzones</strong><p>Puedes crear el primero si tu servicio de correo permite administrar cuentas.</p></div>
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
    <p class="ecd-smallprint">
      El estado de instalación procede de WHMCS. Si se habilita la consulta en Dokploy,
      verás también su estado de ejecución y despliegues recientes. Ninguno de esos
      estados confirma por sí solo que el sitio web responda correctamente.
    </p>
  {/if}
</section>
