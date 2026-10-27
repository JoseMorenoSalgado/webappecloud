#!/usr/bin/env node
/**
 * WebAppECloud — read-only Dokploy runtime diagnosis.
 *
 * Run INSIDE the current application container:
 *    node scripts/diagnose-dokploy.mjs nexusrealestatenicaragua.com
 *
 * Never logs database credentials, connection strings, tenants, or exceptions.
 * Does NOT run prisma db push, seed, migrations or any remote write.
 */
import {existsSync, readFileSync} from "node:fs";
import {resolve, join} from "node:path";

const check = (code, ok) => console.log((ok ? "OK " : "FAIL ") + code);

function safeCode(error) {
  const code = error && typeof error === "object" && "code" in error
    ? String(error.code) : "";
  return /^(?:P10\d\d|P20\d\d)$/.test(code) ? code : "RUNTIME_ERROR";
}

const input = process.argv[2] || "";
const host = input.toLowerCase().trim().replace(/:\d+$/, "").replace(/\.$/, "");
if (!/^(?=.{4,253}$)[a-z0-9](?:[a-z0-9.-]*[a-z0-9])$/.test(host)
    || (host !== "localhost" && !host.includes(".")) || host.includes("..")) {
  console.error("Uso: node scripts/diagnose-dokploy.mjs ejemplo.com");
  process.exitCode = 2;
} else {
  const dir = resolve(process.env.APP_CONFIG_DIR || join(process.cwd(), ".runtime"));
  const installationFile = join(dir, "installation.json");
  const hasStored = existsSync(installationFile);
  check("APP_CONFIG_DIR_PRESENT", existsSync(dir));
  check("INSTALLATION_FILE_EXISTS", hasStored);

  let url = process.env.DATABASE_URL?.trim() || "";
  let ready = true;
  if (hasStored) {
    try {
      const data = JSON.parse(readFileSync(installationFile, "utf8"));
      if (data.version !== 1 || typeof data.databaseUrl !== "string" || !data.databaseUrl) {
        throw Error("Invalid installation file");
      }
      // Match src/lib/server-config.ts: persisted installation wins over env.
      url = data.databaseUrl;
    } catch {
      check("INSTALLATION_CONFIG_VALID", false);
      ready = false;
    }
  }
  if (ready) {
    check("DATABASE_URL_EFFECTIVE", Boolean(url));
    if (!url) ready = false;
  }
  let engine = "";
  if (ready) {
    try {
      const protocol = new URL(url).protocol;
      engine = protocol === "mysql:" ? "mysql" :
        protocol === "postgresql:" || protocol === "postgres:" ? "postgres" : "";
    } catch { /* Invalid DATABASE_URL syntax */ }
    check("DATABASE_PROVIDER_SUPPORTED", Boolean(engine));
    if (!engine) ready = false;
  }
  if (ready) {
    let db;
    try {
      const Client = engine === "mysql"
        ? (await import("@prisma/client")).PrismaClient
        : (await import("../src/generated/postgres/index.js")).PrismaClient;
      db = new Client({datasources:{db:{url}}, log:[]});
      // A read-only SELECT 1 distinguishes connectivity from schema readiness.
      await db.$queryRawUnsafe("SELECT 1");
      check("SQL_CONNECTION_OK", true);
      try {
        // Domain + Tenant + SitePage all needed to render the public homepage.
        await db.tenant.count();
        await db.sitePage.count();
        check("APPLICATION_SCHEMA_PRESENT", true);
      } catch (error) {
        check("APPLICATION_SCHEMA_PRESENT_" + safeCode(error), false);
        ready = false;
      }
      if (ready) {
        try {
          const row = await db.domain.findUnique({
            where:{hostname:host},
            select:{tenant:{select:{active:true}}},
          });
          check("DOMAIN_LINKED_TO_ACTIVE_TENANT", Boolean(row?.tenant?.active));
          if (!row?.tenant?.active) ready = false;
        } catch (error) {
          check("DOMAIN_TABLE_READ_" + safeCode(error), false);
          ready = false;
        }
      }
    } catch (error) {
      check("SQL_CONNECTION_" + safeCode(error), false);
      ready = false;
    } finally {
      try { await db?.$disconnect(); } catch { /* Read-only cleanup */ }
    }
  }
  console.log(ready
    ? "RESULT READY_FOR_PUBLIC_REQUEST; check Dokploy runtime logs if the homepage still fails."
    : "RESULT NOT_READY; inspect FAIL codes and existing database/volume without deleting data.");
  if (!ready) process.exitCode = 1;
}
