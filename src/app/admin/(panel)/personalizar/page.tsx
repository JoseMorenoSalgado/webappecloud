import Link from "next/link";
import {ArrowUpRight,BookOpen,FileText,Images,LayoutTemplate,ListTree,Settings2} from "lucide-react";
import {requireAdmin} from "@/lib/auth";
import {getPageDefinitions,getSiteMenu} from "@/lib/site-pages";
export const dynamic="force-dynamic";
const sections=[
 {href:"/admin/personalizar/paginas",Icon:FileText,title:"Páginas",description:"Crea páginas, publica o guarda como borrador y controla qué se muestra en la web."},
 {href:"/admin/personalizar/menu",Icon:ListTree,title:"Menú principal",description:"Agrega páginas publicadas, edita sus nombres y cambia el orden de navegación."},
 {href:"/admin/personalizar/contenido#inicio",Icon:LayoutTemplate,title:"Página de inicio",description:"Modifica el título, texto principal, propiedades destacadas y llamadas a la acción."},
 {href:"/admin/personalizar/contenido#identidad",Icon:Images,title:"Identidad y medios",description:"Sube el banner y logotipo, cambia el color de marca y configura datos de contacto."},
 {href:"/admin/personalizar/contenido#nosotros",Icon:BookOpen,title:"Nosotros y servicios",description:"Edita la presentación institucional, misión, visión y servicios inmobiliarios."},
 {href:"/admin/personalizar/contenido#contacto",Icon:Settings2,title:"Información de contacto",description:"Actualiza los textos de contacto, WhatsApp, correo y lema."}
];
export default async function SiteCustomizer(){
 const admin=await requireAdmin();
 const pages=await getPageDefinitions(admin.tenantId);
 const menu=await getSiteMenu(admin.tenantId,pages);
 return <>
  <div className="admin-heading"><div><h1>Personalizar sitio web</h1>
   <p>Administra las páginas, el menú y la identidad visual de tu inmobiliaria desde un solo lugar.</p></div>
   <Link href="/" target="_blank" className="btn wizard-back">Ver sitio público <ArrowUpRight size={17}/></Link>
  </div>
  <section className="admin-panel site-customizer-intro"><div>
   <span className="site-customizer-kicker">ADMINISTRACIÓN DEL SITIO</span>
   <h2>Tu web, bajo tu control.</h2>
   <p>Organiza el contenido y publica cambios sin editar archivos. Las páginas en borrador no serán visibles para los visitantes.</p>
  </div><div className="site-customizer-stats">
   <div><strong>{pages.length}</strong><span>Páginas</span></div>
   <div><strong>{pages.filter(p=>p.published).length}</strong><span>Publicadas</span></div>
   <div><strong>{menu.length}</strong><span>En el menú</span></div>
  </div></section>
  <div className="site-customizer-grid">{sections.map(({href,Icon,title,description})=>
   <Link href={href} className="site-customizer-card" key={href}><span><Icon size={24}/></span>
    <div><strong>{title}</strong><p>{description}</p></div><ArrowUpRight size={18} className="site-customizer-arrow"/>
   </Link>)}</div>
  <p className="property-help">Inicio siempre permanece disponible. Puedes ocultar otras páginas de la web y elegir cuáles forman parte del menú de navegación.</p>
 </>;
}
