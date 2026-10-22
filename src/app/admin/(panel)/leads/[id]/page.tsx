import Link from "next/link";
import {notFound,redirect} from "next/navigation";
import {ArrowLeft,Building2,CalendarClock,Mail,MessageCircle,Phone,Save,StickyNote} from "lucide-react";
import {requireAdmin} from "@/lib/auth";
import {prisma} from "@/lib/prisma";
import {crmTablesReady} from "@/lib/crm-schema";
import {CRM_STATUSES,crmStatusLabel,whatsappLeadUrl} from "@/lib/crm-status";
export const dynamic="force-dynamic";
type Params={error?:string;saved?:string;noteSaved?:string};
const date=(v:Date)=>v.toLocaleDateString("es-NI",{day:"2-digit",month:"short",year:"numeric",timeZone:"UTC"});
const errors:Record<string,string>={
 origen:"El formulario no tiene un origen válido. Recarga la página.",
 permisos:"No tienes permiso para editar este prospecto.",
 datos:"La solicitud contiene datos inválidos.",
 estado:"Selecciona un estado válido.",
 fecha:"La fecha de próximo contacto no es válida.",
 nota:"La nota debe tener entre 3 y 2000 caracteres.",
 servidor:"No fue posible guardar el cambio. Revisa los registros del servidor."
};
export default async function CrmLead({params,searchParams}:{params:Promise<{id:string}>,searchParams:Promise<Params>}){
 const admin=await requireAdmin();
 if(!await crmTablesReady())redirect("/admin/leads");
 const {id}=await params,q=await searchParams;
 const lead=await prisma.lead.findFirst({
  where:{id,tenantId:admin.tenantId},
  include:{crm:true,notes:{where:{tenantId:admin.tenantId},orderBy:{createdAt:"desc"},take:50},property:{select:{refCode:true,title:true,slug:true}}}
 });
 if(!lead)notFound();
 const canEdit=["admin","editor"].includes(admin.role);
 const wa=whatsappLeadUrl(lead.phone,admin.tenantName,lead.property?.refCode);
 const url="/api/admin/crm/leads/"+encodeURIComponent(id);
 return <>
  <div className="crm-detail-head"><Link href="/admin/leads" className="inline-link"><ArrowLeft size={16}/> Volver a prospectos</Link><span className={"crm-status crm-status-"+(lead.crm?.status||"nuevo")}>{crmStatusLabel(lead.crm?.status||"nuevo")}</span></div>
  <div className="admin-heading"><div><h1>{lead.name}</h1><p>Consulta recibida el {date(lead.createdAt)} · Origen: {lead.source==="web"?"Formulario web":lead.source}</p></div></div>
  {(q.saved||q.noteSaved)&&<p className="property-success" role="status">{q.saved?"Seguimiento guardado correctamente.":"Nota registrada en el historial."}</p>}
  {q.error&&<p className="admin-error" role="alert">{errors[q.error]||errors.servidor}</p>}
  <div className="crm-detail-grid">
   <div className="crm-detail-primary">
    <section className="admin-panel crm-info-panel"><h2>Datos del prospecto</h2>
     <div className="crm-contact-data"><span><Phone size={17}/>{lead.phone}</span>
     {lead.email&&<span><Mail size={17}/>{lead.email}</span>}
     <span><Building2 size={17}/>{lead.property?.refCode?lead.property.refCode+" · "+lead.property.title:"Consulta general"}</span></div>
     {lead.message&&<div className="crm-original-message"><strong>Mensaje recibido</strong><p>{lead.message}</p></div>}
     <div className="crm-contact-actions">
      {wa&&<a href={wa} target="_blank" rel="noopener noreferrer" className="btn crm-whatsapp-btn"><MessageCircle size={18}/> Abrir WhatsApp</a>}
      {lead.email&&<a className="btn wizard-back" href={"mailto:"+lead.email}><Mail size={17}/> Enviar correo</a>}
     </div>
     <p className="property-help">Abrir WhatsApp no equivale a confirmar que el cliente respondió. Registra el contacto realizado mediante el estado y una nota.</p>
    </section>
    <section className="admin-panel crm-notes-panel"><h2><StickyNote size={19}/> Historial de notas</h2>
     {canEdit&&<form method="post" action={url} className="crm-note-form"><input type="hidden" name="intent" value="note"/>
      <label>Registrar gestión<textarea name="body" placeholder="Ej.: Llamé al cliente. Desea visitar la propiedad el viernes." minLength={3} maxLength={2000} required/></label>
      <button className="btn btn-red" type="submit">Guardar nota</button>
     </form>}
     <div className="crm-note-list">{lead.notes.length?lead.notes.map(n=><article className="crm-note" key={n.id}>
      <div><strong>{n.authorName}</strong><time dateTime={n.createdAt.toISOString()}>{date(n.createdAt)}</time></div>
      <p>{n.body}</p>
     </article>):<p className="property-help">Todavía no hay notas de seguimiento.</p>}</div>
    </section>
   </div>
   <aside className="admin-panel crm-followup-panel"><h2><CalendarClock size={19}/> Seguimiento</h2>
    <p>Actualiza la etapa comercial y programa el próximo contacto.</p>
    {canEdit?<form method="post" action={url} className="crm-followup-form">
     <input type="hidden" name="intent" value="status"/>
     <label>Estado comercial<select name="status" defaultValue={lead.crm?.status||"nuevo"}>
      {CRM_STATUSES.map(s=><option key={s.value} value={s.value}>{s.label}</option>)}
     </select></label>
     <label>Próximo contacto<input name="nextContactAt" type="date" defaultValue={lead.crm?.nextContactAt?.toISOString().slice(0,10)||""}/></label>
     <button className="btn btn-red" type="submit"><Save size={17}/> Guardar seguimiento</button>
    </form>:<div className="crm-contact-data"><strong>{crmStatusLabel(lead.crm?.status||"nuevo")}</strong>{lead.crm?.nextContactAt&&<span>Próximo contacto: {date(lead.crm.nextContactAt)}</span>}</div>}
    {lead.property&&<Link className="inline-link" href={"/admin/propiedades/"+encodeURIComponent(lead.propertyId||"")+"/editar"}>Ver inmueble asociado →</Link>}
   </aside>
  </div>
 </>;
}
