import { createHash, createPublicKey, verify } from "node:crypto";

export type SignedPackage = {
  version: string;
  channel: "stable" | "beta";
  packageUrl: string;
  sha256: string;
  signature: string;
};

/**
 * Ed25519 signature is over canonical payload, not over untrusted JSON formatting.
 * The signing private key stays on the release publisher; only the public key
 * is configured on each CMS installation.
 */
export function packageSignaturePayload(pkg: Omit<SignedPackage, "signature">): Buffer {
  return Buffer.from(
    ["webappecloud-update-v1", pkg.version, pkg.channel, pkg.packageUrl, pkg.sha256].join("\n"),
    "utf8",
  );
}

export function validateSignedPackage(
  candidate: unknown,
  trustedPublicKeyPem: string,
  allowedHost = "updates.elearningcloud.io",
): SignedPackage {
  if (!candidate || typeof candidate !== "object" || Array.isArray(candidate)) {
    throw new Error("Invalid package descriptor");
  }
  const p = candidate as Record<string, unknown>;
  if (typeof p.version !== "string" || !/^[0-9]+\.[0-9]+\.[0-9]+(?:-[0-9A-Za-z.-]+)?$/.test(p.version)) {
    throw new Error("Invalid release version");
  }
  if (p.channel !== "stable" && p.channel !== "beta") throw new Error("Invalid release channel");
  if (typeof p.sha256 !== "string" || !/^[a-f0-9]{64}$/.test(p.sha256)) throw new Error("Invalid SHA256");
  if (typeof p.packageUrl !== "string") throw new Error("Invalid package URL");
  const url = new URL(p.packageUrl);
  if (url.protocol !== "https:" || url.hostname !== allowedHost || url.port || url.username || url.password || url.hash) {
    throw new Error("Untrusted package URL");
  }
  if (typeof p.signature !== "string" || !/^[A-Za-z0-9+/]{86}==$/.test(p.signature)) {
    // Base64 Ed25519 signatures are exactly 88 characters with == padding.
    throw new Error("Invalid signature encoding");
  }
  const signed: SignedPackage = {
    version: p.version,
    channel: p.channel,
    packageUrl: p.packageUrl,
    sha256: p.sha256,
    signature: p.signature,
  };
  const key = createPublicKey(trustedPublicKeyPem);
  if (key.asymmetricKeyType !== "ed25519" || !verify(null, packageSignaturePayload(signed), key, Buffer.from(signed.signature, "base64"))) {
    throw new Error("Release signature verification failed");
  }
  return signed;
}

export function verifyPackageHash(contents: Buffer, expectedSha256: string): boolean {
  return /^[a-f0-9]{64}$/.test(expectedSha256) &&
    createHash("sha256").update(contents).digest("hex") === expectedSha256;
}
