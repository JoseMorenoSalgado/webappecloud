# WebAppECloud — aprovisionamiento desde WHMCS

## Objetivo
Instalar WebAppECloud desde la contratación en WHMCS, sin que el cliente gestione credenciales de cPanel ni Dokploy. Este documento define el contrato de integración; **el aprovisionamiento todavía no está implementado**.

## Flujo compartido
1. El cliente contrata un producto WHMCS y registra dominio, modalidad y datos del administrador inicial.
2. WHMCS verifica pago/activación, dominio y modalidad y crea un trabajo idempotente por service ID + generación.
3. Un worker de aprovisionamiento con credenciales de alcance mínimo ejecuta el adaptador elegido.
4. Se prepara base de datos y usuario con privilegios solo sobre esa base; secretos fuera del repositorio y logs.
5. Se despliega una versión inmutable de WebAppECloud, se ejecutan migraciones compatibles y se inicializa el administrador mediante enlace de un solo uso y expiración corta.
6. Se verifica HTTP healthcheck, conexión DB y HTTPS antes de marcar el servicio como activo.
7. WHMCS registra resultado y URL. En caso de fallo, se preserva información diagnóstica sin revelar contraseñas y se reintenta de forma idempotente.

## Adaptador cPanel
- Requiere soporte Node.js/Passenger, ejecución controlada de scripts, y MySQL/MariaDB.
- WHMCS utiliza su módulo de aprovisionamiento cPanel existente para crear la cuenta cuando corresponda.
- Un agente de instalación limitado a esa cuenta realiza configuración y despliegue; **no exponer tokens WHM root en el CMS**.
- No asumir que todos los planes compartidos permiten builds Next.js o tareas en segundo plano; validar capacidades por servidor.
- Mantener correo/DNS en cPanel si se contrata ese servicio.

## Adaptador Dokploy
- Un backend de aprovisionamiento servidor-a-servidor consume la API de Dokploy; nunca usar tokens de Dokploy en el navegador.
- Crear proyecto/aplicación/servicio MariaDB por cliente, red privada, volúmenes persistentes, secretos, dominio y TLS.
- Usar imagen versionada, digest fijado y límites de CPU/RAM. No publicar el puerto de MariaDB.
- La integración debe validar la versión concreta de la API de Dokploy antes de escribir el adaptador.

## Operaciones y seguridad
- Estados: pending, provisioning, awaiting_dns, verifying, active, failed, suspended.
- Idempotencia, bloqueo por servicio, reintentos con backoff, auditoría, cuotas y rollback de recursos creados.
- No eliminar datos automáticamente al cancelar sin una política explícita de retención y respaldo.
- Los updates del CMS se administran por separado mediante Update Manager, independientemente del proveedor.
- Fase inicial recomendada: cPanel; Dokploy como segundo adaptador con la misma interfaz de trabajo.
