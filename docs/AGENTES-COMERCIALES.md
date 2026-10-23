# Agentes comerciales, propiedades y prospectos

Este módulo distingue **usuarios internos** (con cuenta y roles) de **clientes/prospectos** (registros de Lead). Crear un prospecto no le concede acceso administrativo.

## Funciones
- Cada usuario puede tener varios roles; `agente` habilita el perfil comercial.
- Un inmueble puede quedar sin agente o asociarse a un usuario activo de la **misma empresa** que tenga el rol `agente`.
- Los administradores/editores ven todos los inmuebles y prospectos de su propia empresa.
- Un agente sin roles administrativos solo consulta las propiedades asignadas a su cuenta y los prospectos vinculados a esas propiedades, incluidos estados y notas.
- Las consultas generales (sin inmueble) solo están disponibles para administradores/editores.
- El perfil comercial usa `AdminUser.name` y `AdminUser.email` y muestra inmuebles y prospectos; no crea duplicados de cuentas.
- Cambiar nombre/correo del agente desde el administrador invalida sus sesiones.

## Base de datos existente: acción previa al despliegue
**Realiza un respaldo verificable de MariaDB/MySQL antes de ejecutar cambios de esquema.**
La asignación requiere una columna nueva opcional `Property.assignedAgentId` con índice y FK a `AdminUser.id`. Los registros actuales permanecerán con valor NULL. El cambio está versionado en:
`prisma/migrations/20261022170000_property_assigned_agent/migration.sql`

Para PostgreSQL se proporciona:
`prisma/migrations-postgres/20261022170000_property_assigned_agent/migration.sql`

**No vuelvas a ejecutar estas sentencias si la columna, índice o clave foránea ya existen.** Comprueba el esquema antes de aplicar el cambio. Estas migraciones son aditivas, pero el repositorio todavía no dispone de una línea base completa de migraciones para todas las instalaciones antiguas. No ejecutes `prisma migrate deploy` indiscriminadamente en una base existente sin reconciliar su historial de migraciones.

Para una **instalación nueva**, el instalador prepara el esquema utilizando los modelos Prisma adecuados al motor. Para una **base existente**, aplica y registra el cambio de forma controlada antes de desplegar el código que utiliza `assignedAgentId`.

## Verificación
El workflow `.github/workflows/verify.yml` incorpora `scripts/check-agent-access.mjs` para probar sobre bases aisladas:
- creación de usuario con rol agente y propiedad asignada;
- visualización del perfil y dashboard comercial;
- acceso del agente a sus prospectos, pero no a contactos generales;
- rechazo de acciones sobre prospectos ajenos y de accesos administrativos;
- funcionamiento con MariaDB y PostgreSQL.

**Limitaciones actuales:** un prospecto queda asociado al agente por su propiedad. Si se reasigna el inmueble, sus prospectos asociados pasan a la cartera del nuevo responsable. No existe aún una asignación histórica independiente de leads ni teléfono público/WhatsApp personal de agente. Las cuentas de clientes con acceso propio requieren otro flujo de autorización.
