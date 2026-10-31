# Piloto híbrido WebAppECloud: Dokploy + cPanel

## Objetivo
Ejecutar Next.js y MariaDB en Dokploy, conservar buzones y SMTP en cPanel y administrar la zona DNS desde cPanel/WHM **solo si sus nameservers son autoritativos**. Si Cloudflare aloja la zona autoritativa, editar registros allí.

## Topología
- app.ejemplo.com: A/AAAA a IP de ingress de Dokploy, HTTPS válido.
- mail.ejemplo.com: A a IP de cPanel; MX del dominio a mail.ejemplo.com.
- TXT SPF, DKIM, DMARC: valores generados/verificados por cPanel.
- WebAppECloud: SMTP con TLS y credenciales por instalación, guardadas en secretos Dokploy.
- MariaDB: red privada, volumen persistente, sin exposición pública.
- DNS externo: sin apuntar MX a Dokploy.

## Prueba inicial sin impacto
1. Usar subdominio piloto dedicado; conservar DNS/MX existentes.
2. Confirmar proveedor DNS autoritativo con consultas NS.
3. Desplegar imagen versionada de Next.js y MariaDB en Dokploy con healthcheck.
4. Crear A/AAAA solo para el subdominio de prueba; verificar HTTPS y redirecciones.
5. Configurar SMTP saliente hacia cPanel, probar envío, recepción y SPF/DKIM/DMARC.
6. Verificar que el dominio esté configurado como Local Mail Exchanger en cPanel cuando corresponda.
7. Comprobar persistencia tras reinicio, límites de recursos, backup y restauración de MariaDB.
8. Comprobar actualización del CMS desde el gestor de versiones **solo cuando el instalador esté implementado y probado**.

## Precauciones
- Si el mismo dominio está agregado en cPanel como web local, evitar que su vhost o redirecciones interfieran con el tráfico dirigido a Dokploy.
- No usar las credenciales WHM root ni claves de cPanel dentro de contenedores.
- Si el dominio raíz sirve en Dokploy, cambiar únicamente los registros web; mantener MX y registros de correo.
- Para producción, automatizar desde WHMCS con operaciones idempotentes, auditoría, rotación de secretos y recuperación.
- Este documento **no** realiza despliegues ni cambios DNS.
