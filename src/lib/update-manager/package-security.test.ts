import assert from "node:assert/strict";
import { generateKeyPairSync, sign, randomBytes, createHash } from "node:crypto";
import { test } from "node:test";
import { packageSignaturePayload, validateSignedPackage, verifyPackageHash } from "./package-security";

test("signed release descriptor accepts valid Ed25519 signature and rejects tampering", () => {
  const { publicKey, privateKey } = generateKeyPairSync("ed25519");
  const payload = randomBytes(48);
  const descriptor = {
    version: "0.2.0",
    channel: "stable" as const,
    packageUrl: "https://updates.elearningcloud.io/packages/0.2.0.zip",
    sha256: createHash("sha256").update(payload).digest("hex"),
  };
  const signature = sign(null, packageSignaturePayload(descriptor), privateKey).toString("base64");
  const publicPem = publicKey.export({ type: "spki", format: "pem" }).toString();
  assert.equal(validateSignedPackage({ ...descriptor, signature }, publicPem).version, "0.2.0");
  assert.equal(verifyPackageHash(payload, descriptor.sha256), true);
  assert.equal(verifyPackageHash(Buffer.from("modified"), descriptor.sha256), false);
  assert.throws(() => validateSignedPackage({ ...descriptor, version: "0.2.1", signature }, publicPem));
  assert.throws(() => validateSignedPackage({ ...descriptor, packageUrl: "https://attacker.example/update.zip", signature }, publicPem));
  assert.throws(() => validateSignedPackage({ ...descriptor, signature: "x".repeat(88) }, publicPem));
});
