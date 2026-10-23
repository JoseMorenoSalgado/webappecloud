import Link from "next/link";
import {House,Users,Eye,MessageCircle,ArrowUpRight} from "lucide-react";
import {requireAdmin} from "@/lib/auth";
import {prisma} from "@/lib/prisma";
import {leadWhere,managesAllLeads} from "@/lib/agent-scope";
import {PropertyCard} from "@/components/property-card";
import {TrafficChart,TypesChart,type MetricDay} from "@/components/dashboard-charts";
import {type PublicProperty} from "@/lib/demo";
export const dynamic="force-dynamic";
const days=14;
export default async function Dashboard(){
 const admin=await requireAdmin(),tenantId=admin.tenantId;
 if(admin.roles.includes("agente")&&!managesAllLeads(admin))return <AgentDashboard user={admin}/>;
 const since=new Date(Date.now()-30*864e5);
 const [total,leadsCount,viewCount,waCount,props,leads,events,types]=await Promise.all([
  prisma.property.count({where:{tenantId}}),prisma.lead.count({where:{tenantId,createdAt:{gte:since}}}),
  prisma.visitEvent.count({where:{tenantId,kind:"view",createdAt:{gte:since}}}),
  prisma.visitEvent.count({where:{tenantId,kind:"whatsapp",createdAt:{gte:since}}}),
  prisma.property.findMany({where:{tenantId},include:{photos:{orderBy:{sortOrder:"asc"},take:1}},take:3,orderBy:{createdAt:"desc"}}),
  prisma.lead.findMany({where:{tenantId},take:5,orderBy:{createdAt:"desc"},include:{property:{select:{refCode:true}}}}),
  prisma.visitEvent.findMany({where:{tenantId,createdAt:{gte:new Date(Date.now()-days*864e5)}} ,select:{kind:true,createdAt:true}}),
  prisma.property.groupBy({by:["type"],where:{tenantId},_count:{_all:true}})
 ]);
 const leadSeries=await prisma.lead.findMany({where:{tenantId,createdAt:{gte:new Date(Date.now()-days*864e5)}},select:{createdAt:true}});
 const key=(d:Date)=>d.toISOString().slice(0,10);
 const today=Date.now();
 const chart:MetricDay[]=Array.from({length:days},(_,i)=>{const date=new Date(today-(days-1-i)*864e5),k=key(date);
  return {day:date.toLocaleDateString("es",{day:"2-digit",month:"short",timeZone:"UTC"}),visitas:events.filter(e=>key(e.createdAt)===k&&e.kind==="view").length,whatsapp:events.filter(e=>key(e.createdAt)===k&&e.kind==="whatsapp").length,leads:leadSeries.filter(l=>key(l.createdAt)===k).length};
 });
 const latest:PublicProperty[]=props.map(p=>({id:p.id,slug:p.slug,refCode:p.refCode,title:p.title,description:p.description,city:p.city,neighborhood:p.neighborhood||"",type:p.type,operation:p.operation==="alquiler"?"alquiler":"venta",price:Number(p.price),bedrooms:p.bedrooms||0,bathrooms:p.bathrooms||0,areaM2:p.areaM2||0,image:p.photos[0]?.url||"",photos:p.photos.map(x=>x.url)}));
 const cards=[{label:"Propiedades",value:total,Icon:House},{label:"Nuevos leads",value:leadsCount,Icon:Users},{label:"Visitas al sitio",value:viewCount,Icon:Eye},{label:"Clics en WhatsApp",value:waCount,Icon:MessageCircle}];
 return <><div className="admin-heading"><div><h1>Panel de administración</h1><p>Resumen general de tu plataforma inmobiliaria · últimos 30 días</p></div><Link href="/admin/propiedades/nueva" className="btn btn-red">+ Nueva propiedad</Link></div>
  <div className="metric-grid">{cards.map(x=><div className="metric-card" key={x.label}><div className="metric-label"><span>{x.label}</span><span className="metric-icon"><x.Icon size={20}/></span></div><strong>{x.value.toLocaleString("es")}</strong><small>{x.label==="Propiedades"?"Total registradas":"Eventos registrados en 30 días"}</small></div>)}</div>
  <div className="admin-panels"><div className="admin-panel"><h2>Visitas y contactos</h2><TrafficChart data={chart}/></div><div className="admin-panel"><h2>Tipos de propiedades</h2><TypesChart data={types.map(x=>({name:x.type,value:x._count._all}))}/></div></div>
  <div className="admin-panels"><div className="admin-panel"><div className="heading-row"><h2>Propiedades recientes</h2><Link className="inline-link" href="/admin/propiedades">Ver todas <ArrowUpRight size={16}/></Link></div>{latest.length?<div className="admin-property-list">{latest.map(p=><PropertyCard key={p.id} property={p}/>)}</div>:<p className="admin-empty">No has registrado propiedades todavía.</p>}</div>
   <div className="admin-panel"><div className="heading-row"><h2>Últimos leads</h2><Link className="inline-link" href="/admin/leads">Ver todos <ArrowUpRight size={16}/></Link></div>{leads.length?leads.map(l=><div className="lead-row" key={l.id}><div><strong>{l.name}</strong><small>{l.property?.refCode||"Consulta general"}</small></div><small>{l.createdAt.toLocaleDateString("es")}</small></div>):<p className="admin-empty">Aún no hay consultas recibidas.</p>}</div></div>
  <p className="admin-empty">Las métricas reflejan eventos registrados en la aplicación; un clic en WhatsApp no confirma una conversación.</p>
 </>;
}

async function AgentDashboard({user}:{user:{tenantId:string;userId:string;name:string;roles:string[]}}){
 const [properties,leads,totalProps,totalLeads]=await Promise.all([
  prisma.property.findMany({where:{tenantId:user.tenantId,assignedAgentId:user.userId},select:{id:true,title:true,refCode:true,slug:true,published:true,city:true},take:8,orderBy:{updatedAt:"desc"}}),
  prisma.lead.findMany({where:leadWhere(user),select:{id:true,name:true,createdAt:true,property:{select:{refCode:true}}},take:8,orderBy:{createdAt:"desc"}}),
  prisma.property.count({where:{tenantId:user.tenantId,assignedAgentId:user.userId}}),
  prisma.lead.count({where:leadWhere(user)})
 ]);
 return <>
  <div className="admin-heading"><div><h1>Mi espacio comercial</h1><p>Hola, {user.name}. Gestiona las consultas vinculadas a tus inmuebles.</p></div><Link href={"/admin/clientes-agentes/agentes/"+encodeURIComponent(user.userId)} className="btn wizard-back">Mi perfil</Link><Link className="btn wizard-back" href="/admin/reportes/agentes">Mi rendimiento</Link></div>
  <div className="metric-grid">
   <div className="metric-card"><div className="metric-label">Propiedades asignadas <House size={19}/></div><strong>{totalProps}</strong><small>Inmuebles a tu cargo</small></div>
   <div className="metric-card"><div className="metric-label">Prospectos asociados <Users size={19}/></div><strong>{totalLeads}</strong><small>Consultas sobre tus propiedades</small></div>
  </div>
  <div className="admin-panels">
   <div className="admin-panel"><div className="heading-row"><h2>Mis propiedades</h2><Link href={"/admin/clientes-agentes/agentes/"+encodeURIComponent(user.userId)} className="inline-link">Ver todas <ArrowUpRight size={15}/></Link></div>
    {properties.length?properties.map(p=><div key={p.id} className="lead-row"><div><strong>{p.refCode} · {p.title}</strong><small>{p.city} · {p.published?"Publicada":"Borrador"}</small></div>{p.published&&<Link className="inline-link" href={"/propiedades/"+encodeURIComponent(p.slug)} target="_blank">Ver ficha</Link>}</div>):<p className="admin-empty">Aún no tienes propiedades asignadas.</p>}
   </div>
   <div className="admin-panel"><div className="heading-row"><h2>Consultas recientes</h2><Link href="/admin/leads" className="inline-link">Ir al CRM <ArrowUpRight size={15}/></Link></div>
    {leads.length?leads.map(l=><div key={l.id} className="lead-row"><div><strong>{l.name}</strong><small>{l.property?.refCode||"Consulta"} · {l.createdAt.toLocaleDateString("es-NI",{timeZone:"UTC"})}</small></div><Link href={"/admin/leads/"+encodeURIComponent(l.id)} className="inline-link">Ver</Link></div>):<p className="admin-empty">No tienes consultas asociadas a tus inmuebles.</p>}
   </div>
  </div>
  <p className="property-help">Las consultas generales sin propiedad y los datos de otros agentes son gestionados por el administrador.</p>
 </>;
}
