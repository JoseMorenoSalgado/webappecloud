# UI/UX Pro Max — Diseño de experiencia WHMCS × Dokploy

**Audiencia:** dos contextos: cliente que compra una app Next.js (poca experiencia técnica, frecuentemente móvil) y técnico de soporte WHMCS (trabajo repetitivo, acceso restringido, pantalla móvil/escritorio).

**Aplicación de metodología:** referencias y checklist de [UI/UX Pro Max](https://github.com/nextlevelbuilder/ui-ux-pro-max-skill) (MIT): accesibilidad, blancos táctiles, densidad, feedback, forms, contraste y mobile first. Implementación real en PHP/Smarty WHMCS/Lagom; no migrar el módulo a React/Next porque rompería el flujo nativo.

## Diseño «Operations Calm»

- Estilo: SaaS B2B, minimalista, orientación a tareas, estética editorial compacta, **sin degradados pesados**, glassmorphism, emojis decorativos ni tarjetas repetidas.
- Color: primario heredado de Lagom (`--primary`, fallback `#175eb2`); texto primario `#172c4c`; secundario `#43566f`; bordes `#d9e3ee`; superficie `#ffffff`; superficie tenue `#f6f9fd`; foco `#1468cb`. Usar variables semánticas, nunca depender solo del color para un estado. Modo oscuro ajusta superficies/texto.
- Tipografía: heredar tipografía Lagom para consistencia; cuerpo 16px móvil, etiquetas 14px; titulares 20–24px, peso 650–750; line-height 1.5.
- Espaciado: base 4px, componentes 8/12/16/24px, borde 12–16px. Touch targets >=44px y 8px de separación.
- Ancho: adaptar el 100% del contenedor WHMCS; no usar anchuras fijas; 1 columna <=720px, 2 columnas sólo para datos secundarios. Sin overflow horizontal.
- Motion: 150–200ms sólo hover/focus/transición de color, respetar `prefers-reduced-motion`.

## IA — arquitectura de información

### Cliente: «Mi aplicación»

1. **Estado real**, descripción orientada a acción y aviso explícito «El despliegue solicitado no confirma disponibilidad del sitio».
2. **Acción principal única** según estado:
   - lista para importar → formulario GitHub con pegado, configuración avanzada cerrada, confirmar;
   - configuración guardada y aún sin recursos → reintento seguro sin reintroducir información;
   - instalación iniciada → estado y soporte (sin botón que duplique);
   - despliegue solicitado → enlace a dominio configurado, histórico cuando lecturas habilitadas.
3. **Progreso real**: 3 etapas «Proyecto» → «Aplicación» → «Publicación» alimentadas desde estado persistido; jamás usar barra porcentual ficticia ni confundir `deployment_requested` con sitio en línea.
4. **Detalles técnicos**, secundarios y desplegables: dominio, base de datos, rama y GitHub.
5. **Correo corporativo**, sección separada, con manejo permitido sólo cuando existe vínculo de cPanel y flags activos.
6. **Ayuda** contextual con enlace a soporte.

### Administrador: «Ayudar al cliente»

1. Resumen compacto del propietario, servicio, dominio y estado vinculado.
2. Primer bloque: crear o comprobar proyecto mediante comando WHMCS; no requiere GitHub.
3. Segundo bloque: guardar GitHub real del cliente con preset y puerto predefinidos, variables de entorno sólo si necesita. Guardar no crea infraestructura.
4. Tercer bloque: ejecutar comando **Instalar aplicación del cliente**; confirmación a nivel de flujo antes de desplegar; mantener controles WHMCS nativos.
5. Mostrar IDs remotos cuando existan, nunca secretos.
6. Si ya hubo una creación remota ambigua o despliegue solicitado, bloquear edición del origen y guiar hacia conciliación/soporte.

## Formulario y accesibilidad

- Etiquetas visibles, ejemplos no usados como única indicación, errores al lado del campo, `aria-describedby`, `aria-invalid`, summary de errores `role=alert` y foco al primer campo erróneo.
- Indicaciones específicas («Pega la URL completa https://github.com/usuario/repositorio»), nunca sólo «error».
- Botones con `type`, estado de envío `disabled`, texto «Solicitando instalación…», evitando doble click. Esto reduce solicitudes duplicadas del navegador, pero no sustituye bloqueos del backend.
- Detección de GitHub opcional y asíncrona: indicador de carga, respuesta textualmente diferenciada, fallo recuperable con selección manual.
- Guía para variables de entorno: `NEXT_PUBLIC_*` son públicas; no mostrar secretos guardados.
- Colores visibles en claro y oscuro; foco WCAG 2.2; semántica HTML: `main/section/nav/h2/h3/details` acorde al documento padre.
- No exponer API key Dokploy, password cPanel, Docker host ni IDs cruzados.

## Estados y comunicación

- `awaiting_setup`, `new`: proyecto pendiente, completar importación si servicio está activo.
- `creating_project`, `project_created`, `creating_environment`: infraestructura en preparación, no permitir instalar otra vez.
- `environment_ready`: proyecto y production creados; falta Git.
- `creating_db`, `db_created`, `deploying_db`, `db_deployed`, `creating_app`, `app_created`, `configuring_app`, `app_configured`, `creating_domain`, `domain_created`, `requesting_deploy`: configuración/instalación en proceso.
- `deployment_requested`: petición recibida, DNS/SSL/health no verificados.

## Test de aceptación

- Móvil 320, 375, 390 y 428px: sin scroll lateral, primario 44px+, keyboard URL, controles accesibles.
- Escritorio 768, 1280 y 1440px: ancho legible, jerarquía y acciones sin duplicidad.
- Teclado: Tab en orden, focus visible, detalles expandibles y botón deshabilitado al enviar.
- Error: repo Git mal formado, rama inexistente, GitHub no disponible, campo avanzado inválido, doble click, respuesta API fallida.
- Administración: «Guardar cambios» NO debe crear proyecto, «Crear/Verificar» solo proyecto, «Instalar» Git/DB/build/deploy; no secretos en HTML ni logs.
- Servidores reales: QA con WHMCS/Lagom y Dokploy 0.30.9 antes de lanzamiento (CI/mocks no sustituyen prueba visual o API).
