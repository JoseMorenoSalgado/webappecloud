# WebAppECloud — Plataforma multiempresa

**Primera implementación:** NEXUS REAL ESTATE · «Pensando en tu futuro».

Base Next.js 15 + TypeScript + Tailwind CSS + Prisma + **MariaDB/MySQL**, preparada para cPanel cuando la cuenta admita una aplicación Node.js persistente. **Rama de desarrollo**, no apta aún para producción sin pruebas de instalación, seguridad, dominio y carga.

## Funcionalidades implementadas

- Web pública NEXUS: home, buscador, catálogo por operación/tipo/ubicación y ficha individual.
- Ficha técnica PDF generada por servidor.
- Formulario de contactos con consentimiento; registro separado por empresa (tenant).
- WhatsApp contextual por propiedad y contador separado de los leads confirmados.
- Panel administrativo **protegido por usuario y sesión segura**: KPIs, gráficas, inmuebles recientes, leads.
- Registro de inmuebles con publicación opcional (fotografía por URL HTTPS).
- Esquema multiempresa en MariaDB: tenants, domains, propiedades, leads, páginas, eventos, cuentas y sesiones.
- Diseño móvil del home y dashboard, basado en la referencia aprobada y la paleta roja/gris de Nexus.

### Aún pendiente

- Logo vectorial/fotografías definitivas de NEXUS en el repositorio.
- Editor de páginas, carga de medios y administración de plantillas/módulos para otros sectores.
- Gestión de categorías, agentes, CRM, edición/eliminación de propiedades, informes avanzados.
- Rate limiting del formulario público, anti-bots, auditoría de seguridad y protección de datos (login incluye control básico de intentos).
- Migraciones iniciales versionadas, pruebas CI y proceso de despliegue concreto del cPanel.

## Desarrollo local

Necesitas Node.js 20+ y MariaDB/MySQL.

```bash
npm install
cp .env.example .env
# Configura DATABASE_URL, SEED_DOMAIN=localhost, ADMIN_EMAIL y ADMIN_PASSWORD fuerte
npx prisma generate
npx prisma db push        # Solo primera instalación de DESARROLLO
npm run db:seed
npm run dev
```

Abre http://localhost:3000 (página pública) o http://localhost:3000/admin/acceso (panel). Para ver la web pública **sin** base de datos en desarrollo, omite `DATABASE_URL`: se muestra un catálogo de ejemplo, pero el formulario y el panel permanecen deshabilitados. En producción **no** se mostrará la demo sin base de datos.

La semilla crea la inmobiliaria NEXUS, el dominio configurado, tres propiedades ficticias y —solo si están definidos `ADMIN_EMAIL` y `ADMIN_PASSWORD`— un administrador. La semilla **no cambia la contraseña de una cuenta existente**.

### Seguridad multicliente

Nunca aceptamos el identificador del tenant enviado por el visitante para seleccionar datos. Se obtiene de la asignación única del dominio. Todas las consultas del panel se limitan al tenant de la cuenta autenticada, con cookie HttpOnly de sesión y token aleatorio cuyo hash se almacena en MariaDB.

### cPanel / WHM

Necesita Node.js / Passenger, Application Manager o Web Apps, más una versión de Node compatible, configurada por el administrador de WHM. Crear base MariaDB con credenciales en `.env`, fuera de `public_html`, y configurar dominio y SSL. Aún **no** se ha validado un despliegue Passenger de esta aplicación; `output: "standalone"` no significa que se pueda ejecutar sin ajustar el punto de entrada. No uses `prisma db push` como mecanismo de migración en producción; versionaremos migraciones y respaldos antes del primer despliegue.

## Estado de validación

No se ha ejecutado `npm install` ni compilación en este entorno sin conexión a npm. La rama se deja para revisión y pruebas sobre un ambiente de desarrollo, **sin fusionar automáticamente con main**.

Referencias de diseño: [docs/UX-BRIEF.md](docs/UX-BRIEF.md).
