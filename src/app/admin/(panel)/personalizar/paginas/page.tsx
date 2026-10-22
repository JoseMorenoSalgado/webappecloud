import Link from "next/link";
import {ArrowLeft,ArrowUpRight,FileText,Plus} from "lucide-react";
import {requireAdmin} from "@/lib/auth";
import {getPageDefinitions} from "@/lib/site-pages";
export const dynamic="force-dynamic";
const anchor:Record<string,string>={home:"inicio",nosotros:"nosotros",servicios:"servicios",contacto:"contacto",propiedades:"propiedades"};
export default async function PagesAdmin({searchParams}:{searchParams:Promise<{saved?:string;error?:string}>}){
 const admin=await requireAdmin(),q=await searchParams;
 const pages=await getPageDefinitions(admin.tenantId);
 const canEdit=["admin","editor"].includes(admin.role);
 return <>
  <div className="admin-heading"><div><Link href="/admin/personalizar" className="inline-link"><ArrowLeft size={16}/> Personalizar</Link>
   <h1>Páginas del sitio</h1><p>Administra el contenido y la publicación de las páginas, como en un gestor de contenidos.</p></div>
   {canEdit&&<Link href="/admin/personalizar/paginas/nueva" className="btn btn-red"><Plus size={17}/> Nueva página</Link>}</div>
  {q.saved&&<p className="property-success" role="status">Estado de publicación actualizado.</p>}
  {q.error&&<p className="admin-error" role="alert">No se pudo completar la actualización.</p>}
  <section className="admin-panel site-pages-panel"><div className="site-section-heading"><FileText size={21}/><div>
   <h2>Todas las páginas</h2><p>Las páginas publicadas tienen una URL accesible. Las que estén en borrador no aparecen en el sitio.</p></div></div>
   <div className="site-pages-list">{pages.map(page=><article className="site-page-row" key={page.id}>
    <div><strong>{page.title}</strong><span>{page.href}</span>
     <small className={page.published?"site-page-published":"site-page-draft"}>{page.published?"Publicada":"Borrador"}</small></div>
    <div className="site-page-row-actions">
     <Link href={page.builtIn?"/admin/personalizar/contenido#"+(anchor[page.slug]||"inicio"):
      "/admin/personalizar/paginas/"+page.slug} className="btn wizard-back">Editar</Link>
     {page.published&&<Link href={page.href} target="_blank" className="site-visit-link" aria-label={"Abrir "+page.title}><ArrowUpRight size={18}/></Link>}
     {canEdit&&page.id!=="home"&&<form method="post" action={"/api/admin/site-pages/"+encodeURIComponent(page.slug)}>
      <input type="hidden" name="intent" value="status"/>
      {!page.published&&<input type="hidden" name="published" value="on"/>}
      <button className="btn site-page-toggle" type="submit">{page.published?"Pasar a borrador":"Publicar"}</button>
     </form>}
    </div>
   </article>)}</div>
  </section>
  <p className="property-help">La página de Inicio no se puede despublicar. Las páginas propias pueden editarse desde aquí; el contenido de Nosotros, Servicios y Contacto se modifica en el editor de contenido.</p>
 </>;
}
