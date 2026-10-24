# Respaldos reales: primera etapa (operación manual)

El archivo `scripts/create-snapshot.mjs` realiza una exportación consistente de MariaDB/MySQL (tablas InnoDB mediante `--single-transaction`) y copia los directorios de archivos configurados. Genera `manifest.json` con SHA-256 de cada archivo. Los snapshots quedan en una ruta privada fuera de la aplicación. No instala actualizaciones ni ejecuta restauraciones.

## Preparación en cPanel

1. Crear un directorio privado independiente del directorio de la aplicación y de `public_html`; dar acceso solo al usuario de la aplicación. Verificar espacio libre.
2. Confirmar que `mariadb-dump` o `mysqldump` esté disponible y que el usuario de la base tenga permisos suficientes para exportar los objetos utilizados.
3. Configurar `DATABASE_URL`, `WEBAPPECLOUD_SNAPSHOT_DIR` y `WEBAPPECLOUD_SNAPSHOT_PATHS` (lista separada por comas de directorios **relativos a la raíz del proyecto**; por defecto `public/uploads`). La lista debe cubrir todas las rutas reales donde se guarden archivos persistentes; comprobarlo antes de usar el respaldo.
4. Ejecutar manualmente `node scripts/create-snapshot.mjs` desde la raíz del proyecto con Node.js. Si se usa `mysqldump`, configurar `WEBAPPECLOUD_DUMP_BINARY=mysqldump`.

No ejecutar este script desde una ruta pública ni exponerlo como endpoint HTTP. No colocar el directorio de snapshots dentro de la aplicación ni en una carpeta servida por el servidor web.

## Limitaciones y seguridad

- **No es aún un mecanismo de restauración**. No se debe ofrecer el botón de recuperación real a clientes hasta probar una restauración completa en un entorno aislado.
- El dump de MariaDB no cubre transacciones no InnoDB con la misma garantía de consistencia; la copia de archivos no es atómica respecto a las escrituras concurrentes. Para una copia coherente de todo el CMS, establecer una ventana de mantenimiento o congelar escrituras.
- El respaldo incluye datos sensibles y **no está cifrado**. Exige directorio privado, permisos restrictivos y, para almacenamiento externo, cifrado antes de transferir.
- Un archivo `manifest.json` con hashes permite detectar corrupción accidental, pero **no autentica** el origen: hace falta firma o autenticación del manifiesto antes de restaurar.
- El bloqueo `.snapshot.lock` impide dos procesos simultáneos, pero una ejecución abortada puede dejar un bloqueo que requiere inspección manual.
- La lista de directorios no incluye por defecto archivos de configuración, secretos ni el código desplegado. La recuperación completa requiere un artefacto de release verificable y la configuración del servidor por separado.
- Probar la exportación, los hashes, el almacenamiento y la recuperación en staging antes de producción.

## Verificar un snapshot (sin restaurar)

Ejecutar `node scripts/verify-snapshot.mjs snap_ID` con `WEBAPPECLOUD_SNAPSHOT_DIR` configurado. El verificador lee cada archivo de forma secuencial, confirma tamaño y SHA-256, y falla si detecta corrupción, enlaces simbólicos o rutas inválidas. **No modifica la base de datos ni el sitio**. La verificación de hashes no sustituye una restauración de prueba ni una firma criptográfica del manifiesto.
