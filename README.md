# WebAppECloud

Plataforma web multiempresa de Elearning Cloud.

## Objetivo

Construir una plataforma SaaS multi-tenant para crear y administrar sitios web de distintos sectores. **El primer cliente será una inmobiliaria**: sitio público, catálogo de propiedades, fichas PDF, formularios de leads, WhatsApp contextual y dashboard personalizado. El proyecto deberá permitir incorporar otros sectores mediante módulos sin duplicar aplicaciones.

> **Estado:** repositorio en preparación. Este documento establece la arquitectura y el flujo visual; todavía no hay una aplicación Next.js implementada ni desplegada.

## Arquitectura aprobada

- Next.js + TypeScript, React y Tailwind CSS para sitio público y panel administrativo.
- UI personalizada con shadcn/ui, iconos Lucide y gráficas Recharts.
- MySQL o MariaDB con Prisma ORM.
- Aplicación Node.js apta para cPanel/WHM **cuando Node.js/Passenger estén disponibles y el proceso se haya validado en el entorno de destino**.
- Modelo multi-tenant con segregación por `tenant_id`, permisos aplicados en el servidor, mapeo de dominios y configuración visual por cliente.
- CMS modular propio: páginas, bloques, medios, menús, SEO, temas y módulos sectoriales.

## Primer cliente: inmobiliaria

El sitio público debe tener hero con buscador, catálogo filtrable, propiedades destacadas, ficha detallada con fotos y PDF descargable, formularios y botón de WhatsApp contextual (referencia, título y ubicación de la propiedad). El dashboard debe seguir las referencias visuales previamente aprobadas: barra lateral, cuatro KPIs, gráfica de visitas/contactos, distribución por tipo de propiedad, propiedades recientes y últimos leads.

La analítica diferenciará visitas, clics en WhatsApp y formularios completados; un clic **no** equivale a una conversación o lead confirmado.

Consulta [docs/UX-BRIEF.md](docs/UX-BRIEF.md) para el contrato de diseño inicial.

## UI UX Pro Max (herramienta de desarrollo, no runtime)

Referencia: https://github.com/nextlevelbuilder/ui-ux-pro-max-skill (MIT).

En el equipo de desarrollo, con Node.js, npm y Python 3 instalados:

```bash
git clone https://github.com/JoseMorenoSalgado/webappecloud.git
cd webappecloud
npm install -g ui-ux-pro-max-cli
uipro init --ai codex
```

El instalador crea los archivos locales de la skill para Codex en `.agents/skills/ui-ux-pro-max/`. Antes de diseñar nuevas páginas, generar una propuesta de design system con el buscador de la skill y revisarla contra las capturas del cliente.

Ejemplo después de instalar:

```bash
python3 .agents/skills/ui-ux-pro-max/scripts/search.py "real estate agency premium dashboard" --design-system -p "WebAppECloud" -f markdown
```

Para persistir un diseño aprobado usar `--persist --output-dir .` y leer los archivos existentes antes de regenerarlos. No reemplazar archivos existentes sin aprobación.

**La skill se usa en desarrollo**, no se debe instalar ni ejecutar en el hosting de producción solo para servir el sitio.

## Próximas tareas

1. Generar y aprobar el sistema visual del home y dashboard.
2. Inicializar Next.js y componentes compartidos.
3. Desarrollar home y ficha de propiedad con contenido de muestra.
4. Implementar autenticación, aislamiento multi-tenant y MariaDB.
5. Conectar el CMS, leads y analítica; preparar despliegue de cPanel.
