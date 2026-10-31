# Docker storage hygiene for Dokploy

WebAppECloud deployments should use prebuilt, immutable image digests rather than running an independent build for every customer. On a single Docker host, identical image layers are shared.

## Measure before cleanup

```bash
df -h
docker system df -v
docker builder du
docker ps -a --size
docker volume ls
```

## Safe maintenance principles

- **Never run** `docker system prune --volumes` or `docker volume prune` on a multi-tenant server without a verified inventory and backups: persistent MariaDB data can be destroyed.
- Keep at least the currently deployed and immediately previous image digest for each application. A cleanup job must consult deployed and rollback references, not just image age.
- Docker daemon JSON-file log rotation should be configured on new containers (for example `max-size: 10m`, `max-file: 3`); changing daemon defaults requires container recreation to take effect.
- Build once in CI and push to a registry, then deploy pinned digests through Dokploy. Avoid rebuilding per tenant.
- Next.js standalone output and a multistage Dockerfile can reduce final runtime image size, but validate Prisma client generation, native sharp dependencies, and runtime asset paths.
- Track disk usage and alert before exhaustion; do not assume image pruning solves growth from database volumes or uploads.
- Prune **only dangling/unreferenced** images after checking active deployments and rollback requirements; treat builder cache separately.

## Pilot acceptance
Record baseline `docker system df -v`, deploy a new image twice, compare disk use, verify rollback to previous digest, and verify MariaDB persists across container recreation.
