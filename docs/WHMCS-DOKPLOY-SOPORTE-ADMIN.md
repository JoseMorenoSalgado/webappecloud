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
- Si algún paso remoto quedó ambiguo (ej. `creating_project`, `creating_app`), no se reintenta automáticamente ni se crean proyectos nuevos.
- **No autoriza crear infraestructura sin pago** en esta versión. Una cortesía/demo comercial debe formalizarse como un servicio de prueba facturado o activado conforme a un flujo de autorización separado; no bypass del control de pago.

## Ejemplo: pedido WHMCS #324

El pedido #324 y el **ID de servicio** no son lo mismo. En el producto App Ecloud, revisa que la primera factura esté marcada como Paid, y que el servidor sea App1. Pulsa «Crear / verificar proyecto Dokploy», y busca en Dokploy → Projects un nombre como `ec-client-{id_cliente}-service-{id_servicio}`, usando sus IDs reales. Si el cliente necesita que soporte despliegue el sitio, guarda su GitHub en los campos del módulo y pulsa «Instalar aplicación del cliente».

## Configuración previa una sola vez

En producto WHMCS, módulo `eclouddokploy`, «Instalación automática» `guiado` (o `pedido` si incluye GitHub durante compra), grupo de servidores Dokploy y motor de BD. El servidor WHMCS tiene Hostname y token API Dokploy, límite máximo 10. Los permisos de administrador se aplican mediante el login nativo WHMCS.

## Verificaciones obligatorias

Las pruebas automáticas verifican formularios HTML escapados, sesión admin, cliente/proveedor, GitHub seguro, escritura de borrador cifrado, creación del proyecto y su entorno con un mock HTTPS, instalación en el mismo entorno, rechazo de facturas impagas, doble instalación y credenciales no expuestas. Aun así, antes de uso productivo faltan pruebas reales con WHMCS y Dokploy v0.30.9, la MariaDB, DNS, TLS, permisos de cPanel, backups y recuperación.
