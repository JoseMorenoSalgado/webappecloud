# NEXUS · Primera fase del rediseño público (Home)

**Objetivo:** portada comercial más clara, fotográfica y profesional, sin
romper el CMS multicliente ni las funciones inmobiliarias existentes.

## Dirección visual aplicada

- **Taste / Persuade:** fotografía arquitectónica protagonista, titular editorial
  sobre fondo cálido, jerarquía legible; sin inventar cifras o testimonios.
- **Impeccable:** un CTA principal al catálogo, buscador con etiquetas visibles,
  estados de foco, acciones secundarias distinguibles y secciones más fáciles
  de escanear.
- **Emil / mobile-native:** controles de al menos 44 px, campos de 16 px en
  móvil, adaptación explícita a 960/700/610/375 px, movimientos breves y
  opción `prefers-reduced-motion`.
- **Marca y tenant:** el hero, el color, los títulos y las propiedades siguen
  saliendo de los ajustes y la empresa correspondiente; se mantiene la
  fotografía personalizada si existe (`/api/site-media/hero`).
- **Sin alterar la administración:** estilos confinados a
  `.public-site .nexus-home-premium` y cargados mediante
  `src/app/styles/templates/nexus-home-premium.css`.

## Cambios visibles

1. Hero dividido en contenido y fotografía en escritorio; foto arriba
   y mensaje abajo en pantallas estrechas.
2. Buscador con labels, operación, tipo y ubicación, vinculado al catálogo.
3. Propiedades destacadas con imágenes `cover`, sin reescalar la ficha
   individual; la selección sigue siendo real/administrable.
4. Servicios en columnas editoriales simples (sin cajas interiores).
5. Secciones de historia y contacto con mejor jerarquía y menos ruido visual.

## Restricciones y fases siguientes

- El contenido original de NEXUS, los bloques CMS, la navegación,
  los módulos por empresa, el formulario de contacto y los permisos de
  publicación permanecen sin cambios funcionales.
- No usar datos comerciales ficticios ni imágenes generadas como si fueran
  inmuebles reales. La fotografía de referencia existente es solo un fondo
  visual provisional; idealmente se reemplaza por una fotografía aprobada
  mediante el editor del sitio.
- Esta PR afecta **solamente a la portada inmobiliaria**. La siguiente
  fase cubrirá catálogo, ficha de propiedad y páginas institucionales,
  después de revisar esta composición.
- La validación automática `scripts/check-nexus-premium-home.mjs` cubre
  HTML publicado, enlaces, aislamiento y reglas CSS; **no equivale a
  una auditoría visual manual**.

## Lista de aprobación visual pendiente

Comprobar en navegador real a anchos 375 / 768 / 1024 / 1440 px:

- Que la foto no tape los textos y que el titular no se recorte.
- Que el buscador no desborde y funcione con teclado, touch y zoom.
- Que las imágenes del catálogo tengan calidad suficiente y se vean completas
  cuando el cliente abra la ficha individual.
- Que no haya desplazamiento horizontal ni texto ilegible.
- Que el menú móvil, la ruta al catálogo, los botones y el formulario funcionen.
- Que un tenant institucional no herede ninguna clase del Home NEXUS.

No fusionar a main ni desplegar antes de revisar estas capturas y las CI.
