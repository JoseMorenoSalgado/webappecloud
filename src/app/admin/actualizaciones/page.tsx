import { requireRole } from "@/lib/auth";
import { discoverUpdates } from "@/lib/update-manager/discovery";
import UpdateManagerDemo from "@/components/update-manager/UpdateManagerDemo";

export const dynamic = "force-dynamic";

export default async function ActualizacionesPage() {
  await requireRole(["admin"]);
  let discovery: Awaited<ReturnType<typeof discoverUpdates>> | null = null;
  let error: string | null = null;
  try {
    discovery = await discoverUpdates("stable");
  } catch {
    error = "No se pudo consultar el servidor de actualizaciones. Comprueba la configuración o la conexión HTTPS.";
  }
  return <main className="min-h-screen bg-white p-4 text-slate-900 md:p-8">
    <section className="mx-auto mb-5 max-w-3xl rounded-xl border bg-white p-4" aria-label="Actualizaciones disponibles">
      <h2 className="text-lg font-semibold">Consulta real de actualizaciones</h2>
      <p className="text-sm">Versión del paquete instalado: <strong>{discovery?.currentVersion ?? "No disponible"}</strong></p>
      {error ? <p role="status" className="mt-2 text-sm text-amber-800">{error}</p> :
        discovery?.releases.length ? <ul className="mt-3 space-y-2">{discovery.releases.map(release =>
          <li key={release.version} className="rounded-lg border p-3">
            <strong>{release.title} — v{release.version}</strong>
            <p className="text-sm">{release.notes}</p>
            <p className="text-xs">Canal: {release.channel} · Tipo: {release.category}</p>
          </li>
        )}</ul> : <p className="mt-2 text-sm">No hay versiones estables más recientes en el manifiesto.</p>}
      <p className="mt-2 text-xs text-slate-600">Solo consulta información; no descarga ni instala paquetes.</p>
    </section>
    <UpdateManagerDemo />
  </main>;
}
