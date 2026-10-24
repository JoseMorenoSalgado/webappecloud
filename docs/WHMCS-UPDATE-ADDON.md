# Elearning Cloud Update Manager — WHMCS

Primera fase: módulo administrativo WHMCS y endpoint de consulta de versiones. **No está conectado aún a instalaciones ni distribuye paquetes.**

## Instalación (staging)
- Copiar `integrations/whmcs/modules/addons/ecloudupdates/` a `modules/addons/ecloudupdates/` de WHMCS.
- Activar el addon en Configuración > Módulos addon y asignar acceso solo a administradores autorizados.
- Se crea `mod_ecloudupdates_releases`; desactivar el módulo no elimina datos.
- El dashboard permite crear borradores y publicar versiones. La publicación escribe el manifiesto JSON de manera atómica en la ruta privada configurada.
- El endpoint `integrations/update-service/public/manifest.php` sirve un JSON estático desde una ruta privada definida por `ECLOUD_UPDATE_MANIFEST_FILE`. Configurar el servidor web para servirlo como `https://updates.elearningcloud.io/manifest.json`, con HTTPS válido y sin redirecciones. No guardar manifiestos en directorios editables por visitantes.
- En cada CMS, configurar `WEBAPPECLOUD_UPDATE_MANIFEST_URL=https://updates.elearningcloud.io/manifest.json` cuando la rama de consulta real esté integrada.

## Próximas fases
1. Publicación autenticada desde WHMCS con controles CSRF, validación estricta y auditoría.
2. Generación atómica del manifiesto desde versiones publicadas y firmado digital.
3. Registro/autorización de instalaciones mediante credenciales por instalación y descargas temporales.
4. Paquetes firmados, actualizador local y rollback probado en cPanel/Passenger y VPS externos.

**Seguridad:** no exponer la base WHMCS ni usar credenciales administrativas de WHMCS en los CMS clientes. Este endpoint no debe aceptar operaciones de escritura. Probar el addon en staging antes de copiarlo a WHMCS de producción.

## Publicación administrativa

El addon usa el token CSRF del panel administrativo WHMCS para guardar borradores y publicarlos. Configurar `ECLOUD_UPDATE_MANIFEST_FILE` como ruta absoluta a un archivo privado en un directorio existente y escribible por el proceso PHP de WHMCS. El servicio HTTPS de manifiestos debe leer ese mismo archivo. La publicación solo transmite metadatos; **no distribuye ZIP ni instala código**. Si falla la escritura del manifiesto, la base puede quedar con una versión marcada como publicada: resolver la configuración y volver a publicar antes de anunciar disponibilidad.

Antes de producción: probar compatibilidad de las funciones de token con la versión exacta de WHMCS, verificar permisos del directorio y comprobar que el servidor público lea el JSON actualizado. No permitir publicación desde usuarios no autorizados.
