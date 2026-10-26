# Correo corporativo cPanel dentro de App Ecloud (WHMCS/Lagom)

**ESTADO: PILOTO / PR #38.** La interfaz y adaptador están implementados, pero aún deben validarse con tu WHMCS/cPanel reales antes de habilitar escritura para clientes. Los servicios Dokploy y correo continúan independientes en facturación e infraestructura.

## Modelo de alojamiento

- **WHMCS / Lagom**: un inicio de sesión, una ficha App Ecloud con «Aplicación» y «Correo corporativo».
- **Dokploy**: Next.js, Git, bases de datos y dominios de aplicaciones.
- **cPanel / WHM existente**: buzones reales y webmail; se factura como **otro servicio de hosting cPanel** en WHMCS, nunca como cuenta cPanel compartida por muchos clientes.
- El cliente ve únicamente buzones correspondientes a su segundo servicio, activo, **con el mismo propietario WHMCS**, vinculado explícitamente por el administrador.
- **No** hay credenciales WHM/root/reseller ni API key Dokploy en el navegador. Cada cPanel account posee su propio **API token cPanel**, guardado fuera del webroot en WHMCS.
- Una segunda aplicación del mismo cliente requiere vínculo propio (por `service_id`).

## 1. Crear o identificar el producto cPanel de correo

Crear un producto independiente de tipo **cPanel**, que provisiona una cuenta cPanel de correo para el dominio del cliente, en un servidor de correo ya dado de alta en WHMCS. El servicio debe aparecer en `tblhosting` con estado `Active`, `userid` idéntico al de App Ecloud, módulo `cpanel`, username cPanel y `server` asignado. El hostname del servidor cPanel debe ser un FQDN HTTPS válido con el puerto `2083` accesible desde el WHMCS PHP.

**NO usar la cuenta de correo del administrador o el token WHM/root.** Entra en el cPanel de esa cuenta y crea un token desde **Seguridad → Administrar tokens API**. Es específico de esa cuenta.

## 2. Vincular ambos servicios de WHMCS de manera privada

Consulta los **IDs de servicios de WHMCS**, no los números de pedido:

- `APP_SERVICE_ID`: URL de la ficha «App Ecloud»: `clientarea.php?action=productdetails&id=1234`.
- `MAIL_SERVICE_ID`: ID de producto/servicio cPanel contratado, p. ej. `5678`.

Crea fuera de `public_html`, en el servidor WHMCS, un archivo JSON legible por **el usuario PHP de WHMCS** y solo por él (`chmod 600`), por ejemplo:

```text
/home/USUARIO_PRIVADO/secretos/ecloud-mail-bindings.json
```

Contenido de ejemplo **sin token real**:

```json
{
  "1234": {
    "email_service_id": 5678,
    "api_token": "TOKEN_CPANEL_DE_ESTA_CUENTA_NO_LO_PEGUES_AQUI",
    "mailbox_limit": 5,
    "max_quota_mb": 2048,
    "mail_domain": "empresa.com"
  }
}
```

`mail_domain` es opcional cuando la app y cPanel tienen el **mismo dominio**. Cuando App Ecloud usa `app.empresa.com` pero el hosting de correo utiliza `empresa.com`, el administrador debe definir explícitamente `mail_domain: empresa.com`. Solo se permite el mismo dominio o un dominio padre exacto del subdominio de la app. No se aceptan dominios ajenos.

**La conexión se verifica contra WHMCS**: ambos servicios tienen el mismo `userid`, estado `Active`, dominio correcto, y el producto de correo usa módulo `cpanel`. El hostname y username de cPanel proceden directamente de `tblservers` y `tblhosting`, nunca del navegador ni del JSON.

Los tokens son confidenciales: nunca imprimirlos en plantillas, registrar solicitudes completas, incluir en archivos públicos o subir a GitHub. Rotarlos si se sospecha exposición. Copiar respaldo cifrado separado de este archivo privado.

## 3. Habilitación escalonada mediante variables PHP

El entorno PHP que ejecuta WHMCS debe recibir:

```text
ECLOUD_DOKPLOY_EMAIL_MAP_FILE=/home/USUARIO_PRIVADO/secretos/ecloud-mail-bindings.json
ECLOUD_DOKPLOY_ENABLE_EMAIL_READS=1
ECLOUD_DOKPLOY_ENABLE_EMAIL_WRITES=0
ECLOUD_DOKPLOY_ENABLE_EMAIL_DELETE=0
```

Primero habilitar solo `READS=1`; abrir la ficha App Ecloud como cliente y comprobar que **solo aparecen los buzones del dominio contratado**.

Para crear, cambiar contraseña y cuota **exclusivamente en staging validado**:

```text
ECLOUD_DOKPLOY_ENABLE_EMAIL_WRITES=1
```

La eliminación está desactivada de forma independiente y requiere tanto `ECLOUD_DOKPLOY_ENABLE_EMAIL_DELETE=1` como escribir la dirección completa con el prefijo `ELIMINAR ` y una confirmación explícita. **No activar la eliminación para clientes reales antes de validar respaldo/restauración de mensajes**.

Los límites `mailbox_limit` y `max_quota_mb` son del plan contratado y se aplican adicionalmente a los límites del propio cPanel. No se conceden cuotas ilimitadas desde esta interfaz. Un bloqueo de 20 segundos entre operaciones limita los reintentos.

## 4. Funciones del cliente en Lagom

La sección «Correo corporativo» de la ficha de App Ecloud tiene:

- Estado de vinculación, dominio de correo, número de buzones y cuota contratada;
- Listado de correos desde `Email/list_pops_with_disk` vía UAPI;
- Crear cuenta: `Email/add_pop`;
- Cambiar contraseña: `Email/passwd_pop`;
- Ajustar cuota: `Email/edit_pop_quota`;
- Eliminar buzón: `Email/delete_pop`, **bloqueada por defecto**;
- «Abrir Webmail»: abre `https://SERVIDOR_CPANEL:2096/`, login normal, **no realiza SSO ni expone contraseñas**.

Las contraseñas se transmiten del navegador a WHMCS bajo HTTPS y de WHMCS a cPanel mediante **POST**, nunca en una URL. Se validan contraseñas de 14–128 caracteres con mayúscula/minúscula/número/símbolo. Los tokens de sesión de WHMCS y un CSRF independiente protegen las acciones; cada operación valida nuevamente propietario, dominio y ambos servicios activos. No se debe almacenar ni registrar contraseñas.

## 5. DNS de aplicaciones y correo separados

En Cloudflare (o zona DNS del dominio):

- `app.empresa.com` o la web principal apunta al proxy de Dokploy según el despliegue;
- `mail.empresa.com` apunta al servidor cPanel, registro DNS **sin proxy naranja**;
- `MX` apunta al host de correo definido por cPanel;
- `SPF`, `DKIM`, `DMARC` se configuran según el servidor de correo;
- webmail/IMAP/SMTP siguen en cPanel, no Dokploy.

Las actualizaciones DNS **no se automatizan** en esta fase; validarlas antes de ofrecer correo activo.

## 6. Pruebas obligatorias antes de producción

1. Conectar primero el correo de un **cliente de staging** y activar solo lectura.
2. Verificar la respuesta de UAPI con el token de la cuenta cPanel, puerto 2083 y SSL válido.
3. Entrar con otro cliente WHMCS y confirmar que ni se listan ni se modifican buzones ajenos.
4. Verificar límite de buzones, límite de cuota, contraseñas y CSRF.
5. Crear un buzón, cambiar contraseña, recibir/enviar un mensaje por IMAP/SMTP; verificar correo entrante y saliente.
6. Verificar la desactivación inmediata al suspender el servicio de correo o la app.
7. Para eliminación, probar primero backup y restauración de buzones (JetBackup) y dejarlo desactivado hasta entonces.

## Notas técnicas

Código:

```text
integrations/whmcs/modules/servers/eclouddokploy/
  eclouddokploy.php
  lib/CpanelMail.php
  lib/EmailOperations.php
  templates/clientarea.tpl
```

Este sigue siendo un **módulo de aprovisionamiento WHMCS (`modules/servers`)** y **NO aparece en «Módulos adicionales» (`modules/addons`)**.

- cPanel UAPI email: https://api.docs.cpanel.net/specifications/cpanel.openapi/email-accounts
- Autenticación de API Token: https://api.docs.cpanel.net/cpanel/tokens/
- Módulos WHMCS y acciones cliente: https://developers.whmcs.com/provisioning-modules/custom-functions/
