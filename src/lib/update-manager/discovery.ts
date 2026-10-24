import "server-only";
import { readFile } from "node:fs/promises";
import { join } from "node:path";
import { checkForUpdates } from "../../../lib/update-manager/check";
import type { UpdateChannel } from "../../../lib/update-manager/types";

export async function discoverUpdates(channel: UpdateChannel = "stable") {
  const pkg = JSON.parse(await readFile(join(process.cwd(), "package.json"), "utf8")) as { version?: string };
  const currentVersion = pkg.version || "0.1.0";
  const releases = await checkForUpdates(currentVersion, channel);
  return { currentVersion, releases };
}
