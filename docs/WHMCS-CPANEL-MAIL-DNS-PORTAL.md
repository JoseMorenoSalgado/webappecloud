# Elearning Cloud — WHMCS portal for each customer's cPanel email and DNS

**Status: staging foundation, not installed or approved for production.**

This addon complements the native WHMCS cPanel provisioning module. Clients receive their own cPanel account with the **ecloud_mail_dns** WHM package, while the actual Next.js application and SQL database stay on Dokploy. The package and feature list are created in WHM first. See `docs/WHM-EMAIL-DNS-ONLY-PACKAGE.md`.

## Current portal functionality

- Service lookup strictly from `tblhosting.userid = current WHMCS session uid` and `domainstatus = Active`.
- Double-check service domain and username against a private admin-maintained account map.
- Read a client's email mailboxes and zone through the cPanel UAPI with the cPanel user's token, not WHM root credentials.
- Client forms for mailbox creation, password changes and quota changes; DNS listing and constrained A/CNAME/TXT additions.
- DNS MX, NS, SPF, DKIM, DMARC and known mail hosts are protected from portal writes.
- Client POST requests use a per-session CSRF token. External API responses and secrets are never rendered.
- All mutation endpoints **disabled by default**; turn on only after a successful staging UAPI test.
- Read-only displays rely on the shape of UAPI responses; verify against the actual cPanel 134 instance before enabling.

## Installation in staging

Copy `integrations/whmcs/modules/addons/ecloudhosting/` to `WHMCS_ROOT/modules/addons/ecloudhosting/` and activate via WHMCS addon management. Assign addon to the intended admin role, and configure a private configuration file **outside webroot**.

Example configuration file (must be readable only by the WHMCS PHP account, permission 0600):

```json
{
  "services": {
    "123": {
      "host": "cp1.example.net",
      "username": "exampleclient",
      "domain": "example.com",
      "token": "REPLACE_WITH_ACCOUNT_UAPI_TOKEN",
      "nameservers": ["ns1.example.net", "ns2.example.net"],
      "dns_edit": false
    }
  }
}
```

Environment:

```ini
ECLOUD_CPANEL_ACCOUNTS_FILE=/private/path/cpanel-service-map.json
ECLOUD_CPANEL_ALLOWED_HOSTS=cp1.example.net,cp2.example.net
ECLOUD_CPANEL_ENABLE_MAIL_WRITES=0
ECLOUD_CPANEL_ENABLE_DNS_WRITES=0
```

Do not commit any tokens or service map files. The real cPanel hostname must resolve to a trusted certificate on port 2083. API tokens are account-scoped; do **not** assume each cPanel token has function-level restrictions. Protect them as passwords, rotate them, and isolate credentials per service. For future scaling, replace the manually managed JSON with an encrypted secret store tied to WHMCS provisioning lifecycle.

## DNS ownership

The editing capability is separately allowed only when:

1. `ECLOUD_CPANEL_ENABLE_DNS_WRITES=1`,
2. the service map has `dns_edit=true`,
3. cPanel UAPI reports local authority, and
4. the current public NS set matches the expected DNS hosts.

If Cloudflare is authoritative, edit DNS in Cloudflare, not cPanel. The public portal should show a message rather than claim an edit was successful.

## Before enabling a customer

1. Confirm that WHM has created a separate mail-only cPanel package and that the client cannot use File Manager, SSH, FTP, SQL, or app hosting features.
2. Provision one throwaway test account through WHMCS's native cPanel module.
3. Confirm that its `tblhosting.username`, domain, and service ID match the private map.
4. Confirm mailbox list, password and quota APIs and DNS `parse_zone` response layout on your exact cPanel version.
5. Test an A record with no mail/DNS-critical effects. Review the `mass_edit_zone` payload shape for your cPanel version. **Leave DNS writes disabled until that integration passes.**
6. Verify client A cannot see/update client B's account, including forged service IDs.
7. Verify web to Dokploy and MX/mail to cPanel, and independent DNS authoritativeness.
8. Validate audit, anti-abuse/rate limiting, and backup/recovery in staging.

## Boundaries

The current addon does not create cPanel accounts itself; WHMCS's supported cPanel server module provisions the **ecloud_mail_dns** package. It does not manage Cloudflare zones. It is not a substitute for an email server or email backups. It does not deploy Dokploy; that work is in the separate draft provisioning module.

Caution: the current prototype does not yet have application-level rate limiting or fully validated DNS UAPI response contracts. **Do not enable mail/DNS writes in production until these controls and integration tests are complete.**
