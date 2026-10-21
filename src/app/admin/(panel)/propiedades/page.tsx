import Link from "next/link";
import {prisma} from "@/lib/prisma";
import {requireAdmin} from "@/lib/auth";
export const dynamic="force-dynamic";
export default async function AdminProperties(){
 const admin=await requireAdmin();const rows=await prisma.property.findMany({where:{tenantId:admin.tenantId},orderBy:{createdAt:"desc"},take:100});
 return <><div className="admin-heading"><div><h1>Propiedades</h1><p>Gestiona el catálogo publicado en tu sitio web.</p></div><Link href="/admin/propiedades/nueva" className="btn btn-red">+ Nueva propiedad</Link></div>
 <table className="admin-table"><thead><tr><th>Referencia</th><th>Propiedad</th><th>Operación</th><th>Precio</th><th>Estado</th></tr></thead><tbody>{rows.map(p=><tr key={p.id}><td>{p.refCode}</td><td><strong>{p.title}</strong><br/>{p.city}</td><td>{p.operation}</td><td>US$ {Number(p.price).toLocaleString("en-US")}</td><td>{p.published?"Publicada":"Borrador"}</td></tr>)}</tbody></table>{rows.length===0&&<p className="admin-empty">Aún no hay propiedades; crea la primera desde el botón superior.</p>}
 </>;
}
