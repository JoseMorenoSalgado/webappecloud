# Despliegue Git de WebAppECloud en cPanel — Nexus

Repositorio: `/home/nexusrea/repositories/webappecloud` (rama `main`).
Application Manager: `/home/nexusrea/apps/nexus/current`.
Dominio: `nexusrealestatenicaragua.com`.

## Etapa 1: copia controlada

El archivo `.cpanel.yml` habilita **Deploy HEAD Commit**. Usa `rsync` sin `--delete`, y excluye secretos, `node_modules`, compilaciones, logs y directorios persistentes. No compila ni reinicia Passenger.

Antes del primer despliegue:
- Comprobar que `/usr/bin/rsync` existe en el servidor.
- Comprobar que la ruta `~/apps/nexus/current` es correcta y que no contiene datos de otra aplicación que se puedan sobrescribir.
- Crear respaldo de la aplicación existente si ya hay contenido.
- No ejecutar el despliegue hasta fusionar este cambio en `main` y actualizar Git Version Control desde remoto.

## Etapa 2: pendiente

Configurar `DATABASE_URL` y demás secretos fuera de Git, ejecutar `npm ci`, generar clientes Prisma, compilar Next.js, aplicar migraciones con respaldo previo, definir punto de entrada Passenger y validar la aplicación. No asumir que `npm run start` funciona como archivo de entrada Passenger sin pruebas. La publicación no debe borrar datos ni secretos.

## Actualizaciones posteriores

Desde cPanel Git Version Control: Update from Remote y luego Deploy HEAD Commit, solo después de validar el pipeline completo. Las automatizaciones se habilitarán después de pruebas de recuperación.
