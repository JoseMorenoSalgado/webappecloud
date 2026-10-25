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

## Conexión de cada servidor Dokploy

WHMCS decide el servidor usando `tblhosting.server`; el módulo recibe `$params['serverid']`. Cada servidor WHMCS se enlaza con su instancia/nodo mediante una configuración privada.

Ejemplo JSON (solo plantilla, no contiene claves reales):

```json
{
  "servers": {
    "11": {
      "url": "https://dokploy1.example.com",
      "api_key": "TOKEN_PRIVADO_DOKPLOY_1",
      "environment_id": "env_001",
      "remote_server_id": null
    },
    "12": {
      "url": "https://dokploy2.example.com",
      "api_key": "TOKEN_PRIVADO_DOKPLOY_2",
      "environment_id": "env_002",
      "remote_server_id": null
    },
    "13": {
      "url": "https://dokploy3.example.com",
      "api_key": "TOKEN_PRIVADO_DOKPLOY_3",
      "environment_id": "env_003",
      "remote_server_id": null
    }
  }
}
```

Donde **11, 12 y 13 son ejemplos de IDs internos de WHMCS** (no la posición visual ni el ID que muestra Dokploy). Si tienes **un Dokploy central gestionando VPS remotos**, puedes repetir URL y API key pero asignar diferentes `remote_server_id` a cada uno.

Guardar el archivo **fuera de public_html**, accesible únicamente al usuario PHP de WHMCS (modo 0600). Definir:

```ini
ECLOUD_DOKPLOY_SERVER_MAP_FILE=/ruta/privada/whmcs/dokploy-servers.json
```

Mantener **ECLOUD_DOKPLOY_ENABLE_PROVISIONING=0**, **ECLOUD_DOKPLOY_ENABLE_CLIENT_REDEPLOY=0** y **ECLOUD_DOKPLOY_ENABLE_CLIENT_READS=0** hasta finalizar las pruebas.

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
