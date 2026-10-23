# Arquitectura de plantillas — WebAppECloud

## Estado actual (octubre de 2026)
WebAppECloud es un CMS multicliente con dominio, contenido, menú, logotipo, tema de color, roles y datos separados por `tenantId`. El primer sitio real es NEXUS REAL ESTATE, una **plantilla inmobiliaria**. La plataforma **todavía no tiene un selector de plantillas por empresa** ni un home completo para otros sectores.

## Separación realizada
- `src/app/globals.css`: estilos base compartidos, sin reglas largas de plantillas
- `src/app/styles/legacy/*`: módulos existentes de dashboard, CMS, agentes y operaciones, conservados sin cambiar cascada
- `src/app/styles/templates/nexus-*.css`: diseño público de NEXUS, galería y tarjetas de inmuebles
- `src/components/templates/real-estate-home.tsx`: portada inmobiliaria extraída de la ruta raíz
- `src/components/site-brand.tsx`, `src/lib/site-presentation.ts`: identificación y texto de navegación/pie de página, según la empresa
- `src/lib/templates/nexus-defaults.ts`: textos predeterminados del cliente inmobiliario inicial
- `src/app/layout.tsx`: título y descripción resueltos por dominio registrado, no datos globales fijos de NEXUS

## Siguientes etapas para soportar sectores diferentes
1. Registrar un `templateKey` validado por empresa (por ejemplo `real-estate`, `education`, `services`); definirlo en instalación y crear flujo de migración/versionado para empresas existentes
2. Implementar el registro explícito de plantillas con sus componentes de portada, bloques del editor, navegación y configuraciones; no determinar industria solamente por el nombre de la empresa
3. Separar recursos de marca, configuración del editor y modelos sectoriales, conservando las relaciones multicliente y la comprobación de permisos en el servidor
4. Añadir pruebas visuales responsive, comprobación de aislamiento de archivos/consultas y migraciones en MariaDB/MySQL y PostgreSQL
5. Mantener compatibilidad de NEXUS en cada actualización de plantilla, con respaldos y reversión

**No usar el cambio de nombre de una institución como selector de plantilla.** El comportamiento actual conserva las páginas inmobiliarias para empresas existentes; la apariencia genérica de navegación y metadatos por sí sola no habilita otros sectores.

## Seguridad y regresión
- La reorganización CSS mantiene el orden original de las reglas; los estilos de mejora conservan su posición posterior
- Los archivos y modelos actuales no requieren migración de base de datos en esta fase
- Los números locales de ocho dígitos solo se completan con prefijo `505` para la instalación NEXUS, por compatibilidad; otras empresas deben indicar código internacional
- Ejecutar la suite de GitHub Actions y Docker antes del despliegue de `main` en Dockploy
