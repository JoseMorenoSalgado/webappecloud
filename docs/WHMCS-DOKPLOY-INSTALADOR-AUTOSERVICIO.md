# Instalador autoservicio tipo Vercel — WHMCS / Lagom / Dokploy

**Estado: PR #38 / PILOTO.** No activar en producción hasta probar de extremo a extremo con tu Dokploy real v0.30.9, WHMCS y cPanel de pruebas.

## Recorrido del cliente

1. Compra y paga su producto «App Ecloud» en WHMCS. El módulo reserva una plaza en el VPS de su grupo (por ejemplo, diez clientes por servidor) sin desplegar código predeterminado.
2. En «Mis servicios → App Ecloud → Administrar» encuentra el asistente «Instala tu aplicación en 3 pasos».
3. Solo **pega la URL HTTPS de su repositorio público de GitHub**. La rama y la carpeta raíz tienen valores predeterminados, y la detección de Next.js es opcional; no necesita conocimientos de Dokploy.
4. Para la mayoría, basta la configuración automática (Next.js/Node.js con **Nixpacks, sin Dockerfile**). Rama, carpeta, puerto, Dockerfile y variables se encuentran en una sección avanzada que puede dejar cerrada.
5. Confirma el dominio de su servicio y la base de datos incluida en su plan; pulsa «Instalar mi aplicación».
6. WHMCS crea un proyecto y entorno Dokploy privado, la base de datos contratada, la aplicación, Git, build, variables, dominio HTTPS y **solicita** el despliegue.
7. Consulta progreso y, si el administrador habilita las lecturas, registros de despliegues recientes. Los buzones cPanel continúan en la misma ficha como servicio separado.

**No se incluyen aún:** OAuth ni repositorios privados, lista automática de repositorios de una cuenta GitHub, deploy automático en cada push, dominios temporales DNS propios, previews por rama o comprobación integral de salud del sitio. No anunciarlos como disponibles.

## Configuración desde WHMCS, sin SSH (primero en staging)

**No hay que crear variables PHP ni usar terminal.** En WHMCS, configura una sola vez el servidor Dokploy con hostname y API key, y el producto App Ecloud con **«Instalación automática» = `guiado`**. Para comprobarlo antes de ofrecerlo a clientes, utiliza productos/servicios de staging.

En **Productos/Servicios → App Ecloud → Module Settings → Automation Settings**, elige **«Automatically setup the product as soon as the first payment is received»**. WHMCS ejecutará CreateAccount al confirmar el pago y reservará una plaza de su grupo de servidores. NO aconsejamos instalar antes del pago.

La primera vez que un cliente instale un proyecto, el módulo genera **automáticamente** su clave maestra aleatoria y la guarda cifrada en `mod_ecloud_dokploy_runtime`, mediante el servicio Crypt de WHMCS/Laravel. Nunca envía ni muestra la clave en pantalla. Si ya existe un `ECLOUD_DOKPLOY_PASSWORD_SEED` configurado para instalaciones antiguas, lo conserva como prioridad para evitar cambiar las contraseñas históricas.

**Respaldos necesarios:** base de datos WHMCS (incluye clave cifrada) **y material criptográfico de la instalación WHMCS**. Restaurar la base de datos sin el mismo material criptográfico puede impedir recuperar secretos y contraseñas de las aplicaciones. No regeneres la clave maestra después de desplegar clientes.

Requisitos: WHMCS con cifrado Crypt inicializado, PHP 8.2+, ext-curl y ext-sodium. Si la inicialización de Crypt no está disponible, el módulo rechaza la instalación en vez de guardar secretos en claro.

Mantén desactivadas las acciones experimentales de redespliegue directo y CRUD de correo hasta su validación real. La ficha del cliente muestra el asistente de instalación por defecto, incluso para un servicio piloto previo que esté `Active` y no tenga una reserva: el registro se prepara automáticamente durante su primera visita.

## Instalación y productos WHMCS

- Configurar uno o más servidores de tipo eclouddokploy con hostname HTTPS válido (sin esquema en el campo Hostname), API key por servidor en Password, Username vacío para instalaciones **nuevas** con proyecto privado.
- Asignar límite máximo de cuentas (ejemplo: 10), agrupar servidores con «Fill active server until full then switch to next least used».
- El producto App Ecloud selecciona dicho grupo y el motor de base de datos (MariaDB, MySQL, Postgres, externo o ninguno).
- WHMCS debe conocer el dominio FQDN del sitio en su ficha de servicio. Solo ese dominio se instala; el cliente no puede sustituirlo mediante POST.
- Configurar DNS A/AAAA al VPS/proxy correcto; Let's Encrypt depende de DNS correcto.
- En la compra, CreateAccount devuelve success cuando reservó la plaza, NO porque el sitio ya esté publicado. El estado del módulo aparece «Preparada para instalar» hasta que el usuario despliegue.
- Si el servicio **ya estaba activo** antes de instalar la actualización, WHMCS prepara su reserva cuando el propietario abre «App Ecloud». No hay botón ni comando administrativo por cuenta.

## Seguridad y manejo de errores

- El navegador no recibe API keys Dokploy, contraseñas SQL ni secretos cifrados.
- Cada acción exige sesión WHMCS y coincidencia exacta de cliente propietario, producto, servidor, estado Active, dominio y token CSRF propio.
- Los repositorios admitidos son GitHub público mediante HTTPS, con nombres, rutas, ramas y puertos validados. Sin soporte OAuth.
- La autodetección consulta únicamente api.github.com, sin redirecciones ni credenciales y con respuestas acotadas. Si GitHub limita o bloquea, el cliente debe elegir el framework manualmente.
- Variables de entorno: hasta 30 pares CLAVE=valor; se cifran con Sodium secretbox y clave derivada de la seed **y el ID del servicio**. No se muestran en Lagom. DATABASE_URL, PORT, NODE_ENV y ECLOUD_* quedan reservadas a la plataforma. Advertir que NEXT_PUBLIC_* puede exponerse al frontend.
- Al instalar, la configuración queda persistida UNA sola vez, antes de iniciar operaciones remotas. Se valida su inmutabilidad.
- Estados «creating_project», «creating_environment» y otras fases potencialmente ambiguas bloquean intentos automáticos; un administrador debe conciliar IDs remotos antes de reanudar. Solo se permite reintentar sin cambios si aún está en «new» sin recursos remotos.
- No se eliminan automáticamente los proyectos ni bases de datos ante errores.
- La acción «InstallApp» devuelve success cuando el estado indica que Dokploy aceptó la solicitud; **no afirma que el sitio esté online**.
- El módulo realiza varias llamadas HTTPS síncronas. Para explotación masiva hay que evaluar latencia, timeouts y cola persistente mediante cron; este prototipo no equivale aún al despliegue tolerante a fallos de Vercel.

## Pruebas automatizadas

- Sintaxis PHP y validaciones locales de Git, monorepos, build, CSRF, estado, cuotas y secretos.
- Cifrado de variables por servicio; otro servicio no puede descifrarlas con su clave derivada.
- Mock HTTPS Dokploy: tres proyectos/entornos privados para varios servicios, configuración de Git + build Nixpacks/Dockerfile + variables + dominio + deploy.
- Mock HTTPS cPanel para CRUD de buzones con dominio/identidad y token correctos.

## Pruebas reales pendientes, bloqueantes

1. Flujo completo de compra y reserva del servicio en WHMCS staging.
2. Acceso de cliente desde Lagom, importación de repositorio Next.js sin Dockerfile y creación de proyecto/entorno real en Dokploy v0.30.9.
3. DNS, SSL real, healthcheck de la app, base de datos persistente y variables.
4. Concurrencia, máximo diez servicios por VPS, asignación al segundo VPS.
5. Errores de Git, dominio, SQL, tiempo de espera, suspensión/terminación y recuperación manual.
6. CPU, RAM, redes aisladas, almacenamiento, backups y restauración por cliente.

Documentación:
- https://vercel.com/docs/git
- https://docs.dokploy.com/docs/api/application
- https://developers.whmcs.com/provisioning-modules/client-area-output/
