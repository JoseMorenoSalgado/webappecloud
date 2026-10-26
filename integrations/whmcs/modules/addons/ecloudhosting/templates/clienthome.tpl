{* WHMCS client portal: readable, responsive and scoped. No API tokens are rendered. *}
<style>
{literal}
.ec-portal{--ec-navy:var(--text-heading-color,#173258);--ec-text:var(--text-color,#202d40);--ec-muted:var(--text-muted,#4c5d72);--ec-border:var(--border-color,#d9e0e9);--ec-surface:var(--body-bg,#f5f7fa);--ec-blue:var(--primary,#1358a8);color:var(--ec-text);font-family:inherit;font-size:15px;line-height:1.55;max-width:1160px;margin:0 auto 48px}
.ec-portal *{box-sizing:border-box}
.ec-portal .ec-header{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;border-bottom:1px solid var(--ec-border);padding:12px 0 22px;margin-bottom:22px}
.ec-portal .ec-eyebrow{font-size:12px;letter-spacing:.09em;text-transform:uppercase;font-weight:700;color:var(--ec-muted);margin:0 0 4px}
.ec-portal h1{font-size:29px;line-height:1.2;font-weight:750;color:var(--ec-navy);margin:0 0 9px}
.ec-portal h2{font-size:21px;line-height:1.3;font-weight:750;color:var(--ec-navy);margin:0}
.ec-portal h3{font-size:17px;line-height:1.35;font-weight:700;color:var(--ec-navy);margin:0 0 14px}
.ec-portal .ec-lead{font-size:15px;color:var(--ec-muted);margin:0}
.ec-portal .ec-domain-picker{display:flex;align-items:end;gap:10px;flex-wrap:wrap}
.ec-portal label{font-size:14px;font-weight:650;color:var(--ec-text);display:block;margin-bottom:6px}
.ec-portal input,.ec-portal select{font-size:16px;min-height:44px;border:1px solid #acb9c8;border-radius:8px;max-width:100%;background:var(--input-bg,#fff);color:var(--ec-text);padding:9px 12px}
.ec-portal .form-control{height:auto}
.ec-portal .ec-domain-picker select{min-width:210px}
.ec-portal .ec-button{display:inline-flex;align-items:center;justify-content:center;min-height:44px;border-radius:8px;padding:10px 17px;text-decoration:none;font-weight:700;font-size:14px;line-height:1.25;border:1px solid var(--ec-blue);background:var(--ec-blue);color:#fff;cursor:pointer}
.ec-portal .ec-button:hover,.ec-portal .ec-button:focus{filter:brightness(.9);color:#fff;text-decoration:none}
.ec-portal .ec-button-secondary{background:var(--ec-surface);border-color:var(--ec-border);color:var(--ec-text)}
.ec-portal .ec-button-secondary:hover,.ec-portal .ec-button-secondary:focus{background:var(--ec-surface);color:var(--ec-text)}
.ec-portal .ec-navigation{display:flex;gap:10px;margin:12px 0 22px;flex-wrap:wrap}
.ec-portal .ec-navigation a{display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--ec-border);background:#fff;color:var(--ec-blue);border-radius:9px;padding:10px 17px;min-height:44px;font-weight:700;text-decoration:none}
.ec-portal .ec-navigation a:hover,.ec-portal .ec-navigation a:focus{background:var(--ec-surface);color:var(--ec-text)}
.ec-portal .ec-panel{border:1px solid var(--ec-border);border-radius:13px;background:var(--panel-bg,#fff);margin-bottom:22px;overflow:hidden;box-shadow:0 2px 9px rgba(26,43,69,.04)}
.ec-portal .ec-panel-header{padding:20px 23px;border-bottom:1px solid var(--ec-border);display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.ec-portal .ec-panel-body{padding:22px 23px}
.ec-portal .ec-counter{font-weight:700;font-size:13px;color:var(--ec-blue);border:1px solid var(--ec-border);border-radius:20px;padding:5px 11px;background:var(--ec-surface)}
.ec-portal .ec-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:20px}
.ec-portal .ec-stat{background:var(--ec-surface);border:1px solid var(--ec-border);padding:15px;border-radius:10px}
.ec-portal .ec-stat strong{display:block;color:var(--ec-navy);font-size:19px;overflow-wrap:anywhere}
.ec-portal .ec-stat span{font-size:13px;color:var(--ec-muted)}
.ec-portal .ec-mail-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(235px,1fr));gap:12px}
.ec-portal .ec-mail-card{border:1px solid var(--ec-border);border-radius:10px;padding:16px;background:var(--panel-bg,#fff);min-width:0}
.ec-portal .ec-mail-card strong{display:block;font-size:15px;overflow-wrap:anywhere;color:var(--ec-navy)}
.ec-portal .ec-mail-card span{display:block;margin-top:6px;font-size:13px;color:var(--ec-muted)}
.ec-portal .ec-subpanel{background:var(--ec-surface);border:1px solid var(--ec-border);border-radius:11px;padding:20px;margin-top:20px}
.ec-portal .ec-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
.ec-portal .ec-field{min-width:0;margin-bottom:16px}
.ec-portal .ec-field input,.ec-portal .ec-field select{width:100%}
.ec-portal .ec-help{color:var(--ec-muted);font-size:13px;margin:8px 0 0}
.ec-portal .ec-alert{border-left:4px solid #98acc7;border-radius:8px;background:#f3f6fa;padding:12px 15px;margin:12px 0 18px;color:var(--ec-text)}
.ec-portal .ec-alert-error{border-left-color:#b42318;background:#fff5f4;color:#851b16}
.ec-portal .ec-alert-success{border-left-color:#238449;background:#f0f9f3;color:#155d34}
.ec-portal .ec-table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch;border:1px solid var(--ec-border);border-radius:9px}
.ec-portal .ec-table{width:100%;border-collapse:collapse;margin:0;min-width:550px;font-size:14px}
.ec-portal .ec-table th{background:var(--ec-surface);color:var(--ec-text);text-align:left;font-weight:750;font-size:13px;white-space:nowrap}
.ec-portal .ec-table th,.ec-portal .ec-table td{padding:13px 14px;border-bottom:1px solid #e8edf3;vertical-align:top}
.ec-portal .ec-table tr:last-child td{border-bottom:0}
.ec-portal .ec-break{overflow-wrap:anywhere;word-break:break-word}
.ec-portal .ec-tag{font-size:12px;font-weight:700;padding:4px 9px;border:1px solid var(--ec-border);border-radius:6px;background:var(--ec-surface);white-space:nowrap}
.ec-portal .ec-muted{color:var(--ec-muted)}
.ec-portal a:focus-visible,.ec-portal button:focus-visible,.ec-portal input:focus-visible,.ec-portal select:focus-visible{outline:3px solid #5aa3ee;outline-offset:2px}
@media(max-width:720px){.ec-portal{font-size:15px}.ec-portal h1{font-size:25px}.ec-portal .ec-header{align-items:stretch}.ec-portal .ec-domain-picker,.ec-portal .ec-domain-picker label,.ec-portal .ec-domain-picker select{width:100%}.ec-portal .ec-domain-picker .ec-button{width:100%}.ec-portal .ec-grid{grid-template-columns:1fr}.ec-portal .ec-stats{grid-template-columns:1fr 1fr}.ec-portal .ec-panel-header,.ec-portal .ec-panel-body{padding:17px}.ec-portal .ec-navigation a{flex:1;min-width:120px}.ec-portal .ec-mail-grid{grid-template-columns:1fr}.ec-portal .ec-button{width:100%}}
{/literal}
</style>
<main class="ec-portal">
  <header class="ec-header">
    <div>
      <p class="ec-eyebrow">Elearning Cloud / Mi servicio</p>
      <h1>Correos y dominio</h1>
      <p class="ec-lead">Administra tus buzones y consulta los registros DNS desde un mismo lugar.</p>
    </div>
    {if $ecAccounts}
      <form method="get" action="index.php" class="ec-domain-picker">
        <input type="hidden" name="m" value="ecloudhosting">
        <div>
          <label for="ec-service-id">Dominio contratado</label>
          <select name="service_id" id="ec-service-id" class="form-control">
            {foreach from=$ecAccounts item=account}
              <option value="{$account.service_id|escape:'html'}"{if $ecSelected && $ecSelected.service_id == $account.service_id} selected{/if}>{$account.domain|escape:'html'}</option>
            {/foreach}
          </select>
        </div>
        <button type="submit" class="ec-button ec-button-secondary">Ver dominio</button>
      </form>
    {/if}
  </header>

  {if $ecError}<p role="alert" class="ec-alert ec-alert-error">{$ecError|escape:'html'}</p>{/if}
  {if $ecMessage}<p role="status" class="ec-alert ec-alert-success">{$ecMessage|escape:'html'}</p>{/if}

  {if !$ecAccounts}
    <section class="ec-panel"><div class="ec-panel-body">
      <h2>Aún no hay servicios vinculados</h2>
      <p class="ec-lead">No encontramos una cuenta de correo cPanel activa para tu usuario. Comunícate con soporte.</p>
    </div></section>
  {/if}

  {if $ecSelected}
    <nav class="ec-navigation" aria-label="Secciones del servicio">
      <a href="#ec-email">Cuentas de correo</a>
      <a href="#ec-dns">Zona DNS</a>
    </nav>

    <div class="ec-stats">
      <div class="ec-stat">
        <span>Dominio seleccionado</span>
        <strong>{$ecSelected.domain|escape:'html'}</strong>
      </div>
      <div class="ec-stat">
        <span>Buzones disponibles en tu cuenta</span>
        <strong>{$ecMail|@count} registrados</strong>
      </div>
    </div>

    <section class="ec-panel" id="ec-email" aria-labelledby="ec-email-title">
      <div class="ec-panel-header">
        <div>
          <p class="ec-eyebrow">Gestión de correo</p>
          <h2 id="ec-email-title">Cuentas de correo</h2>
        </div>
        <span class="ec-counter">{$ecMail|@count} buzones</span>
      </div>
      <div class="ec-panel-body">
        {if $ecMail}
          <div class="ec-mail-grid">
            {foreach from=$ecMail item=mail}
              <div class="ec-mail-card">
                <strong>{$mail.email|escape:'html'}</strong>
                <span>Cuota: {$mail.quota|escape:'html'}</span>
              </div>
            {/foreach}
          </div>
        {else}
          <p class="ec-muted">Este dominio todavía no tiene buzones de correo registrados.</p>
        {/if}

        {if $ecCanMail}
          <div class="ec-subpanel">
            <h3>Crear un correo empresarial</h3>
            <form method="post" action="{$ecModuleLink|escape:'html'}">
              <input type="hidden" name="csrf" value="{$ecToken|escape:'html'}">
              <input type="hidden" name="service_id" value="{$ecSelected.service_id|escape:'html'}">
              <input type="hidden" name="ecloud_action" value="email_create">
              <div class="ec-grid">
                <div class="ec-field">
                  <label for="ec-new-mail">Nombre del buzón</label>
                  <input id="ec-new-mail" class="form-control" name="email_local" required maxlength="64" autocomplete="off" placeholder="ventas">
                  <p class="ec-help">Se creará en @{$ecSelected.domain|escape:'html'}</p>
                </div>
                <div class="ec-field">
                  <label for="ec-new-quota">Espacio en MB</label>
                  <input id="ec-new-quota" class="form-control" name="quota" type="number" min="100" max="20480" value="1024" required>
                </div>
              </div>
              <div class="ec-field">
                <label for="ec-new-password">Contraseña del buzón</label>
                <input id="ec-new-password" class="form-control" name="password" type="password" minlength="14" maxlength="128" required autocomplete="new-password" placeholder="Mínimo 14 caracteres">
                <p class="ec-help">La contraseña no se mostrará después de crear el buzón.</p>
              </div>
              <button class="ec-button" type="submit">Crear cuenta de correo</button>
            </form>
          </div>

          {if $ecMail}
            <div class="ec-subpanel">
              <h3>Administrar un correo existente</h3>
              <form method="post" action="{$ecModuleLink|escape:'html'}">
                <input type="hidden" name="csrf" value="{$ecToken|escape:'html'}">
                <input type="hidden" name="service_id" value="{$ecSelected.service_id|escape:'html'}">
                <div class="ec-field">
                  <label for="ec-edit-mail">Selecciona el buzón</label>
                  <select id="ec-edit-mail" name="email_local" class="form-control" required>
                    {foreach from=$ecMail item=mail}
                      <option value="{$mail.local|escape:'html'}">{$mail.email|escape:'html'}</option>
                    {/foreach}
                  </select>
                </div>
                <div class="ec-grid">
                  <div class="ec-field">
                    <label for="ec-password">Nueva contraseña</label>
                    <input id="ec-password" type="password" name="password" class="form-control" minlength="14" maxlength="128" autocomplete="new-password" placeholder="Solo para cambiar contraseña">
                  </div>
                  <div class="ec-field">
                    <label for="ec-quota">Nueva cuota en MB</label>
                    <input id="ec-quota" name="quota" type="number" min="100" max="20480" value="1024" class="form-control">
                  </div>
                </div>
                <div class="ec-grid">
                  <button class="ec-button ec-button-secondary" name="ecloud_action" value="email_password" type="submit">Cambiar contraseña</button>
                  <button class="ec-button ec-button-secondary" name="ecloud_action" value="email_quota" type="submit">Actualizar espacio</button>
                </div>
              </form>
            </div>
          {/if}
        {else}
          <p class="ec-alert">La administración de buzones desde WHMCS está pendiente de activación. Puedes consultar tus correos disponibles.</p>
        {/if}
      </div>
    </section>

    <section class="ec-panel" id="ec-dns" aria-labelledby="ec-dns-title">
      <div class="ec-panel-header">
        <div>
          <p class="ec-eyebrow">Dominio y conectividad</p>
          <h2 id="ec-dns-title">Zona DNS</h2>
        </div>
        <span class="ec-counter">{$ecDns|@count} registros</span>
      </div>
      <div class="ec-panel-body">
        <p class="ec-lead">Consulta hacia dónde apunta tu web y dónde se recibe tu correo. Los registros de correo (MX, SPF, DKIM y DMARC) están protegidos.</p>
        <div class="ec-table-wrap" role="region" aria-label="Registros DNS del dominio" tabindex="0" style="margin-top:18px">
          <table class="ec-table">
            <thead><tr><th>Nombre</th><th>Tipo</th><th>Destino / Valor</th><th>TTL</th></tr></thead>
            <tbody>
              {foreach from=$ecDns item=record}
                <tr>
                  <td class="ec-break">{$record.name|escape:'html'}</td>
                  <td><span class="ec-tag">{$record.type|escape:'html'}</span></td>
                  <td class="ec-break">{$record.value|escape:'html'}</td>
                  <td>{$record.ttl|escape:'html'}</td>
                </tr>
              {foreachelse}
                <tr><td colspan="4">No hay registros disponibles para consultar.</td></tr>
              {/foreach}
            </tbody>
          </table>
        </div>
        <p class="ec-help">En celulares puedes desplazar la tabla hacia los lados para ver todos los valores.</p>

        {if $ecCanDns}
          <div class="ec-subpanel">
            <h3>Agregar un registro DNS</h3>
            <p class="ec-help">Disponible solo si los servidores DNS autoritativos corresponden a cPanel.</p>
            <form method="post" action="{$ecModuleLink|escape:'html'}">
              <input type="hidden" name="csrf" value="{$ecToken|escape:'html'}">
              <input type="hidden" name="service_id" value="{$ecSelected.service_id|escape:'html'}">
              <input type="hidden" name="ecloud_action" value="dns_add">
              <div class="ec-grid">
                <div class="ec-field"><label for="ec-dns-name">Nombre</label><input id="ec-dns-name" class="form-control" name="record_name" placeholder="@ o subdominio" required></div>
                <div class="ec-field"><label for="ec-dns-type">Tipo de registro</label><select id="ec-dns-type" class="form-control" name="record_type"><option>A</option><option>CNAME</option><option>TXT</option></select></div>
                <div class="ec-field"><label for="ec-dns-value">Destino / Valor</label><input id="ec-dns-value" class="form-control" name="record_value" required maxlength="255"></div>
                <div class="ec-field"><label for="ec-dns-ttl">TTL en segundos</label><input id="ec-dns-ttl" class="form-control" name="ttl" type="number" min="300" max="86400" value="14400"></div>
              </div>
              <button class="ec-button" type="submit">Guardar registro DNS</button>
            </form>
          </div>
        {else}
          <p class="ec-alert">La edición DNS aún no está habilitada o tu dominio utiliza servidores DNS de otro proveedor. La consulta sigue disponible.</p>
        {/if}
      </div>
    </section>
  {/if}
</main>
