"use client";
import Link from "next/link";
import {usePathname} from "next/navigation";
import {type PublicTenant} from "@/lib/demo";
import {Building2,Menu,ArrowUpRight} from "lucide-react";
import {type SiteMenuItem} from "@/lib/site-pages";
export function Brand({tenant}:{tenant:PublicTenant}){
 const isNexus=tenant.name.toLowerCase()==="nexus real estate";
 return <Link className="brand nexus-brand" href="/" aria-label={"Inicio · "+tenant.name}>
  <span className="brand-icon">{tenant.logoUrl?<img src={tenant.logoUrl} alt=""/>:isNexus?<img src="/brand/nexus-icon.webp" alt=""/>:<Building2 size={28}/>}</span>
  <span><strong>{isNexus?"NEXUS":tenant.name}</strong><small>{isNexus?"REAL ESTATE":tenant.tagline||"INMOBILIARIA"}</small></span>
 </Link>;
}
export function SiteNav({tenant,links}:{tenant:PublicTenant;links:SiteMenuItem[]}){
 const path=usePathname();
 const items=()=>links.map(link=>{
  const selected=link.href==="/"?path==="/":path===link.href||path.startsWith(link.href+"/");
  return <Link key={link.href} href={link.href} aria-current={selected?"page":undefined}>{link.label}</Link>;
 });
 return <header className="site-header nexus-header">
  <div className="container nav-inner">
   <Brand tenant={tenant}/>
   <nav className="nexus-nav-links" aria-label="Navegación principal">{items()}</nav>
   {links.some(x=>x.id==="contacto")&&<Link href="/contacto" className="btn btn-red nexus-header-cta">Hablemos <ArrowUpRight size={16}/></Link>}
   <details className="mobile-menu nexus-mobile-menu">
    <summary aria-label="Abrir menú de navegación"><Menu size={24}/></summary>
    <nav aria-label="Navegación móvil">{items()}</nav>
   </details>
  </div>
 </header>;
}
