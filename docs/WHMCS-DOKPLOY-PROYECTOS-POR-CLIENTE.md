# WHMCS → Dokploy: proyecto privado por servicio de cliente

**Fase piloto / PR #38.** No activar aprovisionamiento automático en producción hasta validar en staging real.

## Cómo funciona

La contratación de un servicio de aplicaciones Next.js en WHMCS produce una asignación de servidor conforme al grupo de servidores: VPS 1 recibe hasta 10 servicios y, al llenarse, WHMCS asigna el siguiente al VPS 2.

El módulo ahora crea en el VPS asignado:

1. Un proyecto Dokploy por servicio, nombre **`ec-client-{WHMCS_CLIENT_ID}-service-{WHMCS_SERVICE_ID}`** (identificador interno, no incluye nombres ni correos).
2. Un entorno **production** exclusivo dentro de ese proyecto. Si Dokploy creó automáticamente su entorno production, se reutiliza **solo si pertenece a ese proyecto**.
3. La base de datos del servicio (MariaDB/MySQL/PostgreSQL) dentro del nuevo entorno, cuando el plan la incluye.
4. Su aplicación Next.js dentro del mismo entorno, código Git, configuración, dominio y solicitud de despliegue.

**Cada servicio tiene un proyecto propio:** si un mismo cliente compra dos aplicaciones, recibe dos proyectos independientes. El proyecto no es una cuenta de usuario Dokploy; los clientes solo acceden a sus servicios mediante su login de WHMCS y Lagom.

El módulo almacena en la tabla `mod_ecloud_dokploy_services`:

- `service_id` e `owner_client_id`, que vinculan estrictamente el servicio al cliente WHMCS;
- `route_fingerprint`, que inmoviliza el servidor Dokploy;
- `project_id` y `environment_id`, que identifican los recursos de ese servicio;
- los IDs de base de datos, aplicación y dominio;
- el estado de cada paso.

En cada operación desde el área de cliente se comprueba propiedad, estado, servidor y entorno remoto. **No se expone el token administrador de Dokploy ni se crea usuario Dokploy para el cliente.**

## Instalación del módulo

Copiar la carpeta `eclouddokploy` a:

```text
<WHMCS_ROOT>/modules/servers/eclouddokploy/
```

Este **no** es un WHMCS Addon Module, de modo que no aparecerá en `Configuración → Módulos adicionales`. Se utiliza en **Servidores** y en **Productos/Servicios → Module Settings**.

Para el caso de paneles Dokploy independientes:

| Campo en WHMCS / Servidores | Valor |
|---|---|
| Módulo | Elearning Cloud — Dokploy (piloto) |
| Hostname | `dokploy1.ejemplo.com` (sin HTTPS, ruta ni puerto) |
| Username | **Puede dejarse vacío**; ya no se requiere Environment ID compartido |
| Password | API key de Dokploy correspondiente a ese VPS |
| Max. No. of Accounts | `10` |

**Si ya asignaste un valor de Username a servicios que empezaron aprovisionamiento, no lo cambies sin conciliación:** forma parte de la huella histórica de la ruta. Para un servidor nuevo puede quedar vacío.

Crea un grupo WHMCS con la opción `Fill active server until full then switch to next least used`, y asígnalo al producto de aplicaciones. El servidor seleccionado seguirá fijado a cada servicio.

## Protección contra recursos duplicados

Estados nuevos:

- `new`: registro nuevo;
- `creating_project`: Dokploy pudo aceptar `project.create`, por lo que no se reintenta automáticamente;
- `project_created`: ID del proyecto guardado;
- `creating_environment`: creación del entorno posiblemente realizada; no reintentar a ciegas;
- `environment_ready`: ID de entorno guardado, listo para la BD;
- después siguen `creating_db`, `db_created`, `deploying_db`, `db_deployed`, `creating_app`, etc.

El módulo **no ejecuta `project.remove` ni borra volúmenes/bases de datos automáticamente**. Si ocurre una interrupción durante la creación, se debe revisar el proyecto en Dokploy y conciliar manualmente antes de reanudar.

Una instalación anterior del piloto que utilizaba un proyecto/entorno compartido **no se convierte automáticamente**: sus recursos necesitan inspección y migración administrada. El módulo bloquea acciones de cliente que no tengan vinculación privada válida.

## Límites reales de aislamiento

La separación por proyecto reduce mezclas operativas y, combinada con WHMCS, impide que un cliente administre recursos ajenos mediante la ficha del servicio. **No equivale por sí sola a aislamiento fuerte entre contenedores**. Para hosting multi-tenant aún faltan pruebas de límites CPU/RAM, separación de redes y permisos SQL, volúmenes persistentes, controles Docker y backups/restauración independientes.

## Pruebas de aceptación

1. Con el aprovisionamiento automático deshabilitado en producción, configurar dos VPS Dokploy **de staging** en WHMCS (máximo 10 cuentas cada uno).
2. Crear servicio WHMCS A y servicio B con dos clientes de pruebas. Confirmar que Dokploy crea dos proyectos y entornos diferentes, con aplicaciones y bases de datos dentro de sus respectivos entornos.
3. Simular dos servicios del **mismo cliente**; confirmar que cada servicio tiene un proyecto separado.
4. Comprobar que el proyecto 1 nunca se utiliza para aplicaciones/base de datos del servicio 2.
5. Con un usuario distinto, intentar modificar `service_id`, `applicationId`, `environmentId` y token CSRF: las operaciones deben rechazarse.
6. Interrumpir `project.create` y `environment.create`; comprobar que no vuelve a crear recursos a ciegas y exige conciliación.
7. Completar 10 servicios de prueba en el primer VPS. El número 11 debe ser asignado por WHMCS al segundo VPS, sujeto a las reglas del grupo.
8. Validar DNS, conectividad SQL, certificados, Dockerfile, health check, límites, suspensiones y backups antes de habilitar pedidos automáticos.

Documentación oficial: https://docs.dokploy.com/docs/api/project y https://docs.dokploy.com/docs/api/environment.
