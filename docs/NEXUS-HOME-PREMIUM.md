# NEXUS · Rediseño premium de la web pública

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
- Además de la portada, esta misma PR incluye una capa de diseño para
  catálogo, ficha de propiedad, Nosotros, Servicios y Contacto. Los sectores
  Educación, Servicios institucionales y Comercio conservan sus estilos.
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

## Segunda fase implementada: resto de páginas públicas

Se incorporó `src/app/styles/templates/nexus-site-premium.css`, cargado
después del estilo del Home y acotado al sitio inmobiliario mediante la clase
`nexus-site-premium` colocada exclusivamente en `PublicFrame` cuando el
sector de la empresa es `real-estate`. Se usa `nexus-public-page` para
acotar los espacios y tarjetas internas y no sobrescribir los ajustes del Home.

- **Propiedades:** portada clara, filtros compactos y sin cajas superpuestas,
  selectores móviles legibles, resultados y tarjetas con jerarquía visual.
- **Ficha de inmueble:** mejor lectura de título, precio, ubicación,
  características, PDF y contacto; galería original en `object-fit:contain`
  y sidebar solo sticky en escritorio, nunca sobrepuesto en móvil.
- **Nosotros:** misión y visión como secciones editoriales separadas por
  líneas; fotografía real del catálogo si existe y texto del CMS intacto.
- **Servicios:** servicios como filas editoriales con iconografía discreta,
  proceso simplificado y enlaces existentes a venta, alquiler y contacto.
- **Contacto:** métodos como filas legibles, correo y WhatsApp dinámicos,
  formulario independiente y políticas de permiso del módulo preservadas.
- **Navegación y footer:** mejor contraste y consistencia en todo el sector,
  sin reemplazar la identidad de otros clientes.

No se han sustituido ni inventado fotos, estadísticas, testimonios ni datos
de propiedades. Los ajustes CMS y las publicaciones por empresa se mantienen.

### Verificaciones técnicas adicionales

`scripts/check-nexus-premium-site.mjs` solicita las seis rutas públicas
en el servidor de CI y verifica HTML, componentes, aislamiento sectorial,
filtros de catálogo, PDF, WhatsApp, publicación, formulario de contacto
y CSS responsive. `check-style-modules.mjs` valida el nuevo módulo CSS.

**Antes de fusionar:** inspeccionar capturas auténticas en 375, 768,
1024 y 1440 px, comprobar navegación con teclado y touch, y validar
visualmente fotografías reales del cliente. Las pruebas de código no
sustituyen estas comprobaciones.
