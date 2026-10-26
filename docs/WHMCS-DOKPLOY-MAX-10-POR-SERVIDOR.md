# WHMCS → Dokploy: máximo 10 instalaciones por servidor

## Política de asignación solicitada

Esto es exactamente la función nativa de **WHMCS Server Groups**:

- Dokploy VPS 1: **Maximum No. of Accounts = 10**.
- Dokploy VPS 2: **Maximum No. of Accounts = 10**.
- Dokploy VPS 3: **Maximum No. of Accounts = 10**.
- Crear el grupo WHMCS `Dokploy - Aplicaciones` con los tres servidores.
- En **Fill Type**, seleccionar **Fill active server until full then switch to next least used**.
- Asignar el grupo al producto `Hosting de aplicaciones Next.js`, que usa el módulo `eclouddokploy`.

Así WHMCS selecciona VPS 1 para las primeras 10 cuentas y después VPS 2. Con VPS 2 lleno, continuará en VPS 3. No se necesita escribir un balanceador de pedidos nuevo. Esta asignación es por cantidad de **servicios en WHMCS**, no por porcentaje de CPU, RAM o almacenamiento.

**Si solo queda un servidor disponible y alcanza su máximo, WHMCS puede seguir intentando asignar cuentas en algunas circunstancias. Por seguridad, el módulo también comprueba el máximo antes de crear recursos y rechaza el servicio 11 si ya existen 10 reservas activas/pending/suspended asignadas al mismo destino. No mueve el pedido él mismo: hay que añadir otro servidor al grupo o corregir la asignación.**

## Preparar los destinos

1. En WHMCS, abrir **Configuración → Ajustes del sistema → Servidores**.
2. Registrar los servidores, cada uno con el tipo de módulo `eclouddokploy`, URL/hostname administrativo e IP si corresponde.
3. En cada servidor, configurar **Maximum No. of Accounts = 10** y asegurarse de que esté habilitado.
4. Crear **Create New Group**, nombre `Dokploy - Aplicaciones`; seleccionar **Fill active server until full then switch to next least used**, agregando los servidores.
5. En **Configuración → Productos/Servicios → Hosting de aplicaciones Next.js → Module Settings**, seleccionar módulo `eclouddokploy` y servidor/grupo `Dokploy - Aplicaciones`.
6. Activar aprovisionamiento al recibir el primer pago **solo después de validar todos los pasos en staging**.

Los clientes de correo usan su módulo cPanel actual y no forman parte del grupo Dokploy.

## Conexión de cada servidor Dokploy (sin archivos adicionales)

El módulo puede leer **directamente la configuración de Servidores de WHMCS**, de manera semejante al módulo de cPanel:

| Campo de servidor WHMCS | Qué debes poner |
|---|---|
| Name | Dokploy VPS 1 |
| Module | eclouddokploy |
| Hostname | dokploy1.ejemplo.com (sin https ni puerto) |
| Username | **Vacío** para servidores nuevos: cada servicio genera su propio proyecto y entorno |
| Password | **Token API de Dokploy** de ese servidor (privado en WHMCS) |
| Maximum No. of Accounts | **10** |

Repite con Dokploy VPS 2, VPS 3, etc. En cada uno introduce su propio hostname, Environment ID y token. Haz clic en **Test Connection** para validar autenticación y HTTPS sin crear ningún recurso.

**No necesitas editar un archivo JSON** para este caso habitual de varias instalaciones de Dokploy.

Internamente WHMCS decide el servidor usando `tblhosting.server`; el módulo recibe `$params['serverid']` y utiliza el hostname/credenciales **de ese servidor**, no las de otro.

### Modo avanzado (opcional)

Solo si tienes **un panel Dokploy central que administra múltiples VPS remotos**, el administrador podrá configurar `ECLOUD_DOKPLOY_SERVER_MAP_FILE` para mapear cada servidor WHMCS a un `remote_server_id` diferente. El archivo se almacena fuera de la raíz web con permisos restringidos. Este modo se usa únicamente cuando se necesita seleccionar explícitamente nodos remotos.

## Cómo queda ligado el cliente

- El primer servicio se crea en el VPS elegido por WHMCS.
- El módulo guarda una huella del destino: ID del servidor WHMCS, URL del panel Dokploy, Environment ID y Remote Server ID.
- Posteriores consultas y despliegues del cliente se envían **solo a ese destino**.
- Si WHMCS asigna otro servidor, o cambias la ruta del servicio después de crearlo, las operaciones quedan bloqueadas. Migrar entre VPS es una tarea administrada distinta que requiere respaldar/trasladar la base de datos y el almacenamiento.
- Si el servidor de un cliente deja de responder, el módulo **no** crea una segunda aplicación en otro VPS automáticamente. Eso necesitaría una arquitectura HA/failover distinta.

## Casos de aceptación antes de producción

1. Crear diez pedidos de prueba en el grupo y verificar que los diez vayan al primer servidor.
2. Crear el undécimo pedido y confirmar que WHMCS le asigne el segundo servidor.
3. Verificar que ningún servicio real se haya creado en el servidor incorrecto.
4. Forzar manualmente un servicio 11 al primer servidor y confirmar que el módulo **rechaza** esa instalación sin llamar a Dokploy.
5. Enviar una solicitud desde otro cliente y comprobar que no puede ver o administrar aplicaciones ajenas.
6. Probar la capacidad cuando un servicio está suspendido, pendiente o cancelado. El prototipo reserva capacidad para Pending/Active/Suspended; Cancelled/Terminated dejan de contar. Ajustar según política real de facturación antes de vender.
7. Actualizar un producto existente sin migrar aplicaciones por accidente.
8. Mantener segregados los productos de correo/cPanel del grupo Dokploy.

## Referencias

- https://docs.whmcs.com/9-0/servers/servers/
- https://docs.whmcs.com/8-10/servers/server-groups/
- https://developers.whmcs.com/provisioning-modules/module-parameters/
- https://docs.dokploy.com/docs/api/application

**Actualización:** ahora el módulo crea un proyecto Dokploy por servicio WHMCS. No hay que crear un proyecto compartido ni obtener un Environment ID para el campo Usuario. Véase `docs/WHMCS-DOKPLOY-PROYECTOS-POR-CLIENTE.md`. No cambiar el Username de servidores con servicios previamente iniciados sin conciliación.
