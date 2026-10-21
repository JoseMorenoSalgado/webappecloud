import {prisma} from "@/lib/prisma";
import {requireAdmin} from "@/lib/auth";
export const dynamic="force-dynamic";
export default async function AdminLeads(){
 const a=await requireAdmin();const leads=await prisma.lead.findMany({where:{tenantId:a.tenantId},orderBy:{createdAt:"desc"},take:100,include:{property:{select:{refCode:true,title:true}}}});
 return <><div className="admin-heading"><div><h1>Solicitudes y leads</h1><p>Prospectos captados desde los formularios públicos.</p></div></div>
 <table className="admin-table"><thead><tr><th>Fecha</th><th>Nombre</th><th>Contacto</th><th>Propiedad</th><th>Mensaje</th></tr></thead><tbody>{leads.map(l=><tr key={l.id}><td>{l.createdAt.toLocaleDateString("es")}</td><td><strong>{l.name}</strong></td><td>{l.phone}<br/>{l.email}</td><td>{l.property?.refCode||"General"}</td><td>{l.message||"—"}</td></tr>)}</tbody></table>{leads.length===0&&<p className="admin-empty">Los nuevos contactos aparecerán aquí automáticamente.</p>}
 </>;
}
