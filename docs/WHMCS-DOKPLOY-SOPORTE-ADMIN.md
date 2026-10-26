# Soporte administrativo — crear proyectos y desplegar aplicaciones desde WHMCS

**Estado: PR #38 — piloto en staging.** Las acciones trabajan sobre el servicio WHMCS existente y no requieren iniciar sesión como cliente ni abrir el panel Dokploy. Validar en el VPS real antes de activar para clientes pagados de producción.

## Dónde aparece

WHMCS Admin → Clientes → abrir cliente → Productos/Servicios → elegir **App Ecloud** → ficha del servicio.

El módulo de servidor `eclouddokploy` incorpora funciones nativas de WHMCS `AdminServicesTabFields` y `AdminCustomButtonArray` (no es un addon; no aparece en «Módulos adicionales»). En «Detalles del servicio / ajustes del módulo» aparecen las filas:

| Campo visible para soporte | Utilidad |
| --- | --- |
| Proyecto Dokploy · Soporte | Estado, dominio, IDs de proyecto y entorno vinculados |
| Repositorio GitHub del cliente | URL HTTPS **pública** del GitHub que desea publicar |
| Rama GitHub | `main` por defecto |
| Carpeta de aplicación | `/` o `/apps/frontend` para monorepo |
| Tipo de aplicación | Next.js Nixpacks (sin Dockerfile), Node.js, Dockerfile, o autodetección |
| Puerto interno | `3000` por defecto |
| Variables de entorno | Opcional, un `CLAVE=valor` por línea. Se almacenan cifradas, no reaparecen en pantalla |
| Instalación asistida · Instrucciones | Pasos y restricciones de estado |

## Dos comandos del administrador

### A) Crear / verificar proyecto Dokploy

En **Comandos del módulo**, selecciona **«Crear / verificar proyecto Dokploy»**. El módulo:

1. Verifica sesión de administrador de WHMCS.
2. Verifica que el servicio pertenece al cliente y al producto correcto y está asignado al VPS original.
3. Verifica la primera factura `Paid` y la capacidad del servidor (ejemplo 10 plazas).
4. Crea el proyecto `ec-client-{CLIENT_ID}-service-{SERVICE_ID}` mediante `project.create` y su entorno `production` si aún faltaban.
5. Guarda los IDs en `mod_ecloud_dokploy_services`.

**No hace falta indicar GitHub** para crear únicamente el proyecto. Volver a pulsar el comando con estado `environment_ready` no crea recursos duplicados. Si Dokploy aceptó una escritura pero se perdió su respuesta, el módulo devuelve una advertencia y exige verificar manualmente el estado remoto (no reintenta a ciegas).

### B) Instalar aplicación del cliente

Si el cliente solicita ayuda con su página:

1. Introduce el **repositorio real de ese cliente** en el campo administrativo, rama, tipo de app y, si corresponde, carpeta y puerto.
2. En WHMCS pulsa **Guardar cambios** para guardar el borrador técnico, no para crear recursos remotos.
3. En **Comandos del módulo**, pulsa **«Instalar aplicación del cliente»**.
4. El módulo verifica el primer pago y estado Active, crea o verifica el proyecto privado, guarda la configuración inmutable y crea en el mismo entorno: BD del plan, aplicación Git, build, variables, dominio y solicitud de despliegue.

No necesita que el cliente entre a WHMCS ni que soporte conozca su contraseña. La app se aprovisiona mediante la API key de Dokploy que ya guardaste en el servidor WHMCS. La base de datos del plan se instala solo al instalar la aplicación (no al crear el proyecto vacío).

Al terminar, WHMCS reporta éxito cuando **se ha solicitado** el despliegue. Que Dokploy haya recibido la solicitud NO significa que DNS, HTTPS, compilación ni salud de la web estén verificados. Se debe revisar el despliegue real antes de comunicar al cliente que está publicado.

## Seguridad y recuperación

- Los botones solo están disponibles para una sesión administrativa real de WHMCS. La ejecución vuelve a validar cliente, producto, servidor, dominio, estado y primera factura `Paid`.
- Los administradores no pueden seleccionar otro VPS mediante el formulario: se utiliza la asignación exacta del servicio y su huella de ruta original.
- Las URL GitHub solo se aceptan con `https://github.com/usuario/repositorio`, con rama, carpeta y puerto validados. Por ahora **sin GitHub privado/OAuth**.
- Los borradores administrativos viven en `mod_ecloud_dokploy_admin_drafts`, separados de las credenciales y del propio panel de Dokploy. Las variables de entorno opcionales se cifran con `sodium` y clave derivada por servicio.
- La configuración queda **inmutable** cuando se guarda para crear recursos; una segunda edición se bloquea para prevenir confusión y sobrescritura de aplicaciones ajenas.
- Si el cliente ya configuró GitHub, soporte utiliza esa configuración guardada y no necesita volver a introducirla.
- No se expone al navegador la API key, contraseñas de BD o variables descifradas.
- Las acciones de soporte (guardar repositorio, crear/verificar proyecto y solicitar despliegue) dejan una entrada en el registro de actividad de WHMCS con ID del administrador, servicio y cliente, **sin credenciales, URL Git ni secretos**.
- Si algún paso remoto quedó ambiguo (ej. `creating_project`, `creating_app`), no se reintenta automáticamente ni se crean proyectos nuevos.
- **No autoriza crear infraestructura sin pago** en esta versión. Una cortesía/demo comercial debe formalizarse como un servicio de prueba facturado o activado conforme a un flujo de autorización separado; no bypass del control de pago.

## Ejemplo: pedido WHMCS #324

El pedido #324 y el **ID de servicio** no son lo mismo. En el producto App Ecloud, revisa que la primera factura esté marcada como Paid, y que el servidor sea App1. Pulsa «Crear / verificar proyecto Dokploy», y busca en Dokploy → Projects un nombre como `ec-client-{id_cliente}-service-{id_servicio}`, usando sus IDs reales. Si el cliente necesita que soporte despliegue el sitio, guarda su GitHub en los campos del módulo y pulsa «Instalar aplicación del cliente».

## Configuración previa una sola vez

En producto WHMCS, módulo `eclouddokploy`, «Instalación automática» `guiado` (o `pedido` si incluye GitHub durante compra), grupo de servidores Dokploy y motor de BD. El servidor WHMCS tiene Hostname y token API Dokploy, límite máximo 10. Los permisos de administrador se aplican mediante el login nativo WHMCS.

## Verificaciones obligatorias

Las pruebas automáticas verifican formularios HTML escapados, sesión admin, cliente/proveedor, GitHub seguro, escritura de borrador cifrado, creación del proyecto y su entorno con un mock HTTPS, instalación en el mismo entorno, rechazo de facturas impagas, doble instalación y credenciales no expuestas. Aun así, antes de uso productivo faltan pruebas reales con WHMCS y Dokploy v0.30.9, la MariaDB, DNS, TLS, permisos de cPanel, backups y recuperación.


## Corrección del error «La creación del proyecto está pendiente o requiere revisión»

En versiones anteriores, el aprovisionamiento dejaba algunos servicios en estado `new`, que el creador de proyectos no procesaba, aunque aún no hubiera enviado `project.create`. Otra causa posible es que Dokploy haya recibido `project.create` o `environment.create`, pero WHMCS no haya recibido o guardado el ID (estados `creating_project` y `creating_environment`).

La nueva versión ejecuta una conciliación **de solo lectura y con el mismo bloqueo MySQL por servicio**:

1. Comprueba cliente, primera factura `Paid`, dominio, producto, grupo/VPS asignado y huella de ruta.
2. Lee `GET /api/project.all` en Dokploy y busca el **nombre exacto** `ec-client-{CLIENT_ID}-service-{SERVICE_ID}`. Verifica formato de ID, descripción (si está disponible) y unicidad. Rechaza nombres ambiguos o distintos. Nunca busca por prefijo ni reutiliza `WebAppECloud` o proyectos de otros clientes.
3. Si el proyecto existe y no está registrado en WHMCS, guarda su `projectId`; luego lee `GET /api/environment.byProjectId?projectId=...` y adopta **solo** el entorno `production` de ese proyecto.
4. Si estado `new` o `awaiting_setup` y no existe proyecto, puede crearlo por primera vez. Si el estado ya era `creating_project` pero no aparece en la lectura remota, **no** repite el POST; devuelve una advertencia que exige revisión de logs. Equivalente para `creating_environment` cuando no aparece `production`.
5. Si hay duplicados, descripción incompatible, cambio de VPS o proyecto remoto desconocido, aborta sin mutar recursos.

### Recuperación del pedido #324

- **No elimines el servicio, no cambies su servidor WHMCS App1 ni borres proyectos en Dokploy.**
- Haz respaldo del módulo y base de datos WHMCS; instala el ZIP actualizado en `modules/servers/eclouddokploy/`.
- Comprueba que la **primera factura de ese pedido** está en `Paid`.
- Abre la ficha del servicio del cliente (no solo la del pedido) → **Comandos del módulo → Crear / verificar proyecto Dokploy**. El comando consulta Dokploy y recupera el proyecto si ya existe. No uses «Instalar aplicación del cliente» hasta que el proyecto/production estén listos.
- En Dokploy → Projects, busca `ec-client-{CLIENT_ID}-service-{SERVICE_ID}`. Los identificadores de cliente y servicio están en la ficha de WHMCS; **no es necesariamente el número de pedido 324**.
- Si permanece el aviso `No encontramos el proyecto pendiente en Dokploy`, la recuperación automatizada se niega a crear un duplicado sin confirmar el resultado de la solicitud anterior. Comprueba el registro de auditoría de Dokploy y solicita conciliación técnica. **No modifiques `state` directamente en SQL**.

### Proyecto existente confirmado: cliente 225 / servicio 293

En la captura real se observa **`ec-client-225-service-293`**, con entorno **`production`**. El número de pedido WHMCS es **324**, pero el nombre del proyecto se deriva de los IDs **225 y 293**, respectivamente.

El botón **«Crear / verificar proyecto Dokploy»** ahora **SIEMPRE** consulta primero `project.all`, incluso si WHMCS ya marcó la cuenta como `environment_ready` o conservó IDs locales. Recupera el `projectId` y `environmentId` cuando faltan, pero **nunca sustituye un ID existente distinto**. Si el nombre existe exactamente en Dokploy, la función adopta ese proyecto; no llama a `project.create` por segunda vez. Solo se crea un recurso nuevo cuando la respuesta de la API confirma que no existe ninguno y el estado era inicial `new` o `awaiting_setup`.

Este proceso se verifica mediante pruebas automatizadas que recrean un proyecto remoto llamado `ec-client-225-service-293` antes de ejecutar la recuperación, y comprueban que el número de proyectos no aumenta. Se prueban además pérdida de IDs, cambios de ID indebidos, propietario cruzado y un estado `environment_ready` cuyo proyecto remoto ya no existe.

**Atención:** la captura demuestra la existencia del proyecto y entorno, pero no demuestra todavía que el servicio esté correctamente enlazado en la base de datos WHMCS ni que haya una aplicación desplegada. El resultado real de la conciliación debe observarse desde el servidor WHMCS y el panel Dokploy. Mantener la implementación en staging hasta ese paso.

El ZIP se valida con `tests/paid_project_creation_mock.php` y un servidor HTTPS Dokploy simulado. Las pruebas no prueban que el proyecto remoto del usuario exista ni sustituyen inspeccionar sus logs.


## Diagnóstico del error genérico «No fue posible verificar la creación del proyecto Dokploy» (25 oct 2026)

La captura de WHMCS corresponde al servicio #293, cliente #225 y pedido #324. El mensaje antiguo se originaba en el manejador genérico de errores de eclouddokploy_order_prepare_project, que ocultaba incluso las respuestas HTTP de Dokploy. La pantalla por sí sola no demuestra qué error devolvió la API.

**Corrección implementada:**

1. En la consulta inicial se reemplazó el endpoint pesado /api/project.all por GET /api/project.search?name=ec-client-225-service-293&limit=100&offset=0. El formato real de Dokploy es JSON con claves items (array) y total (entero). Se exige que la respuesta esté completa antes de tratarla como ausencia de proyecto. Los resultados se cotejan de forma exacta por nombre, descripción y projectId. Si el servidor no soporta project.search (HTTP 404/405), el módulo intenta la lectura anterior project.all solo como alternativa de compatibilidad. Respuesta HTTP 401/403/500 o inesperada no permite crear otro proyecto.
2. La llamada a environment.byProjectId sigue limitada al projectId confirmado. Se recupera exclusivamente production y se conserva el ID anterior si coincide.
3. Errores operativos seguros en WHMCS, sin secretos: ahora indica project.search o environment.byProjectId y el código HTTP; distingue permisos 401/403, endpoint ausente 404/405, fallo HTTPS (HTTP 0), error HTTP 500 y JSON incompatible. No se muestran respuestas HTTP crudas, tokens, SQL, credenciales ni stack traces.
4. Se añadió una prueba de integración que realiza project.search contra un simulador HTTPS y confirma que recupera ec-client-225-service-293 sin crear duplicados; comprueba también que mensajes SQL ficticios no aparecen en el diagnóstico.

**Contexto de compatibilidad:** Dokploy mantiene /api/project.search en su API y su router retorna items/total con búsqueda parcial de nombre, que WHMCS filtra después por igualdad exacta. El endpoint /api/project.all puede devolver proyectos con servicios anidados, más datos de los necesarios y, según reportes públicos en algunas versiones de Dokploy, errores HTTP 500 de serialización. El cambio evita depender de esa consulta global cuando project.search está disponible.

**Para tu instalación:** respalda WHMCS, actualiza la carpeta del módulo y ejecuta una sola vez el comando «Crear / verificar proyecto Dokploy» del servicio 293. Si la API acepta la búsqueda, WHMCS recupera los IDs sin tocar la infraestructura. Si devuelve HTTP 401/403/500, toma nota del endpoint y código para revisar desde el VPS WHMCS/Dokploy; **no borres ni recrees recursos ni pegues la API key en el chat**.

Fuentes públicas:
- https://docs.dokploy.com/docs/api/project
- https://github.com/Dokploy/dokploy/issues/3793

**Estado:** CI automatizada verificada con simulación HTTPS, todavía no probado sobre el servidor real; staging, no desplegar masivamente sin validación.


## Reparación administrativa cuando se eliminó el proyecto desde Dokploy

**Caso real:** se eliminó de Dokploy el proyecto ec-client-225-service-293 y se ejecutó «Crear / verificar proyecto» en WHMCS. El error «WHMCS tiene un ID de proyecto, pero no aparece en Dokploy para este servicio» era esperado en la versión anterior: MySQL WHMCS conservaba el projectId antiguo aunque el proyecto remoto hubiera desaparecido.

**Nueva recuperación en el mismo comando administrativo:**

1. La ficha debe pertenecer a un administrador WHMCS autenticado, al cliente 225 / servicio 293, estar Active, contar con primera factura Paid, dominio y VPS asignado con huella inmutable.
2. El módulo comprueba en MySQL que el servicio únicamente tiene proyecto y production preparados, con estados project_created o environment_ready, y ninguna aplicación, MariaDB o recurso de dominio previamente provisionado. Si el sistema ya instaló una aplicación, debe utilizarse un procedimiento de restauración con backups; jamás se reinicializa automáticamente.
3. La verificación remota es doble y de solo lectura: GET project.search por nombre exacto ec-client-225-service-293 debe confirmar ausencia; GET project.one?projectId=ID_ANTIGUO debe responder HTTP 404. Si el proyecto simplemente fue renombrado, project.one todavía lo encontrará y no se crea otro. HTTP 401/403/500, fallos TLS, respuestas incompletas o permisos incorrectos no prueban la eliminación, así que se aborta sin alterar la BD.
4. Solo tras las dos pruebas y dentro del mismo lock de servicio, limpia exclusivamente project_id y environment_id, conserva facturación, propietario, dominio, plan, DB mode, claves y VPS, y ejecuta el creador idempotente de proyecto y entorno production. La operación no elimina más recursos.
5. El nuevo ID puede ser diferente del anterior: es correcto para un proyecto recreado. La auditoría WHMCS registra que un administrador realizó la recuperación sin secretos.
6. Si la API no puede certificar la eliminación o existen datos de una aplicación anterior, se devuelve la causa; no borrar filas ni usar SQL manual.

**Cómo probarlo tras actualizar el ZIP STAGING:** primero respaldar WHMCS y confirmar que el proyecto realmente fue eliminado. Abrir ficha del servicio 293 → Comandos del módulo → Crear / verificar proyecto Dokploy, una sola vez. Si pasa las validaciones, crea un proyecto nuevo con el mismo nombre, nuevo projectId y production, sin añadir otro servicio WHMCS. Luego instalar GitHub desde WHMCS; recuperar proyecto no instala aplicaciones ni bases de datos.

**Nota v0.30.9:** el router de Dokploy admite project.search y project.one. La confirmación de borrado se basa en HTTP 404 de project.one; si el servidor responde de otra forma, se conserva el bloqueo por seguridad.

**Pruebas:** simulador HTTPS verifica eliminación externa, reconstrucción una vez, cambio de identificadores, rechazo al renombrar proyecto existente, rechazo ante apps/DB y no duplicación. No sustituye la prueba real del servidor.
