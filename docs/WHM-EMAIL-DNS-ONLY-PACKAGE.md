# WHM/cPanel — paquete exclusivo de correo y DNS por cliente

## Objetivo

El CMS del cliente se ejecuta en Dokploy. Cada cliente mantiene una cuenta cPanel independiente para correo, webmail y administración DNS. No se ofrecerán bases de datos, FTP, File Manager, Git, Node.js/Passenger, terminal ni herramientas de hosting web.

**La visibilidad de una función en cPanel no sustituye los permisos de API ni los límites del paquete.**

## 1. Feature list en WHM

En `WHM > Packages > Feature Manager`, crear **ecloud_mail_dns** (no modificar la lista global `default` ni la lista `disabled`). Basarse en los permisos de correo de **Mail Only**, y habilitar exclusivamente:

- Email Accounts, Webmail (si aparece como opción), Forwarders, Email Filters, Email Disk Usage, Email Deliverability y Track Delivery.
- Spam Filters, autorespuestas y otras funciones de correo aprobadas.
- **Email Routing (MX Records and Zone Editor)** para registros MX.
- **Simple Zone Editor** para A/CNAME, y **Advanced Zone Editor** para TXT, AAAA, SPF/DKIM/DMARC y demás registros necesarios, según opciones disponibles en versión WHM.
- DNSSEC solo si realmente se utilizará y el proveedor de nameservers lo admite.

Deshabilitar File Manager, FTP Accounts, Git Version Control, Terminal, SSH, MySQL Databases, PostgreSQL Databases, phpMyAdmin, Application Manager, Node.js, MultiPHP y dominios adicionales. Verificar la lista exacta en WHM 134 antes de crearla, porque ciertas etiquetas pueden cambiar.

## 2. Package en WHM

En `WHM > Packages > Add a Package`, crear **ecloud_mail_dns** y seleccionar la Feature List `ecloud_mail_dns`. Valores piloto (modificar por plan):

| Opción | Piloto |
|---|---|
| Disk Space Quota (MB) | 5120 (5 GiB, compartidos entre buzones y cuenta) |
| Max Email Accounts | 5 |
| Max Quota per Email Address (MB) | 1024 |
| Max SQL Databases | 0 |
| Max FTP Accounts | 0 |
| Max Sub Domains | 0 |
| Max Addon Domains | 0 |
| Max Parked Domains | 0 |
| Max Passenger Applications | 0 |
| Shell Access | Off |
| CGI Access | Off |
| Max Mailing Lists | 0 |
| Email rate limit | Definir política anti-spam; NO usar 0 suponiendo que significa bloqueo, pues puede significar ilimitado |

Revisar políticas de transferencia, retención, antivirus/antispam, backups y cuota real utilizada. La cuenta cPanel aún tendrá directorios internos; **no es una cuenta Linux sin archivos**, sino una cuenta con herramientas limitadas.

## 3. WHMCS y Dokploy

- Registrar en WHMCS un producto cPanel con **WHM Package Name: ecloud_mail_dns**; su módulo cPanel nativo crea una cuenta propia para cada servicio/cliente.
- Registrar el producto de aplicación que ejecuta el módulo Dokploy; automatizar su vinculación al servicio de correo mediante un orquestador o relaciones explícitas de servicios, **sin asumir que un producto WHMCS ejecuta dos módulos de aprovisionamiento simultáneos**.
- Dentro del área de cliente, el addon `ecloudhosting` podrá mostrar buzones y zonas de la cuenta correspondiente, mediante la UAPI limitada a ese usuario, tras verificar servicio WHMCS activo y propietario.
- Mantener tokens de cPanel por cuenta fuera de la base web, con permisos mínimos; jamás usar token WHM root desde el navegador.
- Creación masiva y nuevos servicios son una fase separada. **El código del addon de correo/DNS todavía requiere pruebas UAPI con el WHM real y está bloqueado por defecto para escrituras**.

## 4. DNS autoritativo

**Si los NS públicos son de cPanel/WHM**, la zona que administra cPanel puede modificar los registros reales. Para el dominio del cliente:

- A/AAAA del dominio y www: ingress/Dokploy.
- mail: IP del servidor cPanel.
- MX: mail.dominio.com (o host de correo configurado).
- SPF, DKIM, DMARC: valores correctos generados y verificados en cPanel.
- Email Routing de cPanel: Local Mail Exchanger cuando cPanel recibe el correo.

**Si los NS públicos apuntan a Cloudflare u otro proveedor**, editar la zona local cPanel *no* cambia el DNS público. Mantener el correo en cPanel pero administrar DNS en el proveedor autoritativo mediante otro adaptador o su propio panel; evitar exponer al usuario un editor DNS que aparentemente guarda cambios que no se publican.

## 5. Prueba de aceptación

1. Crear un paquete de ensayo en WHM.
2. Crear cuenta cliente de prueba desde módulo cPanel de WHMCS.
3. Confirmar acceso a buzones y editor de zona; confirmar que no hay accesos a SQL, shell ni File Manager.
4. Apuntar web al VPS con Dokploy, dejar MX y mail en cPanel.
5. Enviar y recibir mensajes internos/externos, comprobar autenticación de correo.
6. Probar editor DNS solo para registros no críticos; auditar cambios y bloquear MX/NS/SPF/DKIM/DMARC si el portal WHMCS aún no gestiona esos registros con un flujo especializado.
7. Revisar cuotas y backups; cancelar pruebas sin borrar correo real.

Referencias:
- https://docs.cpanel.net/whm/packages/feature-manager/
- https://docs.cpanel.net/whm/packages/add-a-package/
- https://docs.whmcs.com/9-1/servers/server-modules/cpanel/
