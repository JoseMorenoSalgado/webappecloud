import { NextResponse } from "next/server";

/** Liveness: confirma que Next.js escucha sin depender de MariaDB. */
export const dynamic = "force-dynamic";
export function GET() {
  return NextResponse.json(
    { status: "online", service: "webappecloud" },
    { headers: { "Cache-Control": "no-store" } }
  );
}
