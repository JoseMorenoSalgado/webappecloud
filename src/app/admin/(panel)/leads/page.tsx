import Link from "next/link";
import {Building2,CalendarClock,ChevronRight,ClipboardList,Filter,MessageCircle,Search,Users} from "lucide-react";
import {Prisma} from "@prisma/client";
import {requireAdmin} from "@/lib/auth";
import {prisma} from "@/lib/prisma";
import {crmTablesReady} from "@/lib/crm-schema";
import {CRM_STATUSES,crmStatusLabel,isCrmStatus,whatsappLeadUrl} from "@/lib/crm-status";

export const dynamic="force-dynamic";
type Search={q?:string;status?:string;page?:string;activated?:string;error?:string};
const date=(x:Date)=>x.toLocaleDateString("es-NI",{day:"2-digit",month:"short",year:"numeric",timeZone:"UTC"});
export default async function AdminLeads({searchParams}:{searchParams:Promise<Search>}){
 const admin=await requireAdmin();
 const params=await searchParams;
 const ready=await crmTablesReady();
 const q=(params.q||"").trim().slice(0,80);
 const status=params.status&&isCrmStatus(params.status)?params.status:"";
 const page=Math.min(10000,Math.max(1,Number.parseInt(params.page||"1",10)||1));
 if(!ready)return <>
  <div className="admin-heading"><div><h1>Prospectos y seguimiento</h1><p>Administra las consultas de tus posibles compradores y arrendatarios.</p></div></div>
  <section className="admin-panel crm-activation">
   <ClipboardList size={35} color="#b81e29"/>
   <h2>Activar seguimiento comercial</h2>
   <p>Conservaremos todos los prospectos actuales y añadiremos tablas independientes para registrar estados, próximas llamadas y notas. Esta ampliación no elimina ni modifica los contactos recibidos.</p>
   {params.error&&<p className="admin-error" role="alert">No fue posible activar el CRM. Comprueba los permisos de creación de tablas de MariaDB en Dokploy.</p>}
   {admin.role==="admin"?<form action="/api/admin/crm/activate" method="post"><button className="btn btn-red">Activar CRM de prospectos</button></form>:<p>Un administrador debe activar la función para empezar a utilizarla.</p>}
  </section>
 </>;
 const filters:Prisma.LeadWhereInput[]=[];
 if(q)filters.push({OR:[
  {name:{contains:q}},{phone:{contains:q}},{email:{contains:q}},
  {property:{is:{refCode:{contains:q}}}}
 ]});
 if(status==="nuevo")filters.push({OR:[{crm:{is:null}},{crm:{is:{status:"nuevo",tenantId:admin.tenantId}}}]});
 else if(status)filters.push({crm:{is:{status,tenantId:admin.tenantId}}});
 const where:Prisma.LeadWhereInput={tenantId:admin.tenantId,...(filters.length?{AND:filters}:{})};
 const [total,leads,allCount,managed]=await Promise.all([
  prisma.lead.count({where}),
  prisma.lead.findMany({where,orderBy:{createdAt:"desc"},skip:(page-1)*12,take:12,
   include:{property:{select:{refCode:true,title:true}},crm:true}}),
  prisma.lead.count({where:{tenantId:admin.tenantId}}),
  prisma.leadCrm.groupBy({by:["status"],where:{tenantId:admin.tenantId},_count:{_all:true}})
 ]);
 const counts=new Map(managed.map(x=>[x.status,x._count._all]));
 const newCount=allCount-managed.filter(x=>x.status!=="nuevo").reduce((n,x)=>n+x._count._all,0);
 const paramsFor=(p:number)=>{const u=new URLSearchParams({q,status,page:String(p)});return "/admin/leads?"+u.toString();};
 const canEdit=["admin","editor"].includes(admin.role);
 return <>
  <div className="admin-heading"><div><h1>Prospectos y seguimiento</h1><p>Organiza las consultas inmobiliarias, programa contactos y registra gestiones.</p></div></div>
  {params.activated&&<p className="property-success" role="status">CRM activado correctamente. Los contactos anteriores se conservaron.</p>}
  {params.error&&<p className="admin-error" role="alert">No se pudo completar la operación. Comprueba los permisos del usuario.</p>}
  <div className="crm-overview">
   <div><span>Total de consultas</span><strong>{allCount}</strong><Users size={20}/></div>
   <div><span>Nuevos prospectos</span><strong>{newCount}</strong><MessageCircle size={20}/></div>
   <div><span>En seguimiento</span><strong>{(counts.get("contactado")||0)+(counts.get("visita")||0)+(counts.get("negociacion")||0)}</strong><CalendarClock size={20}/></div>
  </div>
  <form className="crm-filters" method="get" action="/admin/leads">
   <label><span>Buscar contacto</span><div className="property-search"><Search size={17}/><input name="q" placeholder="Nombre, teléfono, correo o referencia" defaultValue={q} maxLength={80}/></div></label>
   <label><span>Estado comercial</span><select name="status" defaultValue={status}>
    <option value="">Todos los estados</option>
    {CRM_STATUSES.map(s=><option value={s.value} key={s.value}>{s.label}</option>)}
   </select></label>
   <button className="btn btn-dark" type="submit"><Filter size={16}/> Filtrar</button>
  </form>
  <div className="property-result-heading"><strong>{total} resultados</strong><span>Página {page} de {Math.max(1,Math.ceil(total/12))}</span></div>
  {leads.length?<div className="crm-list">
   {leads.map(l=>{
    const wa=whatsappLeadUrl(l.phone,admin.tenantName,l.property?.refCode);
    const progress=l.crm;
    return <article key={l.id} className="crm-lead-card">
     <div className="crm-lead-main">
      <div className="crm-avatar" aria-hidden="true">{l.name.slice(0,1).toLocaleUpperCase("es")}</div>
      <div className="crm-lead-name"><strong>{l.name}</strong><span>{l.phone} {l.email?" · "+l.email:""}</span><small>{date(l.createdAt)}</small></div>
     </div>
     <div className="crm-lead-property"><Building2 size={15}/><span>{l.property?.refCode?l.property.refCode+" · "+l.property.title:"Consulta general"}</span></div>
     <div className="crm-lead-side">
      <span className={"crm-status crm-status-"+(progress?.status||"nuevo")}>{crmStatusLabel(progress?.status||"nuevo")}</span>
      {progress?.nextContactAt&&<small>Próximo contacto: {date(progress.nextContactAt)}</small>}
      <div className="crm-lead-actions">
       {wa&&<a href={wa} target="_blank" rel="noopener noreferrer" className="crm-whatsapp-link"><MessageCircle size={16}/> WhatsApp</a>}
       <Link href={"/admin/leads/"+encodeURIComponent(l.id)} className="btn wizard-back">Ver ficha <ChevronRight size={16}/></Link>
      </div>
     </div>
    </article>;
   })}</div>:<div className="property-empty"><Users size={34}/><h2>Sin prospectos</h2><p>{q||status?"Prueba con otros filtros.":"Las consultas enviadas desde tu sitio web aparecerán aquí."}</p></div>}
  {total>12&&<nav className="property-pagination" aria-label="Paginación de prospectos">
   {page>1&&<Link href={paramsFor(page-1)} className="btn wizard-back">← Anterior</Link>}
   {page*12<total&&<Link href={paramsFor(page+1)} className="btn wizard-back">Siguiente →</Link>}
  </nav>}
  {!canEdit&&<p className="property-help">Tu cuenta puede consultar los prospectos, pero no modificar sus estados ni agregar notas.</p>}
 </>;
}
