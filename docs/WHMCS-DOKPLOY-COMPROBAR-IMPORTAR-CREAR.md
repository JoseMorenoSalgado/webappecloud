# WHMCS → Dokploy: comprobar, importar o crear

**Versión de prueba · PR #38 · Actualización 25/10/2026.**

## Flujo definitivo

Al elegir el comando WHMCS **«Comprobar / crear proyecto»** para un servicio App Ecloud pagado:

1. WHMCS valida cliente propietario, producto, dominio, servidor Dokploy asignado y huella de ruta.
2. Con API key del VPS asignado, consulta `GET /api/project.search` con nombre `ec-client-{CLIENTE}-service-{SERVICIO}`; filtra por nombre **exacto**, descripción (si está) e ID, y rechaza respuestas incompletas o duplicadas.
3. **Ya existe y todavía no está vinculado:** WHMCS no crea nada. Muestra: **«Proyecto ya existe en Dokploy. ¿Deseas importarlo?»**.
   - El administrador elige **«Sí, importar proyecto existente»** desde **Comandos del módulo**. La confirmación está asociada a su sesión de WHMCS, servicio, propietario, VPS, projectId y caduca en 15 minutos.
   - Al confirmar, bajo bloqueo por servicio, WHMCS consulta de nuevo el mismo proyecto y el entorno `production`. Solo después guarda `project_id` y `environment_id` en ese servicio.
   - La opción **«No, cancelar importación»** descarta la confirmación y no cambia nada en Dokploy o WHMCS.
4. **No existe:** WHMCS crea un proyecto nuevo de forma automática utilizando `POST /api/project.create`. Dokploy v0.30.9 devuelve:
```json
{
  "project": {"projectId": "identificador-del-proyecto"},
  "environment": {
    "environmentId": "identificador-del-entorno",
    "projectId": "identificador-del-proyecto",
    "name": "production"
  }
}
```
   WHMCS extrae los dos IDs, verifica que `production` realmente pertenece al proyecto mediante `environment.byProjectId`, y los guarda. **No intenta crear otro `production`**, pues v0.30.9 ya lo genera automáticamente y prohíbe crearlo por separado.
5. **Ya está vinculado correctamente:** WHMCS comprueba y responde éxito, sin importar ni crear recursos nuevos.

## Caso real cliente 225 / servicio 293 / pedido 324

El proyecto esperado es `ec-client-225-service-293`, siempre en el servidor **App1**. Los identificadores **225 y 293 no son el ID del pedido 324**.

- En la ficha del servicio, ejecutar **«Comprobar / crear proyecto»**.
- Si Dokploy encuentra el nombre y el módulo todavía no guarda su ID actual, la sección **«Proyecto Dokploy · Centro de ayuda»** mostrará «¿Deseas importarlo?».
- Elegir **«Sí, importar proyecto existente»** solo si el administrador confirma que ese proyecto corresponde al cliente.
- Si fue eliminado y Dokploy confirma su ausencia, para servicios **vacíos** se conserva la recuperación segura de proyectos eliminados: primero comprobar `project.one` con ID antiguo y confirmar HTTP 404, luego crear el proyecto nuevo; no borrar filas manualmente.
- Si ya tiene aplicaciones, dominios o bases de datos vinculados, **no** se sobrescribe la conexión anterior automáticamente. Primero restaurar o conciliar.

## Reglas de seguridad y límites

- La importación **vincula el proyecto y su entorno**, no importa automáticamente aplicaciones, bases de datos o secretos de recursos previamente creados.
- Nunca importa otro proyecto con nombre distinto o cliente ajeno, ni sustituye silenciosamente un proyecto antiguo que siga existiendo con otro nombre.
- En HTTP 401/403/500, errores TLS, búsquedas incompletas o entornos ambiguos, detiene la operación y no genera duplicados.
- Solo el administrador de WHMCS puede aprobar una importación. La primera factura debe estar `Paid` y el servicio `Active`. «Comprobar / crear» funciona sin GitHub; la instalación de GitHub se realiza después mediante **«Instalar aplicación del cliente»**.
- Un estado `environment_ready` representa proyecto/entorno vinculados; **no** significa que la aplicación esté desplegada ni online.

## Operaciones antes del lanzamiento

1. Respaldo BD WHMCS y carpeta del módulo; instalar ZIP staging en `modules/servers/eclouddokploy`.
2. Probar con el servicio 293 y verificar la pregunta Sí/No en la pestaña administrativa.
3. Confirmar que los IDs corresponden al proyecto visible en Dokploy, sin proyectos duplicados.
4. Probar GitHub y despliegue real de Next.js con dominio, SSL, MariaDB y permisos; verificar sesión WHMCS y móvil Lagom.
5. Mantener PR #38 como borrador hasta validar el servidor real. Las pruebas simuladas HTTPS/PHP/Smarty no sustituyen estas comprobaciones.

