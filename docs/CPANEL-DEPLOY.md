# cPanel deployment (Node.js 22)

This workflow builds Next.js 15 standalone on GitHub Actions, so compilation does not consume production server CPU. It **does not deploy** until CPANEL_DEPLOY_ENABLED is set to `true`.

## Before enabling deployment

1. Confirm Node.js 22 and persistent app support (Passenger/Application Manager or a process manager supported by WHM).
2. Configure `output: 'standalone'` in `next.config.mjs`; confirm `.next/standalone/server.js` is generated. Passenger may require a separate bootstrap file or a stable startup path.
3. Create the database in cPanel and keep production credentials in `APP_ROOT/shared/.env` with restrictive permissions; **never commit credentials**.
4. Preserve the installer state in `APP_ROOT/shared/.runtime` and ensure all uploaded images/files are stored on persistent storage, not within releases. Check current upload routes first.
5. Review Prisma migration strategy: do not run `prisma db push` against production. Take a verified backup and run vetted migrations separately.
6. Create a dedicated deploy SSH key and verify server fingerprints independently. Store in GitHub Actions secrets: `CPANEL_SSH_HOST`, `CPANEL_SSH_USER`, `CPANEL_SSH_KEY`, `CPANEL_SSH_KNOWN_HOSTS`. Variables: `CPANEL_APP_ROOT` (absolute directory within account home), `CPANEL_DEPLOY_ENABLED`.
7. Install `scripts/cpanel-deploy.sh` into `APP_ROOT/scripts/` on the server initially. Deployment archives don't carry the script automatically to that directory.
8. Ensure `APP_ROOT/current` is used as app root, and configure a restart mechanism for the hosting mode. The script can optionally use `CPANEL_RESTART_COMMAND` defined on the server.
9. Test in staging, verify `/api/live`, login, file uploads, PDF generation and rollback before enabling production.

## Important
The app currently has no validated cPanel deployment. The workflow is deliberately disabled from SSH deployment by default. GitHub will only compile and retain the artifact until configuration is complete.
