# Elearning Cloud Update Manager — WHMCS

Primera fase: módulo administrativo WHMCS y endpoint de consulta de versiones. **No está conectado aún a instalaciones ni distribuye paquetes.**

## Instalación (staging)
- Copiar `integrations/whmcs/modules/addons/ecloudupdates/` a `modules/addons/ecloudupdates/` de WHMCS.
- Activar el addon en Configuración > Módulos addon y asignar acceso solo a administradores autorizados.
- Se crea `mod_ecloudupdates_releases`; desactivar el módulo no elimina datos.
- El dashboard del addon muestra las últimas 100 versiones registradas; **no hay todavía formulario de publicación**.
- El endpoint `integrations/update-service/public/manifest.php` sirve un JSON estático desde una ruta privada definida por `ECLOUD_UPDATE_MANIFEST_FILE`. Configurar el servidor web para servirlo como `https://updates.elearningcloud.io/manifest.json`, con HTTPS válido y sin redirecciones. No guardar manifiestos en directorios editables por visitantes.
- En cada CMS, configurar `WEBAPPECLOUD_UPDATE_MANIFEST_URL=https://updates.elearningcloud.io/manifest.json` cuando la rama de consulta real esté integrada.

## Próximas fases
1. Publicación autenticada desde WHMCS con controles CSRF, validación estricta y auditoría.
2. Generación atómica del manifiesto desde versiones publicadas y firmado digital.
3. Registro/autorización de instalaciones mediante credenciales por instalación y descargas temporales.
4. Paquetes firmados, actualizador local y rollback probado en cPanel/Passenger y VPS externos.

**Seguridad:** no exponer la base WHMCS ni usar credenciales administrativas de WHMCS en los CMS clientes. Este endpoint no debe aceptar operaciones de escritura. Probar el addon en staging antes de copiarlo a WHMCS de producción.
