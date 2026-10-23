import Link from "next/link";
import { requireRole } from "@/lib/auth";
import UpdateManagerDemo from "@/components/update-manager/UpdateManagerDemo";

export const dynamic = "force-dynamic";

/** Administration-only simulation; no update packages or backups are executed. */
export default async function ActualizacionesPage() {
  await requireRole(["owner", "admin", "administrator", "superadmin"]);
  return <main className="min-h-screen bg-white p-4 text-slate-900 md:p-8">
    <nav className="mx-auto mb-4 max-w-3xl text-sm"><Link href="/admin" className="underline">← Volver al panel</Link></nav>
    <UpdateManagerDemo />
  </main>;
}
