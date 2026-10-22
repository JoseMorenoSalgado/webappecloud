import Link from "next/link";
import {ArrowLeft,ExternalLink,ListTree} from "lucide-react";
import {requireRole} from "@/lib/auth";
import {getPageDefinitions,getSiteMenu} from "@/lib/site-pages";
import {SiteMenuEditor} from "@/components/site-menu-editor";
export const dynamic="force-dynamic";
export default async function MenuSettings({searchParams}:{searchParams:Promise<{error?:string;saved?:string}>}){
 const admin=await requireRole(["admin","editor"]);
 const pages=await getPageDefinitions(admin.tenantId);
 const menu=await getSiteMenu(admin.tenantId,pages);
 const q=await searchParams;
 return <>
  <div className="admin-heading"><div><Link href="/admin/personalizar" className="inline-link"><ArrowLeft size={16}/> Personalizar</Link>
   <h1>Menú principal</h1><p>Elige las páginas visibles en el encabezado, cambia sus textos y organízalas con las flechas.</p></div>
   <Link className="btn wizard-back" href="/" target="_blank">Ver sitio <ExternalLink size={16}/></Link></div>
  {q.saved&&<p role="status" className="property-success">Menú guardado y publicado correctamente.</p>}
  {q.error&&<p role="alert" className="admin-error">No se pudo actualizar el menú. Comprueba los nombres, permisos y páginas publicadas.</p>}
  <section className="admin-panel site-menu-panel"><div className="site-section-heading"><ListTree size={22}/>
   <div><h2>Editar navegación</h2><p>Solo puedes añadir páginas publicadas. Quitar una del menú no la elimina ni la convierte en borrador.</p></div></div>
   {["admin","editor"].includes(admin.role)?
    <SiteMenuEditor pages={pages} current={menu}/>:
    <p>No tienes permiso para editar el menú.</p>}
  </section>
 </>;
}
