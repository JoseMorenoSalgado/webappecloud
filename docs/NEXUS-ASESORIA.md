# NEXUS — Asesoría empresarial y cumplimiento normativo

## Alcance
Línea de negocio independiente de la actividad inmobiliaria, exclusiva de `Tenant.slug = nexus` y de la plantilla `real-estate`. La ruta pública es `/asesoria-empresarial`. La presencia y publicación de esta página se gestionan como página incorporada del CMS; no se añade a otros tenants.

- Servicios: auditorías externas de cumplimiento; orientación en LA/FT/FP; capacitaciones para sujetos obligados.
- Referencias informativas: leyes No. 977 y No. 976, con reformas vigentes, incluida la Ley No. 1282 (La Gaceta No. 111, 19 de junio de 2026).
- Navegación: nueva entrada predeterminada en la web NEXUS y en el pie de página, con tratamiento compatible para menús ya guardados. Al guardar una nueva personalización, se respeta su decisión de ocultarla.
- Inicio y sección Servicios: referencia independiente a la nueva área.
- CRM: el formulario reutiliza `/api/leads`, consentimiento y control de módulos; el texto del mensaje identifica la asesoría empresarial para permitir su clasificación. No pide información confidencial relativa a posibles operaciones.
- Diseño: estilos propios y responsivos que no sustituyen la portada aprobada ni alteran otras plantillas.

## Edición de imágenes y material informativo
Ir al panel administrativo, **Personalizar → Páginas → Asesoría empresarial**, para cambiar publicación y añadir bloques de contenido. Los bloques adicionales permiten textos, encabezados e imágenes almacenadas por el CMS para esa página. El contenido editorial base es una plantilla de presentación, mientras que los bloques adicionales se almacenan en la base de datos y son editables sin despliegue.

**Importante:** no se han incorporado fotografías reales ni material de capacitación del cliente; deben aportarse o subirse mediante el editor para evitar atribuirle credenciales, publicaciones o equipos no confirmados.

## Sobre la agenda
La página dispone de un CTA **Solicitar asesoría** y formulario de contacto. Esto crea una consulta en el CRM; **no confirma citas ni reserva horarios**. La agenda automática (disponibilidad, bloqueo de horario, confirmaciones, recordatorios y reasignación) requiere confirmar primero el flujo de atención del cliente.

## Verificación antes de producción
1. Correr `npm install`, `npm run build` y las comprobaciones CI disponibles.
2. En un tenant de prueba con slug `nexus`, comprobar /, /servicios, /asesoria-empresarial, formulario, menú desktop/móvil y personalizador.
3. Comprobar que otro tenant real-estate **no** ve esta página.
4. Verificar carga de imágenes, publicación/despublicación, menús ya personalizados y registro del lead en CRM.
5. Antes de integrar a `main` y desplegar en Dockploy, validar copia restaurable de SQL y volumen persistente, como exige la política del repositorio.
