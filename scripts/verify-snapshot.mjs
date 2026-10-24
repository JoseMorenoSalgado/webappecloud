#!/usr/bin/env node
/** Read-only, streaming integrity verification for a private snapshot. */
import { createHash, timingSafeEqual } from "node:crypto";
import { createReadStream } from "node:fs";
import { lstat, readFile, realpath } from "node:fs/promises";
import { isAbsolute, join, sep } from "node:path";

const store = process.env.WEBAPPECLOUD_SNAPSHOT_DIR;
const id = process.argv[2];
if (!store || !isAbsolute(store)) throw Error("Absolute WEBAPPECLOUD_SNAPSHOT_DIR required");
if (!id || !/^snap_[a-zA-Z0-9_-]{8,75}$/.test(id)) throw Error("Invalid snapshot id");
const root = await realpath(store);
const folder = join(root, id);
const directory = await lstat(folder);
if (!directory.isDirectory() || directory.isSymbolicLink()) throw Error("Snapshot must be a real directory");
const actual = await realpath(folder);
if (!actual.startsWith(root + sep)) throw Error("Invalid snapshot location");
const raw = await readFile(join(actual, "manifest.json"), "utf8");
if (raw.length > 2_000_000) throw Error("Manifest too large");
const manifest = JSON.parse(raw);
const validPath = path => typeof path === "string" && path.length > 0 && path.length <= 512 &&
  !path.startsWith("/") && !path.includes("\\") && !path.includes("\0") &&
  path.split("/").every(part => part && part !== "." && part !== "..");
const validEntry = entry => entry && validPath(entry.path) &&
  /^[a-f0-9]{64}$/.test(entry.sha256) && Number.isSafeInteger(entry.bytes) && entry.bytes >= 0;
if (manifest.schemaVersion !== 1 || manifest.snapshotId !== id ||
  !Array.isArray(manifest.files) || manifest.files.length > 10000 ||
  !validEntry(manifest.database) || !manifest.files.every(validEntry)) throw Error("Invalid manifest");
if (manifest.database.path !== "database.sql" || manifest.files.some(entry => !entry.path.startsWith("files/"))) {
  throw Error("Unexpected snapshot structure");
}
const entries = [manifest.database, ...manifest.files];
if (new Set(entries.map(entry => entry.path)).size !== entries.length) throw Error("Duplicate entries");
for (const entry of entries) {
  const file = join(actual, entry.path);
  const info = await lstat(file);
  if (!info.isFile() || info.isSymbolicLink() || info.size !== entry.bytes) throw Error("Invalid file: " + entry.path);
  const hash = createHash("sha256");
  for await (const chunk of createReadStream(file)) hash.update(chunk);
  const expected = Buffer.from(entry.sha256, "hex");
  if (!timingSafeEqual(hash.digest(), expected)) throw Error("Checksum mismatch: " + entry.path);
}
console.log(JSON.stringify({ snapshotId: id, verified: entries.length, status: "ok" }));
