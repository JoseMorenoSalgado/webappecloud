# Pruebas reales WHMCS → Dokploy, siguiendo el esquema de Edvorya

## Qué se recuperó del proyecto Edvorya

En Edvorya, la ejecución real la hacía un **GitHub Actions self-hosted runner** (`edvorya-ci`) en una máquina de CI aislada, no una terminal remota permanente del chat. Edvorya-LMS documenta el runner y los workflows en `docs/SELF-HOSTED-CI-RUNNER.md`.

Esta implementación replica el principio con el runner de etiqueta **`ecloud-dokploy-ci`**, dedicado al repositorio `JoseMorenoSalgado/webappecloud`.

**Todavía no existe una conexión confirmada al VPS Dokploy desde esta conversación.** El workflow quedó preparado en la rama PR #38, no ejecutado contra el servidor real.

## Alcance del workflow preparado

Archivo: `.github/workflows/dokploy-staging-readonly.yml`

- Ejecución manual exclusivamente (`workflow_dispatch`) desde `main`, una vez aprobado e incorporado el workflow.
- Runner requerido: `self-hosted`, `linux`, `ecloud-dokploy-ci`.
- Entorno GitHub Actions: `dokploy-staging`, con protección de aprobación.
- Solo realiza HTTPS GET mediante `remote_readonly.php`: `project.all`, opcionalmente `application.one` y `deployment.all` para una aplicación de **staging**.
- Comprueba TLS, token, HTTP, formato básico de JSON y que el ID devuelto corresponde a la aplicación de prueba.
- Ninguna llamada POST, no instala bases de datos, no reinicia ni despliega contenedores.
- Salida limitada a estados y conteos, sin mostrar API keys o respuestas completas.

## Paso 1: configurar un entorno de pruebas separado

Recomendación para un repositorio **público**: usar un runner desechable o VM de CI aislada con acceso por HTTPS/443 al Dokploy de pruebas. **No instalar el runner dentro del VPS productivo de Dokploy, ni convertir una aplicación de cliente en staging**.

Si ya existe una máquina dedicada de Edvorya CI, no reutilizar por defecto ese runner: fue registrado para el repo privado Edvorya-LMS. Evaluar de manera separada si esa misma infraestructura puede alojar una VM/instancia aislada para este proyecto.

Instalar PHP CLI 8.2+, `php-curl`, certificados CA y Git en la VM. Para la prueba de **solo lectura** no se necesita pertenecer al grupo Docker.

## Paso 2: registrar runner del nuevo repositorio

Entrar a GitHub:

```text
JoseMorenoSalgado/webappecloud
  → Settings
  → Actions
  → Runners
  → New self-hosted runner
```

Seleccionar Linux. GitHub entrega comandos de descarga, checksum y registro, con un token temporal. **Ejecutar esos comandos en la máquina de CI aislada con usuario no-root**, tal como indique GitHub, sin pegar el token en chats, PRs, tickets ni capturas.

Asignar la etiqueta extra `ecloud-dokploy-ci` en el registro (usando las opciones de `config.sh` que muestra GitHub). Confirmar que figura `Online`.

Nunca instalar en servidores web o de datos productivos. Revisar permisos de la carpeta del runner y evitar que ejecute PRs o código no confiable.

## Paso 3: configurar los secretos GitHub

En el repositorio, `Settings → Environments → New environment`, crear:

```text
dokploy-staging
```

Activar aprobaciones requeridas si tu plan/configuración lo permite. Agregar **Environment secrets**:

- `ECLOUD_DOKPLOY_URL`: URL HTTPS de la **instancia Dokploy de staging**, no el panel de un cliente.
- `ECLOUD_DOKPLOY_API_KEY`: token privado de Dokploy autorizado para consultas; si no hay permisos de solo lectura en esa versión, restringir el acceso por red y no usar un token de producción.

Opcionalmente agregar una **Environment variable** (no secreto):

- `ECLOUD_DOKPLOY_TEST_APPLICATION_ID`: ID de una aplicación de prueba en Dokploy, sin datos de clientes.

Estas variables se almacenan **solo en GitHub**; no se escriben en el repositorio y no las necesita ChatGPT.

## Paso 4: habilitar y ejecutar el diagnóstico real

El workflow actualmente está en el **PR #38**, por lo que no se puede lanzar desde la rama principal hasta que esté revisado y se incorpore de manera controlada.

Después:

```text
Actions → Dokploy staging — real read-only API check
  → Run workflow → main
```

El runner solicita la URL y el token directamente a GitHub y ejecuta `remote_readonly.php`.

Un resultado `pass` confirma que la conexión y los endpoints de consulta funcionan con tu versión de Dokploy. **No confirma** creación de bases de datos, despliegue, migraciones, SSL, DNS ni aplicación saludable: eso se validará después con un proyecto de staging explícito.

## Paso 5: habilitar pruebas de escritura solo en staging

Una vez completada la fase de solo lectura, preparar un **proyecto Dokploy de ensayo separado**, con dominio DNS de pruebas, base de datos desechable, cuotas y un procedimiento de limpieza explícito. Solo allí probar `application.create`, `mysql/mariadb/postgres.create`, `application.deploy` y cancelaciones controladas.

No automatizar `TerminateAccount` ni pruebas que borren datos de clientes. Mantener los módulos WHMCS de correo/cPanel y los productos existentes completamente intactos.

## Evidencia y criterios de salida

Guardar en GitHub Actions los códigos de comprobación, duración, errores sanitizados y compatibilidad API. El módulo WHMCS sigue como PR **draft** hasta validar la integración real. No publicar secretos en logs, artefactos ni mensajes.
