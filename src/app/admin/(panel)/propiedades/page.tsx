import Link from "next/link";
import {Building2,BedDouble,Bath,Maximize2,MapPin,Pencil,Plus,Search,Image as ImageIcon,ExternalLink} from "lucide-react";
import {prisma} from "@/lib/prisma";
import {requireAdmin} from "@/lib/auth";
export const dynamic="force-dynamic";
type Filters={q?:string;status?:string;operation?:string;page?:string;updated?:string;deleted?:string;error?:string};
const money=(x:number)=>new Intl.NumberFormat("en-US",{style:"currency",currency:"USD",maximumFractionDigits:0}).format(x);
export default async function AdminProperties({searchParams}:{searchParams:Promise<Filters>}){
 const admin=await requireAdmin();
 const x=await searchParams;
 const q=(x.q||"").trim().slice(0,70),status=x.status||"",operation=x.operation||"";
 const page=Math.max(1,Math.min(10000,Number.parseInt(x.page||"1",10)||1));
 const where={
  tenantId:admin.tenantId,
  ...(q?{OR:[{title:{contains:q}},{refCode:{contains:q}},{city:{contains:q}}]}:{}),
  ...(status==="publicada"?{published:true}:status==="borrador"?{published:false}:{}),
  ...(["venta","alquiler"].includes(operation)?{operation}:{})
 };
 const [total,rows,activeCount,draftCount]=await Promise.all([
  prisma.property.count({where}),
  prisma.property.findMany({where,include:{photos:{orderBy:{sortOrder:"asc"},take:1}},orderBy:{createdAt:"desc"},skip:(page-1)*12,take:12}),
  prisma.property.count({where:{tenantId:admin.tenantId,published:true}}),
  prisma.property.count({where:{tenantId:admin.tenantId,published:false}})
 ]);
 const filters=new URLSearchParams({q,status,operation}),nextUrl=(p:number)=>{const params=new URLSearchParams(filters);params.set("page",String(p));return "/admin/propiedades?"+params.toString();};
 const canEdit=admin.role==="admin"||admin.role==="editor";
 return <>
  <div className="admin-heading"><div><h1>Propiedades</h1><p>Organiza, publica y administra el catálogo de NEXUS Real Estate.</p></div>{canEdit&&<Link href="/admin/propiedades/nueva" className="btn btn-red"><Plus size={18}/> Nueva propiedad</Link>}</div>
  {(x.updated||x.deleted)&&<div className="property-success" role="status">{x.deleted?"Propiedad eliminada.":"Estado de publicación actualizado."}</div>}
  {x.error&&<div className="admin-error" role="alert">No fue posible completar la operación solicitada.</div>}
  <div className="property-summary"><div><strong>{activeCount+draftCount}</strong><span>Propiedades totales</span></div><div><strong>{activeCount}</strong><span>Publicadas</span></div><div><strong>{draftCount}</strong><span>Borradores</span></div></div>
  <form method="get" className="property-filters" action="/admin/propiedades">
   <label><span>Buscar inmuebles</span><div className="property-search"><Search size={17}/><input name="q" placeholder="Referencia, nombre o ciudad" defaultValue={q}/></div></label>
   <label><span>Estado</span><select name="status" defaultValue={status}><option value="">Todos</option><option value="publicada">Publicadas</option><option value="borrador">Borradores</option></select></label>
   <label><span>Operación</span><select name="operation" defaultValue={operation}><option value="">Todas</option><option value="venta">Venta</option><option value="alquiler">Alquiler</option></select></label>
   <button className="btn btn-dark" type="submit">Filtrar</button>
  </form>
  <div className="property-result-heading"><strong>{total} resultados</strong><span>Página {page} de {Math.max(1,Math.ceil(total/12))}</span></div>
  {rows.length?<div className="property-admin-grid">{rows.map(p=>{
   const photo=p.photos[0];
   return <article key={p.id} className="property-admin-card">
    <div className="property-admin-photo">{photo?<img loading="lazy" src={photo.url} alt={p.title}/>:<div className="property-photo-placeholder"><ImageIcon size={32}/><span>Sin fotografía</span></div>}
     <span className={"property-admin-status "+(p.published?"is-published":"is-draft")}>{p.published?"Publicada":"Borrador"}</span>
    </div>
    <div className="property-admin-content">
     <div className="property-admin-code">{p.refCode}<span>{p.operation==="venta"?"En venta":"En alquiler"}</span></div>
     <h2>{p.title}</h2><div className="property-admin-location"><MapPin size={15}/>{[p.neighborhood,p.city].filter(Boolean).join(", ")}</div>
     <strong className="property-admin-price">{money(Number(p.price))}</strong>
     <div className="property-admin-facts"><span><BedDouble size={15}/>{p.bedrooms??0} hab.</span><span><Bath size={15}/>{p.bathrooms??0} baños</span><span><Maximize2 size={15}/>{p.areaM2??0} m²</span></div>
     <div className="property-admin-actions">
      <Link href={"/admin/propiedades/"+encodeURIComponent(p.id)+"/editar"} className="btn wizard-back"><Pencil size={15}/> Editar</Link>
      {p.published?<Link className="property-admin-view" target="_blank" href={"/propiedades/"+encodeURIComponent(p.slug)} aria-label={"Ver "+p.title}><ExternalLink size={18}/></Link>:null}
      {canEdit&&<form method="post" action={"/api/admin/properties/"+encodeURIComponent(p.id)}><input type="hidden" name="intent" value={p.published?"unpublish":"publish"}/><button type="submit" className="btn btn-text">{p.published?"Ocultar":"Publicar"}</button></form>}
     </div>
    </div>
   </article>;
  })}</div>:<div className="property-empty"><Building2 size={34}/><h2>No encontramos propiedades</h2><p>Prueba con otros filtros o crea tu primer inmueble.</p>{canEdit&&<Link href="/admin/propiedades/nueva" className="btn btn-red">Crear propiedad</Link>}</div>}
  {total>12&&<div className="property-pagination">{page>1&&<Link className="btn wizard-back" href={nextUrl(page-1)}>← Anterior</Link>}{page*12<total&&<Link className="btn wizard-back" href={nextUrl(page+1)}>Siguiente →</Link>}</div>}
 </>;
}
