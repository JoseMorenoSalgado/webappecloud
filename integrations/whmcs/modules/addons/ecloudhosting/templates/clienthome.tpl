{* Customer self-service view; never render cPanel tokens or credentials. *}
<div class="container-fluid">
  <h2>Mis correos y DNS</h2>
  <p>Gestiona los buzones y consulta los registros de tu dominio. Tu sitio web está alojado por separado.</p>
  {if $ecError}<div class="alert alert-danger">{$ecError|escape:'html'}</div>{/if}
  {if $ecMessage}<div class="alert alert-success">{$ecMessage|escape:'html'}</div>{/if}
  {if !$ecAccounts}
    <div class="alert alert-info">No hay cuentas de correo cPanel activas vinculadas a este usuario. Contacta soporte.</div>
  {else}
    <form method="get" action="index.php" class="form-inline">
      <input type="hidden" name="m" value="ecloudhosting">
      <label for="service_id">Dominio:</label>
      <select name="service_id" id="service_id" class="form-control">
        {foreach from=$ecAccounts item=account}
          <option value="{$account.service_id|escape:'html'}"{if $ecSelected && $ecSelected.service_id == $account.service_id} selected{/if}>{$account.domain|escape:'html'}</option>
        {/foreach}
      </select>
      <button type="submit" class="btn btn-default">Seleccionar</button>
    </form>
  {/if}

  {if $ecSelected}
    <h3>Correo — {$ecSelected.domain|escape:'html'}</h3>
    <table class="table table-striped">
      <thead><tr><th>Buzón</th><th>Cuota</th></tr></thead>
      <tbody>
        {foreach from=$ecMail item=mail}
          <tr><td>{$mail.email|escape:'html'}</td><td>{$mail.quota|escape:'html'}</td></tr>
        {foreachelse}
          <tr><td colspan="2">No se encontraron buzones.</td></tr>
        {/foreach}
      </tbody>
    </table>

    {if $ecCanMail}
      <h4>Crear buzón</h4>
      <form method="post" action="{$ecModuleLink|escape:'html'}">
        <input type="hidden" name="csrf" value="{$ecToken|escape:'html'}">
        <input type="hidden" name="service_id" value="{$ecSelected.service_id|escape:'html'}">
        <input type="hidden" name="ecloud_action" value="email_create">
        <div class="form-group">
          <label>Usuario del correo</label>
          <div class="input-group">
            <input class="form-control" name="email_local" required maxlength="64" autocomplete="off">
            <span class="input-group-addon">@{$ecSelected.domain|escape:'html'}</span>
          </div>
        </div>
        <div class="form-group"><label>Contraseña (14 caracteres o más)</label>
          <input class="form-control" type="password" name="password" minlength="14" maxlength="128" required autocomplete="new-password">
        </div>
        <div class="form-group"><label>Cuota en MB</label>
          <input class="form-control" type="number" name="quota" min="100" max="20480" value="1024" required>
        </div>
        <button type="submit" class="btn btn-primary">Crear correo</button>
      </form>
      <p>La contraseña solo se usa para configurar el buzón; no se volverá a mostrar aquí.</p>

      <h4>Cambiar contraseña o cuota</h4>
      <form method="post" action="{$ecModuleLink|escape:'html'}">
        <input type="hidden" name="csrf" value="{$ecToken|escape:'html'}">
        <input type="hidden" name="service_id" value="{$ecSelected.service_id|escape:'html'}">
        <div class="form-group"><label>Buzón existente</label>
          <select name="email_local" class="form-control" required>
            {foreach from=$ecMail item=mail}
              <option value="{$mail.local|escape:'html'}">{$mail.email|escape:'html'}</option>
            {/foreach}
          </select>
        </div>
        <div class="form-group"><label>Nueva contraseña</label>
          <input type="password" name="password" class="form-control" minlength="14" maxlength="128" autocomplete="new-password">
        </div>
        <div class="form-group"><label>Nueva cuota MB</label><input type="number" name="quota" min="100" max="20480" value="1024" class="form-control"></div>
        <button class="btn btn-default" name="ecloud_action" value="email_password" type="submit">Cambiar contraseña</button>
        <button class="btn btn-default" name="ecloud_action" value="email_quota" type="submit">Cambiar cuota</button>
      </form>
    {else}
      <div class="alert alert-info">Administración de buzones pendiente de habilitación por soporte.</div>
    {/if}

    <h3>Zona DNS</h3>
    <p>Los registros MX, NS, SPF, DKIM y DMARC se mantienen protegidos para garantizar la entrega de correo. Si el DNS autoritativo está en Cloudflare u otro proveedor, los cambios deben hacerse allí.</p>
    <table class="table table-striped">
      <thead><tr><th>Nombre</th><th>Tipo</th><th>Valor</th><th>TTL</th></tr></thead>
      <tbody>
      {foreach from=$ecDns item=record}
        <tr>
          <td>{$record.name|escape:'html'}</td>
          <td>{$record.type|escape:'html'}</td>
          <td style="overflow-wrap:anywhere;">{$record.value|escape:'html'}</td>
          <td>{$record.ttl|escape:'html'}</td>
        </tr>
      {foreachelse}
        <tr><td colspan="4">No hay registros disponibles para consultar.</td></tr>
      {/foreach}
      </tbody>
    </table>
    {if $ecCanDns}
      <h4>Agregar registro web</h4>
      <form method="post" action="{$ecModuleLink|escape:'html'}">
        <input type="hidden" name="csrf" value="{$ecToken|escape:'html'}">
        <input type="hidden" name="service_id" value="{$ecSelected.service_id|escape:'html'}">
        <input type="hidden" name="ecloud_action" value="dns_add">
        <div class="form-group"><label>Nombre (@ para dominio raíz)</label><input class="form-control" name="record_name" required></div>
        <div class="form-group"><label>Tipo</label><select class="form-control" name="record_type"><option>A</option><option>CNAME</option><option>TXT</option></select></div>
        <div class="form-group"><label>Valor</label><input class="form-control" name="record_value" required maxlength="255"></div>
        <div class="form-group"><label>TTL</label><input class="form-control" name="ttl" type="number" min="300" max="86400" value="14400"></div>
        <button class="btn btn-primary" type="submit">Guardar registro</button>
      </form>
    {else}
      <div class="alert alert-info">Edición DNS deshabilitada o la zona no está delegada al DNS de cPanel.</div>
    {/if}
  {/if}
</div>
