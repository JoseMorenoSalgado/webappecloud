# Reglas para colaboradores y agentes — WebAppECloud

## Objetivo del proyecto
SaaS de sitios web multicliente. Primer módulo: inmobiliaria; futuros módulos deben ser independientes del núcleo. Interfaces en español y responsive.

## Diseño y UI UX Pro Max
- En trabajos que alteren la interfaz usar como referencia la skill: https://github.com/nextlevelbuilder/ui-ux-pro-max-skill.
- Instalarla con el CLI oficial (`npm install -g ui-ux-pro-max-cli`; `uipro init --ai codex`) **en el entorno de desarrollo**, no en cPanel.
- Antes de cada página nueva consultar el design system generado por la skill; preservar la identidad del cliente y los diseños ya aprobados por encima de recomendaciones genéricas.
- Cuando exista `design-system/webappecloud/MASTER.md`, leerlo antes de modificar UI. Las reglas de `pages/<pagina>.md` prevalecen como ajustes locales. No regenerar ni sobrescribir master sin aprobación.
- Usar Tailwind CSS, componentes accesibles, Lucide y tokens de diseño reutilizables; evitar emojis decorativos, animaciones excesivas, degradados arbitrarios y dashboards recargados.
- Validar tamaños 375, 768, 1024 y 1440px, contraste, navegación por teclado, estados focus, etiquetas visibles, interacción táctil, imágenes optimizadas y reducción de movimiento.
- El dashboard inmobiliario debe parecerse a la captura aprobada: sidebar azul/neutral, cabecera minimalista, KPIs, gráficas, tarjetas de propiedades y últimos prospectos.
- El home debe combinar referencias funcionales de las páginas inmobiliarias indicadas por el cliente con un diseño original, sin copiar código ni activos ajenos.

## Arquitectura no negociable
- Next.js + TypeScript + Tailwind CSS.
- MariaDB o MySQL mediante Prisma (no suponer PostgreSQL).
- Despliegue orientado a WHM/cPanel con Node.js/Passenger validado antes de asegurar compatibilidad.
- Autenticación, autorización y acceso a cada recurso restringidos por tenant en el servidor; nunca depender del filtrado visual.
- Los módulos sectoriales no deben contaminar los componentes núcleo.
- No afirmar que existen funcionalidades, instalaciones, pruebas o despliegues hasta implementarlos y verificarlos.
- No exponer credenciales ni guardar secretos en Git.

## Funciones prioritarias inmobiliarias
Home editable, buscador, propiedades, imágenes, fichas PDF, formulario de contacto, WhatsApp por propiedad, seguimiento de leads y estadísticas diferenciadas (visita/clic/contacto).
