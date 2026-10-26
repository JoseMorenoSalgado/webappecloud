# Elearning Cloud — WHMCS + Dokploy

Módulo de **aprovisionamiento de servicios WHMCS** para administrar proyectos privados de Dokploy. Distribución de código fuente para mantener este componente en **otro repositorio Git**, separado del CMS WebAppECloud.

> **PILOTO / STAGING**. No habilitar en producción sin pruebas sobre WHMCS, Dokploy y cPanel reales. La verificación automatizada no garantiza que los despliegues reales funcionen.

## Contenido del repositorio

- `modules/servers/eclouddokploy/`: código fuente PHP, plantilla de cliente Lagom, bibliotecas y pruebas.
- `docs/`: documentación de instalación, UX, seguridad, correo y flujos de aprovisionamiento.
- `.github/workflows/php-checks.yml`: comprobaciones de sintaxis y pruebas offline.
- `.gitignore`: exclusión de secretos, archivos locales y artefactos.

Es un módulo de **servidores/provisioning**. **No aparece en la sección "Módulos adicionales"** de WHMCS.

## Instalar en WHMCS de prueba

1. Haz copia de seguridad de la base de datos WHMCS y de los archivos del módulo anterior.
2. Copia el directorio `modules/servers/eclouddokploy/` a `<WHMCS_ROOT>/modules/servers/eclouddokploy/`. **No copies la carpeta `tests/` al sitio público.**
3. En WHMCS, añade un servidor de tipo `eclouddokploy` con Hostname HTTPS y su API Key (guardada en el campo de contraseña del servidor).
4. Crea **un producto de prueba nuevo**, selecciona el módulo y usa `Instalación automática = guiado`.
5. Configura el aprovisionamiento al recibir el **primer pago**. Prueba un servicio de staging, no clientes en producción.
6. En la ficha administrativa utiliza «Comprobar / crear proyecto». Si existe, importa únicamente tras confirmar expresamente. Un estado verde de proyecto **no garantiza** aplicación online.
7. Comprueba acceso Lagom, permisos, HTTPS, DNS, base de datos, correo y suspensión/restauración antes de dar acceso general.

**Requisitos:** WHMCS compatible, PHP 8.2+, cURL, Sodium y conexión HTTPS segura a la API Dokploy. El cifrado de secretos depende del material criptográfico de WHMCS: resguárdalo junto a la base de datos.

## Funciones en desarrollo

Aprovisionamiento condicionado al pago, aislamiento de proyectos por cliente y servicio, recuperación segura de proyectos vacíos, instalador guiado GitHub público (Next.js, Node.js, Dockerfile), opciones MariaDB/MySQL/PostgreSQL, gestión de correo cPanel y panel del cliente Lagom. Algunos comandos permanecen sujetos a validación operativa; no dar por habilitadas características experimentales.

## Pruebas locales

```bash
find modules/servers/eclouddokploy -name '*.php' -print0 | xargs -0 -n1 php -l
php modules/servers/eclouddokploy/tests/validate.php
php modules/servers/eclouddokploy/tests/automatic_seed_mock.php
php modules/servers/eclouddokploy/tests/order_payment_mock.php
python3 modules/servers/eclouddokploy/tests/ux_template_contract.py
```

Los tests de integración HTTPS están preparados para un servidor simulado; sigue siendo obligatorio probar Dokploy real antes de producción.

## Publicar este paquete en otro repositorio GitHub

Crea en GitHub un **repositorio nuevo y preferentemente privado**, por ejemplo `whmcs-dokploy`, **sin inicializarlo con README**. Extrae el paquete en una carpeta local y ejecuta allí:

```bash
git init -b main
git add .
git commit -m "chore: import standalone WHMCS Dokploy module"
git remote add origin https://github.com/TU-USUARIO/whmcs-dokploy.git
git push -u origin main
```

`main` en este ejemplo será **la rama del nuevo repositorio**, no la de WebAppECloud. Sustituye `TU-USUARIO` por tu usuario u organización. No subas tokens de Dokploy, credenciales WHMCS, bases de datos ni secretos al repositorio.

## Procedencia

Extraído desde la rama `feature/whmcs-dokploy-git-mariadb` de [JoseMorenoSalgado/webappecloud](https://github.com/JoseMorenoSalgado/webappecloud), [PR #38](https://github.com/JoseMorenoSalgado/webappecloud/pull/38). Esta copia **no mantiene sincronización automática** con su repositorio de origen.

## Licencia

No se concede ni presume una licencia nueva mediante este empaquetado. Conserva los avisos y permisos que correspondan al código original.
