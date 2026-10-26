import Link from "next/link";
import {ArrowUpRight,Images,ImagePlus} from "lucide-react";
import {SiteCustomizerNav} from "@/components/site-customizer-nav";
import {requireRole} from "@/lib/auth";
import {listTenantMedia} from "@/lib/media-library";
import "./media-gallery.css";
export const dynamic="force-dynamic";
export default async function MediaLibraryPage(){
 const user=await requireRole(["admin","editor"]);
 const images=await listTenantMedia(user.tenantId);
 return <>
  <div className="admin-heading"><div><h1>Biblioteca de imágenes</h1>
   <p>Encuentra fotografías que ya cargaste en tu sitio. Puedes reutilizarlas al editar otras páginas.</p>
  </div><Link href="/admin/personalizar/paginas" className="btn wizard-back">Editar páginas <ArrowUpRight size={16}/></Link></div>
  <SiteCustomizerNav current="media"/>
  <section className="admin-panel cms-media-overview"><div><Images size={22} aria-hidden="true"/>
   <div><h2>{images.length} imagen{images.length===1?"":"es"} disponible{images.length===1?"":"s"}</h2>
    <p>Las imágenes se guardan en formato WebP dentro de esta empresa. Se muestran hasta 120 en esta vista.</p></div>
   </div><p>Para añadir una fotografía abre una página, agrega un bloque de imagen y selecciona <strong>Subir imagen</strong>.
   Para reutilizarla elige <strong>Usar imagen existente</strong>.</p>
  </section>
  {images.length>0?<section className="cms-media-gallery" aria-label="Imágenes de tu organización">
   {images.map(item=><article key={item.slug+":"+item.id} className="cms-media-gallery-item">
    <img src={item.url} alt={"Fotografía almacenada en la página "+item.pageTitle} loading="lazy" decoding="async"/>
    <div><strong>{item.pageTitle}</strong><small>WebP · Imagen de la página</small>
     <Link href={"/admin/personalizar/paginas/"+encodeURIComponent(item.slug)}>Editar página <ArrowUpRight size={14}/></Link>
    </div>
   </article>)}
  </section>:<div className="admin-panel cms-media-empty"><ImagePlus size={29} aria-hidden="true"/>
   <h2>Todavía no hay imágenes adicionales</h2>
   <p>Puedes seguir utilizando el logotipo y el banner de tu sitio. Para crear una galería reutilizable, añade fotografías en las páginas de contenido.</p>
   <Link href="/admin/personalizar/paginas" className="btn btn-red">Ir a mis páginas</Link>
  </div>}
  <p className="property-help">Las imágenes cargadas no se eliminan automáticamente al retirarlas de una página. Su limpieza se habilitará con una comprobación de uso para evitar pérdidas accidentales.</p>
 </>;
}
