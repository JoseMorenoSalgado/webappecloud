# Diagnóstico: server-side exception en dominio nuevo WHMCS → Dokploy

Una portada de Next.js que informa "Application error: a server-side exception has occurred"
no implica que falle la compilación. Inspeccionar **Runtime Logs**, no solo Build Logs.

En WebAppECloud, el flujo WHMCS configura DATABASE_URL al crear la MariaDB vacía.
Pero WebAppECloud considera esa variable como conexión configurada y deja inaccesible
el wizard /instalar. La creación de MariaDB desde WHMCS **no crea** tablas, tenant,
dominio ni administrador. Por eso puede fallar la consulta prisma.domain.findUnique
o prisma.sitePage.findUnique aun cuando el contenedor escucha en el puerto 3000.

## Diagnóstico SIN operaciones destructivas

1. Dokploy → proyecto privado del cliente → production → **aplicación** → Logs
   de ejecución. Localiza el error correlacionado con la hora y el digest
   mostrado en el navegador. Nunca publiques DATABASE_URL, passwords o logs
   completos que incluyan secretos.
2. Comprueba el dominio dentro de Dokploy (host y Container Port: **3000**).
   Si el navegador muestra "No es seguro", inspecciona por separado el certificado
   servido por Traefik/Dokploy, su SAN, vencimiento, DNS A/AAAA y emisión Let's Encrypt.
3. Si el repositorio/código con este script ya está en el contenedor, abre
   **Terminal** de la aplicación y ejecuta:

   node scripts/diagnose-dokploy.mjs nexusrealestatenicaragua.com

   No crea tablas, no cambia claves, no aplica migraciones y no toca proyectos.
   Devuelve códigos sin secretos:
   - DATABASE_URL_EFFECTIVE: hay conexión configurada por env o installation.json.
   - SQL_CONNECTION_OK: motor SQL alcanzable, credenciales válidas y SELECT 1.
   - APPLICATION_SCHEMA_PRESENT: existen tablas clave de la app.
   - DOMAIN_LINKED_TO_ACTIVE_TENANT: dominio exacto registrado para empresa activa.
   - Si el diagnóstico da READY pero la portada falla, mirar Runtime Logs y
     consultas posteriores del home.

4. Comprueba /api/live primero: confirma instancia web, no base de datos.
   /api/health usa SELECT 1: **no confirma que las tablas estén instaladas**.

## Recuperación por causa

- Conexión SQL falla: confirma en Dokploy el hostname interno real, red Docker,
  usuario y permisos, y tipo de base. No imprimas DATABASE_URL.
- Conexión SQL funciona, falta estructura: comprueba si es una base verdaderamente
  nueva/vacía. Antes de crear tablas, **realiza respaldo**. Para instalaciones
  iniciales sin datos debe ejecutarse la inicialización del instalador de
  WebAppECloud con su flujo seguro. **No ejecutes prisma db push sin revisar el
  esquema y la existencia de datos**.
- Hay tablas pero no empresa/dominio: verificar el registro de dominio y el
  tenant desde el panel administrativo existente o la rutina de restauración,
  sin crear una segunda institución sobre datos anteriores.
- Instalación previamente realizada: comprobar que el volumen persistente
  /app/.runtime contiene la configuración ORIGINAL; un Dockerfile de Dokploy
  no incluye automáticamente los volúmenes de docker-compose.yml.

No borrar aplicación, base, volúmenes ni semilla. No reintentar despliegues
mientras hay una compilación en curso. Las correcciones deben probarse antes
en staging y respaldarse SQL + volumen de archivos.
