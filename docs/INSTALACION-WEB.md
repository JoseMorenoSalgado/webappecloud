# Instalador web de WebAppECloud

Primer cliente: NEXUS REAL ESTATE. El asistente permite elegir **MariaDB, MySQL o PostgreSQL**. El sitio `inmo.elearningcloud.org` puede instalarse desde el navegador, como WordPress/Moodle, sin escribir manualmente una cadena `DATABASE_URL`.

## 1. Preparar Dockploy

Mantén este servicio **separado de Edvorya**. En la aplicación WebAppECloud (Dockerfile) configura:

- `INSTALLER_TOKEN` es **opcional**. Si Dockploy no entrega variables de entorno, la aplicación genera automáticamente un token aleatorio seguro en `/app/.runtime/installer-token` con permisos privados `0600`, al abrir por primera vez `/instalar`. Puedes optar por definir el token en Dockploy si funciona correctamente.
- Para consultar la clave generada, **primero abre `/instalar`**, luego en la terminal del contenedor de WebAppECloud ejecuta `cat /app/.runtime/installer-token`. No la compartas por chat, no la incluyas en capturas y no la guardes en Git. La clave se solicita en el primer paso y se elimina del volumen al finalizar la instalación.
- `APP_CONFIG_DIR=/app/.runtime`.
- **Volumen persistente** montado en `/app/.runtime` con permisos de escritura para el usuario `node` del contenedor. Este volumen es OBLIGATORIO: sin él, se perderán las credenciales al reconstruir la imagen.
- Elimina cualquier `DATABASE_URL` previo si deseas configurar la conexión desde el asistente. Si está definido, el asistente no permitirá reinstalar.
- Conserva `PORT=3000`, dominio HTTPS y la red/puertos de Edvorya intactos.
- Para ver el instalador directamente en la portada, establece `SITE_PREVIEW_MODE=false`. Si está activada la vista previa, abre explícitamente `/instalar`.

En el modo Compose del repositorio se incluye el volumen `webapp-config:/app/.runtime`. En una **aplicación Dockerfile**, debes añadirlo desde la interfaz de volúmenes de Dockploy; Compose y Dockerfile no son intercambiables.

## 2. Crear base de datos vacía

**MariaDB, MySQL o PostgreSQL**. Como en WordPress, la base y el usuario SQL deben existir previamente. En Dockploy crea una instancia MariaDB independiente (no utilices la de Edvorya) y asigna una base vacía con permisos para crear tablas. El nombre del host será el nombre de servicio DNS **alcanzable desde el contenedor de WebAppECloud**; si están en redes Docker distintas, primero configura una red compartida restringida a estos dos contenedores.

En cPanel crearías la base, usuario y permisos desde MySQL Databases antes de abrir el instalador.

## 3. Abrir el asistente

Visita `https://inmo.elearningcloud.org/instalar`.

**Paso 1:** tipo de motor, host SQL, puerto (3306), nombre de la base, usuario SQL, contraseña y clave de instalación (tomada de la terminal privada o de la variable `INSTALLER_TOKEN`).

**Paso 2:** nombre de la institución, correo institucional, nombre del administrador, correo del administrador y contraseña de al menos 14 caracteres.

El dominio se toma del host HTTP solicitado y se vincula con la institución; no se permite escoger otra empresa mediante el formulario. Una vez completado, se crean las tablas del CMS, la institución, el dominio y el administrador (contraseña almacenada como hash `scrypt`).

La instalación se bloquea tras guardarse la configuración. **No se debe eliminar el volumen de configuración ni el de MariaDB.** Se recomienda hacer copias de seguridad de ambos, con acceso restringido.

### Limitaciones actuales

- El instalador inicial utiliza `prisma db push` para **una base vacía**, exclusivamente para esta primera versión/staging. Antes de utilizar la plataforma con clientes reales se incorporarán migraciones SQL versionadas, validaciones adicionales, backups y auditoría de seguridad.
- Un fallo después de crear el esquema pero antes de terminar puede requerir intervención técnica; **no borres tablas ni reinicies con otro tenant automáticamente**.
- Los permisos de la base deben ser los mínimos necesarios para crear tablas durante la instalación y reducirse después.
- La configuración guardada contiene la contraseña SQL en un archivo privado con permisos `0600` dentro del volumen. No se almacena en Git, HTML ni un directorio público; protege el volumen y sus copias.
- El token de instalación es distinto de la contraseña del administrador. Retíralo de Dockploy después de instalar y comprobar el acceso.
- La instalación inicial crea la primera institución. La alta de siguientes empresas se hará posteriormente desde el panel global SaaS, sin ejecutar nuevamente este instalador.

## Diagnóstico de versión de Dockploy (evitar cachés y contenedores obsoletos)

Abrir `https://inmo.elearningcloud.org/api/live` y comparar el campo `release` con `2026-10-21-multidb-installer-r1`.
Si no aparece esta versión, Dockploy **no está ejecutando la imagen actual de main**.
El dominio debe apuntar al contenedor correcto (puerto interno 3000); en Dockploy usar Deploy/Rebuild y comprobar el commit de main en los logs. No basta con seleccionar la rama ni con Reload.

Abrir `/instalar`, comprobar que aparezca selector MariaDB/MySQL/PostgreSQL. Si la interfaz señala almacenamiento no escribible, revisar el volumen `/app/.runtime` y sus permisos con `id && ls -ld /app/.runtime` dentro del contenedor (sin publicar secretos). El token se genera en ese volumen y se consulta exclusivamente desde la terminal privada.

### PostgreSQL en Dockploy

Crea una base PostgreSQL **vacía e independiente** en un contenedor propio, por ejemplo servicio DNS `postgres`, puerto `5432`. Conecta el contenedor Next.js a una red privada que alcance el servicio PostgreSQL. Introduce host, puerto, base, usuario y contraseña en la pantalla de instalación.

La imagen `docker-compose.yml` incluida crea **MariaDB como ejemplo por defecto**. Si deseas PostgreSQL, crea ese servicio desde Dockploy y no utilices el contenedor MariaDB de muestra. La configuración de ambas tecnologías no se mezcla; se elige el motor al instalar.

Prisma utiliza `prisma/schema.prisma` para MySQL/MariaDB y `prisma/schema.postgres.prisma` para PostgreSQL. No se puede cambiar simplemente el protocolo de DATABASE_URL después de instalar sin migrar físicamente los datos.
