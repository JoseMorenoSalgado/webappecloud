import Link from "next/link";
import {notFound} from "next/navigation";
import {ExternalLink,Save,Trash2} from "lucide-react";
import {requireRole} from "@/lib/auth";
import {prisma} from "@/lib/prisma";
import {parseRoles} from "@/lib/roles";
import {AdminPropertyFields} from "@/components/admin-property-fields";
import {PropertyPhotoUploader} from "@/components/property-photo-uploader";
import {PropertyDocumentManager} from "@/components/property-document-manager";
import {hasCustomPdf} from "@/lib/property-document";
export const dynamic="force-dynamic";
const errors:Record<string,string>={
 datos:"Revisa los campos del formulario y vuelve a guardar.",
 precio:"El precio no es válido.",
 duplicado:"Ya existe una propiedad con la misma referencia.",
 origen:"No se pudo verificar el origen del formulario.",
 confirmacion:"Para eliminar el inmueble debes confirmar su referencia. Solo un administrador puede hacerlo.",
 servidor:"No fue posible guardar los cambios. Revisa los registros de Dokploy."
};
export default async function EditProperty({params,searchParams}:{params:Promise<{id:string}>;searchParams:Promise<{error?:string;created?:string;saved?:string}>}){
 const admin=await requireRole(["admin","editor"]);
 const {id}=await params;
 const q=await searchParams;
 const property=await prisma.property.findFirst({where:{id,tenantId:admin.tenantId},include:{photos:{orderBy:{sortOrder:"asc"}}}});
 if(!property)notFound();
 const canEdit=admin.roles.some(r=>["admin","editor"].includes(r));
 const hasDocument=await hasCustomPdf(admin.tenantId,property.id);
 const users=await prisma.adminUser.findMany({where:{tenantId:admin.tenantId,active:true},select:{id:true,name:true,email:true,role:true}});
 const agents=users.filter(u=>parseRoles(u.role).includes("agente"));
 return <>
  <div className="admin-heading"><div><h1>Editar propiedad</h1><p>Referencia {property.refCode} · {property.published?"Publicada":"Borrador"}</p></div><Link href="/admin/propiedades" className="inline-link">← Propiedades</Link></div>
  {q.created&&<p className="property-success" role="status">Propiedad creada correctamente. Ahora puedes añadir fotografías.</p>}
  {q.saved&&<p className="property-success" role="status">Cambios guardados correctamente.</p>}
  {q.error&&<p className="admin-error" role="alert">{errors[q.error]||errors.servidor}</p>}
  {canEdit&&<form action={"/api/admin/properties/"+encodeURIComponent(id)} method="post" className="admin-form property-editor">
   <input type="hidden" name="intent" value="save"/>
   <AdminPropertyFields property={{...property,price:Number(property.price)}} agents={agents}/>
   <div className="property-editor-actions">
    <button type="submit" className="btn btn-red"><Save size={17}/> Guardar cambios</button>
    {property.published&&<Link className="btn wizard-back" href={"/propiedades/"+encodeURIComponent(property.slug)} target="_blank">Ver publicada <ExternalLink size={15}/></Link>}
   </div>
  </form>}
  <section className="admin-panel property-photo-panel"><h2>Galería de fotografías</h2><p>JPG, PNG o WebP. Máximo 8 MB cada una y 15 fotografías por inmueble. El servidor elimina los metadatos de ubicación al convertirlas a WebP.</p>
   {canEdit?<PropertyPhotoUploader propertyId={id} photos={property.photos.map(x=>({id:x.id,url:x.url,alt:x.alt||property.title}))}/>:<p>Solo disponible para administradores y editores.</p>}
  </section>
  <section className="admin-panel property-photo-panel">
   <div className="property-pdf-heading"><h2>Ficha técnica en PDF</h2><p>Ofrece la ficha automática y también una versión corporativa preparada por NEXUS.</p></div>
   <div className="property-pdf-auto">
    <div><strong>Ficha automática</strong><span>Se genera con el precio, ubicación, descripción y fotografías del inmueble.</span></div>
    {property.published?<Link href={"/api/propiedades/"+encodeURIComponent(property.slug)+"/pdf"} className="btn wizard-back">Descargar automática</Link>:<span className="property-help">Disponible al publicar el inmueble</span>}
   </div>
   <PropertyDocumentManager propertyId={id} hasDocument={hasDocument} canEdit={canEdit}/>
  </section>
  {admin.roles.includes("admin")&&<details className="property-danger"><summary><Trash2 size={16}/> Eliminar inmueble</summary>
   <p>La eliminación es permanente. Las consultas de prospectos existentes se conservarán, pero dejarán de estar asociadas a este inmueble.</p>
   <form method="post" action={"/api/admin/properties/"+encodeURIComponent(id)}>
    <input type="hidden" name="intent" value="delete"/>
    <label>Escribe <strong>{property.refCode}</strong> para confirmar<input name="confirmation" required autoComplete="off" placeholder={property.refCode}/></label>
    <button type="submit" className="btn btn-danger">Eliminar permanentemente</button>
   </form>
  </details>}
 </>;
}
