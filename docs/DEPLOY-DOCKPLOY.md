# Despliegue de PRUEBAS en Dockploy sin afectar Edvorya

Este procedimiento despliega WebAppECloud/NEXUS en el VPS existente mediante Docker Compose, con MariaDB aislada y datos ficticios. **No ejecutar como reemplazo de Edvorya** y no abrir el panel al público hasta completar seguridad y pruebas.

## Diagnóstico en dos etapas

**Etapa 1: servidor web y proxy (sin MariaDB).** En la aplicación Dockerfile, el chequeo Docker usa `/api/live` y debe devolver HTTP 200 con `{"status":"online","service":"webappecloud"}` aunque MariaDB no esté configurada. En el navegador, prueba `https://inmo.elearningcloud.org/api/live`. Si continúa el 502, verifica en Dockploy que el contenedor esté **running**, que Domains apunte al servicio correcto y que Container Port sea `3000`. Revisa los **runtime logs**, no solo los build logs.

**Etapa 2: MariaDB.** `/api/health` confirma disponibilidad de base de datos y devuelve HTTP 503 cuando no está configurada o no responde. Hasta crear el esquema y sembrar el dominio, las páginas públicas pueden mostrar 404 o error; no equivale a fallo de compilación.

## Precondiciones

- Confirmar RAM, CPU, espacio en disco y carga actual de Edvorya antes de desplegar. Reservar un margen para compilaciones.
- Acceso administrativo a Dockploy; repositorio GitHub visible desde su integración.
- Un subdominio de pruebas que apunte por DNS al VPS y pueda habilitarse con HTTPS.
- Respaldar configuración de Dockploy; esta nueva app no reutiliza las redes, puertos ni bases de Edvorya.

## Crear servicio en Dockploy

1. Projects → proyecto nuevo **WebAppECloud – Staging** (distinto al de Edvorya).
2. Add service → **Compose** (Docker Compose). Activar **Isolated Deployments** en las utilidades de Compose para que Traefik alcance la app sin compartir red con Edvorya. En **Preview Compose** comprobar que el servicio `app` está conectado a la red creada por Dockploy.
3. Source: GitHub → `JoseMorenoSalgado/webappecloud`, rama de staging que esté bajo pruebas; después de aprobar el lanzamiento, `main`.
4. Compose file: `./docker-compose.yml`. Contenedor público: servicio `app`, puerto interno **3000**.
5. Configurar variables secretas EN DOCKPLOY (nunca en Git):

   - `MARIADB_PASSWORD`: clave larga para usuario `webappecloud`.
   - `MARIADB_ROOT_PASSWORD`: clave diferente para root de MariaDB.
   - `DATABASE_URL`: `mysql://webappecloud:<CLAVE_URL_ENCODED>@db:3306/webappecloud`.

   IMPORTANTE: Los caracteres especiales de la contraseña deben estar codificados como URL en `DATABASE_URL` (por ejemplo `@` → `%40`). La contraseña de `MARIADB_PASSWORD` permanece sin codificar. Se recomienda almacenarlas solo como variables de entorno/secretos del despliegue.

6. Antes de desplegar, revisar **Preview Compose** y verificar que `app` tenga dos redes: la privada `webapp-internal` para comunicarse con `db`, y la red aislada añadida por Dockploy para Traefik. Compilar y desplegar. **No** habilitar `ports: "3000:3000"` ni `3306:3306`: se colisionaría con servicios existentes o expondría la base de datos.
7. Abrir terminal **del contenedor app** y ejecutar, SOLO PARA STAGING:

   ```bash
   npx prisma db push
   SEED_DOMAIN=tu-subdominio-de-pruebas.example.com npm run db:seed
   ```

   Para crear una cuenta de pruebas, ejecutar la semilla con `ADMIN_EMAIL` y `ADMIN_PASSWORD` (mínimo 14 caracteres) como variables secretas en el entorno, o asignarlas temporalmente al comando de seed sin registrar la contraseña en el historial de shell. Elimina estas variables tras sembrar usuarios. **No colocar credenciales reales en Git**.

8. En el servicio Compose → Domains → Add Domain: servicio `app`, puerto de contenedor `3000`, host `tu-subdominio-de-pruebas.example.com`, HTTPS/Let's Encrypt. Asegurarse de que DNS apunte al VPS.
9. Verificar primero `https://tu-subdominio-de-pruebas.example.com/api/live` (servidor activo). Tras configurar MariaDB y aplicar esquema, revisar `https://tu-subdominio-de-pruebas.example.com/api/health`, home, catálogo, PDF, formularios y `/admin/acceso`.

## Límites y aislamiento

- Edvorya continúa con su propia configuración. No cambiar sus puertos, volúmenes, variables ni contenedores.
- MariaDB no expone puerto al host. Solo `app` accede por la red privada Compose.
- Cada app tiene 1 GiB de memoria como techo de ejecución **inicial**; la compilación Docker puede usar memoria adicional. Ajustar con mediciones reales.
- Las contraseñas y los datos de MariaDB sobreviven reinicios gracias al volumen `webapp-mariadb`.
- La primera semilla genera **propiedades ficticias**. No usar datos de clientes reales en staging sin completar privacidad, rate limiting y validación.
- Los cambios en `docker-compose.yml` o dominios del servicio Compose pueden requerir redepliegue.

## Para producción

No reutilizar el archivo de staging sin revisión. Antes de producción se requieren migraciones versionadas, copias de seguridad con restauración verificada, almacenamiento duradero para imágenes, pruebas de seguridad, alertas y mediciones de carga.

### Actualizar desde main sin pérdida de datos

1. Antes de fusionar, identificar la rama de Git conectada a Dockploy y si **Auto Deploy** está activado. No suponer que un merge es inocuo.
2. Crear respaldo consistente de **la base de datos SQL** y de **los volúmenes persistentes** de la aplicación (`/app/.runtime`, incluidas las imágenes). Confirmar fecha, tamaño, ubicación y capacidad de restauración.
3. Validar en staging con una copia no sensible de los datos: instalador ya configurado, inicio de sesión, panel, edición de páginas, carga WebP, propiedad y formulario de contacto.
4. Integrar `main` solo cuando las comprobaciones visuales y funcionales estén aprobadas; desplegar usando la imagen validada.
5. Confirmar `/api/live`, `/api/health`, navegación pública y login después del despliegue. Ante fallo, volver al despliegue anterior **sin borrar ni reinstalar los volúmenes SQL o de archivos**.

GitHub Actions verifica el código e imagen Docker, **pero no demuestra que exista una copia restaurable de los datos reales ni sustituye las pruebas visuales en el entorno publicado**.
