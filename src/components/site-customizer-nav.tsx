import Link from "next/link";
import {ExternalLink,FileText,ListTree,Palette} from "lucide-react";

/** A single navigation for all customization screens; never mix independent editors. */
export function SiteCustomizerNav({current}:{current:"pages"|"menu"|"identity"}){
 const links=[
  {key:"pages" as const,href:"/admin/personalizar/paginas",label:"Páginas",Icon:FileText},
  {key:"menu" as const,href:"/admin/personalizar/menu",label:"Menú principal",Icon:ListTree},
  {key:"identity" as const,href:"/admin/personalizar/identidad",label:"Identidad visual",Icon:Palette}
 ];
 return <nav aria-label="Herramientas de personalización" className="cms-toolbar cms-toolbar-unified">
  {links.map(({key,href,label,Icon})=><Link key={key} href={href} className={current===key?"cms-toolbar-current":undefined} aria-current={current===key?"page":undefined}>
   <Icon size={17} aria-hidden="true"/><span>{label}</span>
  </Link>)}
  <Link href="/" target="_blank" rel="noopener noreferrer" className="cms-toolbar-visit">Ver sitio <ExternalLink size={16} aria-hidden="true"/></Link>
 </nav>;
}
