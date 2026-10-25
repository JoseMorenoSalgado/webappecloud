# Compatibilidad con Lagom y el módulo cPanel existente

## Contexto

El WHMCS de Elearning Cloud ya utiliza **Lagom** como tema del área de clientes y dispone de un módulo propio de aprovisionamiento de cuentas cPanel en WHM.

**No crear una segunda cuenta cPanel por cliente, no modificar el módulo de aprovisionamiento ni sustituir Lagom.** El addon `ecloudhosting` únicamente proporciona funciones de autoservicio de buzones y DNS para las cuentas YA creadas por el módulo existente.

## Integración con el producto existente

1. El módulo personalizado de WHMCS sigue creando, suspendiendo y administrando la cuenta cPanel Mail & DNS en WHM.
2. WHMCS registra en `tblhosting` el ID de servicio, propietario, dominio, estado y nombre de usuario de cPanel. Confirmar en la integración real **cómo almacena esos datos el módulo personalizado**; el prototipo actualmente exige correspondencia exacta entre esos campos y el mapeo privado.
3. El addon `ecloudhosting` consulta únicamente los servicios activos del cliente autenticado; no expone cuentas de terceros.
4. Para operaciones de correo/DNS, el addon utiliza un **token de UAPI específico de esa cuenta**, nunca el token root WHM y nunca credenciales en el navegador.
5. El cliente entra en `index.php?m=ecloudhosting`; los enlaces del portal podrían incorporarse al detalle del servicio del módulo personalizado después de revisar su plantilla y ruta de control.
6. El producto Dokploy es independiente: el dominio público apunta al VPS, mientras las cuentas MX y el servidor de correo permanecen en cPanel.

## Adaptación visual Lagom

La plantilla `modules/addons/ecloudhosting/templates/clienthome.tpl` ya se muestra dentro de la página WHMCS, heredando su encabezado, navegación y pie de Lagom mediante `requirelogin` y `templatefile`.

Las mejoras visuales están encapsuladas bajo `.ec-portal` para no sobrescribir elementos globales de Lagom. Se utilizan valores heredados o variables de tema con valores predeterminados para tipografía, color principal, superficies y bordes; estos nombres deben contrastarse con las variables CSS **reales** del estilo Lagom instalado. Hay tarjetas de buzones, formularios táctiles, navegación interna por anclas y tabla DNS desplazable en móvil.

**No modificar directamente** `templates/lagom2` ni los archivos de núcleo del tema. Para ajustes globales de color, usar RS Themes → Style Manager. Si el estilo Lagom activo cambia (Modern, Default, Futuristic, etc.), revisar contraste y vistas móvil/escritorio en el entorno de pruebas.

## Información pendiente para integración exacta

Se requiere el **nombre del módulo personalizado** instalado en `modules/servers/<nombre>/` de WHMCS (sin credenciales), y su contrato de creación/identificación de servicios: cómo asigna `tblhosting.username`, `domain` y `serverid`.

Sin ese dato no debemos asumir que los tokens UAPI están disponibles ni automatizar la asociación de cuentas. Mientras tanto el prototipo emplea un mapeo administrativo privado, con las operaciones de escritura apagadas por defecto.

## Validación antes de producción

- Probar con una cuenta cPanel creada por el módulo existente.
- Confirmar que cuenta, dominio y propietario coinciden en ambos módulos; probar manipulación de service ID.
- Verificar `Email.list_pops`, `Email.add_pop` y `DNS.parse_zone` con WHM/cPanel real y límites de rol.
- Confirmar que el DNS autoritativo pertenece a cPanel antes de editarlo; si Cloudflare administra los NS, no editar la zona local.
- Confirmar que Lagom no oculta los formularios, tablas ni botones con el estilo instalado.
- Mantener `ECLOUD_CPANEL_ENABLE_MAIL_WRITES=0` y `ECLOUD_CPANEL_ENABLE_DNS_WRITES=0` hasta finalizar las pruebas.

Referencias:
- https://lagom.rsstudio.net/docs/module-compatibility/
- https://lagom.rsstudio.net/docs/style-manager/
- https://developers.whmcs.com/addon-modules/client-area-output/
- https://developers.whmcs.com/provisioning-modules/module-parameters/
