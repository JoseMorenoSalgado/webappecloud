# Elearning Cloud → Dokploy (Git + bases de datos opcionales) — piloto

**Estado:** primera implementación en revisión. No desplegada ni probada con las credenciales del VPS. No activar automatización de pedidos hasta validar staging.

## Qué hace

Módulo de aprovisionamiento WHMCS `eclouddokploy` para desplegar aplicaciones desde un repositorio público de GitHub, con base de datos **opcional y seleccionable por producto**: MariaDB, MySQL, PostgreSQL, base de datos externa o sin base de datos. WebAppECloud necesita una base de datos SQL; el modo `none` es solo para otras aplicaciones que no la requieran.

1. Parte de un Environment ID **preexistente** en Dokploy; un administrador lo configura en el producto WHMCS.
2. Por cada `serviceid` crea un registro idempotente en `mod_ecloud_dokploy_services`.
3. Para bases administradas, solicita `<motor>.create` con credenciales distintas por servicio y luego `<motor>.deploy`; para `external` reutiliza la URL SQL guardada en un archivo privado, y para `none` no crea una base de datos.
4. Solicita `application.create`, `application.saveGitProvider`, `application.saveBuildType` (`Dockerfile`) y `application.saveEnvironment`.
5. Configura `DATABASE_URL` usando el hostname interno devuelto por `<motor>.one` (o la conexión externa) sin publicar el puerto SQL. En modo `none` omite `DATABASE_URL`.
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
- Si se elige `external`, agregar la variable privada `ECLOUD_DOKPLOY_EXTERNAL_DB_MAP_FILE=/ruta/fuera/webroot/map.json` en el entorno de WHMCS. El archivo JSON debe mapear el ID de servicio a su URL SQL, por ejemplo `{ "123": "postgresql://usuario:clave@host-privado:5432/basedatos" }`. Protegerlo con permisos del sistema, cifrado en reposo y una cuenta SQL por servicio; no introducir contraseñas en campos públicos de WHMCS. Verificar conectividad desde Dokploy hacia el servidor externo, TLS y restricciones de firewall.
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


## Integración con WHMCS y Lagom: servicio independiente

**Decisión adoptada:** Dokploy aparece como otro producto/servicio de WHMCS, dentro de **Mis servicios**, totalmente separado del producto de correo. El módulo comercial de cPanel ya existente continúa administrando los paquetes de correo creados anteriormente, sin cambios en su código ni en su facturación.

1. Copiar este módulo de provisión a \`modules/servers/eclouddokploy/\` en un WHMCS de pruebas.
2. En **Configuración → Productos/Servicios → Productos/Servicios**, crear un **grupo de productos "Aplicaciones web"** si no existe.
3. Crear un producto de tipo hosting o servicio adecuado al catálogo, nombre sugerido **"Hosting de aplicaciones Next.js"**, y seleccionar **eclouddokploy** como módulo en sus opciones.
4. Configurar \`Dokploy Environment ID\`, Git, rama, puerto y motor de base de datos para ese producto. Si el cliente necesita otro motor, utilizar otro producto hasta implementar opciones configurables por pedido.
5. No asignar ni cambiar este módulo en los productos de correo. No editar plantillas de Lagom ni reemplazar la vista completa del servicio.
6. Al contratar la aplicación, el cliente verá **otro renglón en Mis servicios**, y al abrirlo verá una sección propia **"Administrar aplicación"** integrada en la ficha estándar. Facturas y renovaciones mantienen el flujo habitual de WHMCS.

### Función y plantilla implementadas

- \`eclouddokploy_ClientArea\` devuelve \`tabOverviewModuleOutputTemplate\` apuntando a \`templates/clientarea.tpl\`: se añade a la ficha de un producto sin sustituir facturación, dominio u otros datos de Lagom.
- Comprueba propietario, ID, producto y dominio desde \`tblhosting\`, y no llama a Dokploy cuando el cliente abre la página.
- Presenta el estado almacenado del proceso de aprovisionamiento, dominio, tipo de base de datos, repositorio y rama. El estado "Despliegue solicitado" **no afirma** que el sitio esté funcionando.
- En esta versión, la ficha de cliente es **de solo lectura**. No permite alterar el repositorio, borrar bases, realizar despliegues ni acceder al panel administrador de Dokploy.
- Se requiere validar visualmente en la versión y variante de Lagom instalada; las variables de estilo tienen valores de respaldo y todo CSS está limitado al componente de Dokploy.

**Importante:** esto es la integración de interfaz para separar los servicios; los tests offline y la vista no confirman que el aprovisionamiento real de Dokploy funcione en producción. Mantener \`ECLOUD_DOKPLOY_ENABLE_PROVISIONING\` apagado hasta completar pruebas de staging.


## Producto WHMCS

Crear producto que utilice módulo `eclouddokploy` y rellenar Config Options:

- Dokploy Environment ID: identificador del entorno de prueba existente.
- Git Repository HTTPS: `https://github.com/JoseMorenoSalgado/webappecloud.git`.
- Git Branch: `main`.
- App Port: `3000`.
- Database Mode: `mariadb`, `mysql`, `postgres`, `external`, `none`. Se selecciona por **producto WHMCS**; la elección por pedido/cliente requiere una opción configurable adicional o productos separados.
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
- Comprobar que la base de datos seleccionada esté lista antes de ejecutar migraciones Prisma; una descarga Git e imagen Docker no crea por sí sola las tablas. Para WebAppECloud, MariaDB y MySQL usan el esquema Prisma MySQL, mientras PostgreSQL necesita el esquema y cliente Prisma PostgreSQL existentes; confirmar la ruta real de selección antes de declararlo compatible en producción. MongoDB y Redis pueden servir a otras aplicaciones pero **no son motores relacionales alternativos intercambiables para WebAppECloud**.
- Estado asíncrono de deployments, espera/confirmación de healthcheck y activación automática segura.
- Git privado, asignación WHMCS de cuentas de correo cPanel, publicación de DNS mediante proveedor autoritativo, backup/restauración.
- Optimización de imágenes: **la opción inicial de Dockerfile compila en Dokploy**; pasar después a CI con imagen precompilada e inmutable para ahorrar disco y CPU.
- Controles de políticas de cuota, cancelación/suspensión y recuperación ante fallos; no usar en producción todavía.
- Tests sintácticos y de validación se ejecutan en GitHub Actions para el PR, **pero no son un test de aprovisionamiento real**.

Documentación de los endpoints utilizados: https://docs.dokploy.com/docs/api y https://developers.whmcs.com/provisioning-modules/.

## Opciones de base de datos

| Modo | Se crea base nueva en Dokploy | Conexión |
|---|---|---|
| MariaDB | Sí | Red interna TCP/3306, protocolo mysql |
| MySQL | Sí | Red interna TCP/3306, protocolo mysql |
| PostgreSQL | Sí | Red interna TCP/5432, protocolo postgresql |
| Externa | No | URL SQL secreta por service ID, provista por administrador |
| Sin BD | No | Omite `DATABASE_URL`; **no válido para WebAppECloud** |

El módulo mantiene la modalidad elegida para cada servicio y rechaza cambios durante el aprovisionamiento a fin de impedir instalaciones duplicadas o mezclas de credenciales. Una migración entre motores es otro proceso que exige exportación, pruebas y cambio controlado de la aplicación.
