#!/usr/bin/env node
/**
 * Explicitly invoked, local-only backup runner. Not callable from an HTTP route.
 * Requires WEBAPPECLOUD_SNAPSHOT_DIR outside the application directory.
 */
import { createHash, randomUUID } from "node:crypto";
import { spawn } from "node:child_process";
import { createReadStream, createWriteStream } from "node:fs";
import { copyFile, lstat, mkdir, mkdtemp, open, readFile, readdir, realpath, rename, rm, stat, writeFile } from "node:fs/promises";
import { basename, dirname, isAbsolute, join, relative, resolve, sep } from "node:path";
import { tmpdir } from "node:os";
import { pipeline } from "node:stream/promises";

const root = await realpath(process.cwd());
const storeSetting = process.env.WEBAPPECLOUD_SNAPSHOT_DIR;
if (!storeSetting || !isAbsolute(storeSetting)) throw Error("WEBAPPECLOUD_SNAPSHOT_DIR must be an absolute path");
await mkdir(storeSetting, { recursive: true, mode: 0o700 });
const store = await realpath(storeSetting);
const within = (parent, child) => child === parent || child.startsWith(parent + sep);
if (within(root, store) || within(store, root)) throw Error("Snapshot directory must be separate from the app");
const publicDir = join(root, "public");
if (within(publicDir, store)) throw Error("Never store snapshots in public");

const dbUrl = new URL(process.env.DATABASE_URL || "");
if (!["mysql:", "mariadb:"].includes(dbUrl.protocol) || !dbUrl.hostname || !dbUrl.pathname.slice(1)) {
  throw Error("A MariaDB/MySQL DATABASE_URL is required");
}
const database = decodeURIComponent(dbUrl.pathname.slice(1));
if (!/^[a-zA-Z0-9_$-]+$/.test(database)) throw Error("Unsupported database name");
const id = "snap_" + new Date().toISOString().replace(/[-:.TZ]/g, "").slice(0, 14) + "_" + randomUUID().slice(0, 8);
const lockPath = join(store, ".snapshot.lock");
const lock = await open(lockPath, "wx", 0o600);
let working;
let configDir;
try {
  working = await mkdtemp(join(store, ".building-"));
  await mkdir(join(working, "files"), { mode: 0o700 });
  configDir = await mkdtemp(join(tmpdir(), "webappecloud-db-"));
  const escapeOption = value => String(value).replace(/\\/g, "\\\\").replace(/"/g, '\\"').replace(/\n/g, "\\n");
  const cnf = join(configDir, "client.cnf");
  await writeFile(cnf, `[client]\nuser="${escapeOption(decodeURIComponent(dbUrl.username))}"\npassword="${escapeOption(decodeURIComponent(dbUrl.password))}"\nhost="${escapeOption(dbUrl.hostname)}"\nport=${dbUrl.port || "3306"}\n`, { mode: 0o600 });
  const dumpPath = join(working, "database.sql");
  const dump = spawn(process.env.WEBAPPECLOUD_DUMP_BINARY || "mariadb-dump", [
    `--defaults-extra-file=${cnf}`, "--single-transaction", "--quick", "--routines", "--triggers", "--events",
    "--hex-blob", "--no-tablespaces", "--", database
  ], { shell: false, stdio: ["ignore", "pipe", "pipe"] });
  const output = createWriteStream(dumpPath, { flags: "wx", mode: 0o600 });
  let stderr = "";
  dump.stderr.on("data", chunk => { stderr = (stderr + chunk.toString()).slice(-2048); });
  const [exit] = await Promise.all([
    new Promise((ok, fail) => { dump.once("error", fail); dump.once("close", ok); }),
    pipeline(dump.stdout, output)
  ]);
  if (exit !== 0) throw Error("Database dump failed: " + stderr.replace(/password[^\n]*/gi, "[redacted]"));
  const entries = [];
  async function record(path, relativePath) {
    const file = await stat(path);
    const hash = createHash("sha256");
    for await (const chunk of createReadStream(path)) hash.update(chunk);
    return { path: relativePath, bytes: file.size, sha256: hash.digest("hex") };
  }
  const dbEntry = await record(dumpPath, "database.sql");
  const sourceNames = (process.env.WEBAPPECLOUD_SNAPSHOT_PATHS || "public/uploads").split(",").map(x => x.trim()).filter(Boolean);
  for (const sourceName of sourceNames) {
    if (sourceName.startsWith("/") || sourceName.split("/").some(x => !x || x === "." || x === "..") || sourceName.includes("\\")) throw Error("Invalid source path");
    const source = resolve(root, sourceName);
    if (!within(root, source) || within(store, source)) throw Error("Source outside application");
    let sourceInfo;
    try { sourceInfo = await lstat(source); } catch (e) { if (e.code === "ENOENT") throw Error("Required source missing: " + sourceName); throw e; }
    if (!sourceInfo.isDirectory() || sourceInfo.isSymbolicLink()) throw Error("Source must be a real directory");
    async function walk(dir, sub) {
      for (const item of await readdir(dir, { withFileTypes: true })) {
        if (item.isSymbolicLink()) throw Error("Symlinks cannot be included in snapshots");
        const rel = [sourceName, sub, item.name].filter(Boolean).join("/");
        const from = join(dir, item.name);
        if (item.isDirectory()) await walk(from, [sub, item.name].filter(Boolean).join("/"));
        else if (item.isFile()) {
          const dest = join(working, "files", rel);
          await mkdir(dirname(dest), { recursive: true, mode: 0o700 });
          await copyFile(from, dest);
          entries.push(await record(dest, "files/" + rel));
        } else throw Error("Unsupported filesystem entry: " + rel);
      }
    }
    await walk(source, "");
  }
  const manifest = {
    schemaVersion: 1, snapshotId: id, createdAt: new Date().toISOString(),
    appVersion: process.env.npm_package_version || "0.1.0", database: dbEntry, files: entries
  };
  await writeFile(join(working, "manifest.json"), JSON.stringify(manifest, null, 2), { flag: "wx", mode: 0o600 });
  await rename(working, join(store, id));
  working = undefined;
  console.log(JSON.stringify({ snapshotId: id, files: entries.length, location: store }));
} finally {
  if (working) await rm(working, { recursive: true, force: true });
  if (configDir) await rm(configDir, { recursive: true, force: true });
  await lock.close();
  await rm(lockPath, { force: true });
}
