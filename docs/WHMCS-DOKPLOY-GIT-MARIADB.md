# Elearning Cloud → Dokploy (Git + MariaDB) — piloto

**Estado:** primera implementación en revisión. No desplegada ni probada con las credenciales del VPS. No activar automatización de pedidos hasta validar staging.

## Qué hace

Módulo de aprovisionamiento WHMCS `eclouddokploy` para instalar **WebAppECloud** en Dokploy desde un repositorio público de GitHub, con **MariaDB por cliente**.

1. Parte de un Environment ID **preexistente** en Dokploy; un administrador lo configura en el producto WHMCS.
2. Por cada `serviceid` crea un registro idempotente en `mod_ecloud_dokploy_services`.
3. Solicita `mariadb.create` con credenciales fuertes diferentes por servicio; luego `mariadb.deploy`.
4. Solicita `application.create`, `application.saveGitProvider`, `application.saveBuildType` (`Dockerfile`) y `application.saveEnvironment`.
5. Configura `DATABASE_URL` usando el hostname interno de MariaDB obtenido con `mariadb.one`; no publica el puerto SQL.
6. Solicita `domain.create` y `application.deploy`.
7. **No devuelve `success` a WHMCS**: deja el servicio pendiente hasta comprobar `https://dominio/api/health`, DNS y funcionamiento del instalador. El API de Dokploy confirma la aceptación del despliegue, no el estado final de la aplicación.

Los recursos de cada cliente se identifican por `serviceid`, no se mezclan bases de datos ni contraseñas. **No crea buzones de correo**: se mantienen en una cuenta cPanel separada, aprovisionada por su propio módulo de WHMCS.

## Pre-requisitos del piloto

- WHMCS compatible con PHP 8.2+, extensión cURL, base de datos MySQL de WHMCS y permisos de módulos.
- Instalar la carpeta `integrations/whmcs/modules/servers/eclouddokploy/` dentro de `<WHMCS_ROOT>/modules/servers/eclouddokploy/`.
- Dokploy accesible vía **HTTPS con certificado válido y sin puerto explícito**; utilizar un reverse proxy seguro frente a Dokploy si es necesario.
- Ambiente de staging Dokploy existente; confirmar su `environmentId`.
- Repositorio GitHub **público HTTPS** en esta fase. Git privado requiere llave/proveedor configurado en Dokploy y soporte adicional.
- Dominio DNS apuntando al VPS, certificado HTTPS verificable y `Dockerfile` en la raíz del Git.
- WHMCS debe poder acceder a las variables de entorno servidor-privadas (no agregarlas al repositorio ni a campos visibles al cliente):

  - `ECLOUD_DOKPLOY_URL=https://dokploy.tu-dominio-seguro.com`
  - `ECLOUD_DOKPLOY_API_KEY=<token Dokploy privado con permisos mínimos>`
  - `ECLOUD_DOKPLOY_PASSWORD_SEED=<32 bytes aleatorios en hex, longitud 64>`
  - `ECLOUD_DOKPLOY_ENABLE_PROVISIONING=1` **solo durante las pruebas autorizadas**.

Generar la semilla *fuera del chat*, en el servidor autorizado:

```bash
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

Guardar la semilla en un gestor de secretos y respaldarla de forma segura: cambiarla sin migrar las contraseñas deja sin conexión los servicios existentes.

## Producto WHMCS

Crear producto que utilice módulo `eclouddokploy` y rellenar Config Options:

- Dokploy Environment ID: identificador del entorno de prueba existente.
- Git Repository HTTPS: `https://github.com/JoseMorenoSalgado/webappecloud.git`.
- Git Branch: `main`.
- App Port: `3000`.
- Domain: debe estar asignado al servicio en WHMCS.

**Modo piloto recomendado: aprovisionamiento manual por administrador en un solo servicio de prueba**, sin configurar ejecución automática tras el pago. El módulo actualmente deja el servicio WHMCS pendiente como medida de seguridad.

## Recuperación de errores

- Los estados se registran por servicio, y un lock MySQL evita ejecuciones concurrentes.
- Antes de operaciones que podrían crear recursos, el módulo marca un estado `creating_*`. Si falla la comunicación con Dokploy, **no repite a ciegas** esas llamadas para evitar duplicados; requiere conciliación manual con los IDs reales de Dokploy.
- Los pasos de configuración Git / Dockerfile / env se pueden reintentar si la conexión falla.
- No se implementa `TerminateAccount` ni eliminación automática de bases de datos o volúmenes; evitar pérdida de información.
- Los errores retornados al cliente y a WHMCS no incluyen claves o cadenas de conexión.
- Instalar, desplegar y aplicar migraciones SQL son operaciones distintas: **la creación de contenedores no equivale a una instalación completa del CMS**.

## Limitaciones actuales y trabajo pendiente

- Pruebas de integración reales contra tu versión de Dokploy y WHMCS, incluida validación del nombre DNS interno generado por Dokploy.
- Comprobar que MariaDB esté lista antes de ejecutar migraciones Prisma; una descarga Git e imagen Docker no crea por sí sola las tablas.
- Estado asíncrono de deployments, espera/confirmación de healthcheck y activación automática segura.
- Git privado, asignación WHMCS de cuentas de correo cPanel, publicación de DNS mediante proveedor autoritativo, backup/restauración.
- Optimización de imágenes: **la opción inicial de Dockerfile compila en Dokploy**; pasar después a CI con imagen precompilada e inmutable para ahorrar disco y CPU.
- Controles de políticas de cuota, cancelación/suspensión y recuperación ante fallos; no usar en producción todavía.
- Tests sintácticos y de validación se ejecutan en GitHub Actions para el PR, **pero no son un test de aprovisionamiento real**.

Documentación de los endpoints utilizados: https://docs.dokploy.com/docs/api y https://developers.whmcs.com/provisioning-modules/.
