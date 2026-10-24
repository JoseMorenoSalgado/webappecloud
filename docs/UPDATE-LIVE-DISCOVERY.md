# Consulta de actualizaciones reales

El dashboard `/admin/actualizaciones` consulta el manifiesto público HTTPS de `updates.elearningcloud.io` desde el servidor (no desde el navegador).

Variable requerida:

```env
WEBAPPECLOUD_UPDATE_MANIFEST_URL=https://updates.elearningcloud.io/manifest.json
```

Formato esperado (solo metadatos, **no paquetes ejecutables**):

```json
{
  "schemaVersion": 1,
  "releases": [{
    "version": "0.2.0",
    "channel": "stable",
    "category": "feature",
    "title": "Actualización de ejemplo",
    "notes": "Cambios publicados por Elearning Cloud.",
    "publishedAt": "2026-10-23T00:00:00Z"
  }]
}
```

La versión instalada se lee del `package.json` desplegado. El servidor debe publicar realmente ese archivo JSON y permitir conexiones HTTPS salientes. Si el servicio no está configurado o no responde, el dashboard muestra un aviso y conserva la simulación sin interrumpir el CMS.

**Límites de esta fase:** el manifiesto todavía no tiene firma criptográfica y sus notas deben tratarse como datos no confiables. No habilitar descarga, instalación, migraciones ni actualización automática hasta verificar firmas, permisos, snapshot previo y recuperación. No publicar credenciales ni URLs privadas en el manifiesto.
