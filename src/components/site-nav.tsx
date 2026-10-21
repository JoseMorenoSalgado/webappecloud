import Link from "next/link";
import {type PublicTenant} from "@/lib/demo";
import {Building2,Menu} from "lucide-react";
export function Brand({tenant}:{tenant:PublicTenant}){
 const isNexus=tenant.name.toLowerCase()==="nexus real estate";
 return <Link className="brand" href="/"><span className="brand-icon">{isNexus?<img src="/brand/nexus-icon.webp" alt=""/>:<Building2 size={28}/>}</span><span><strong>{isNexus?"NEXUS":tenant.name}</strong><small>{isNexus?"REAL ESTATE":tenant.tagline||"Sitio institucional"}</small></span></Link>;
}
export function SiteNav({tenant}:{tenant:PublicTenant}){return <header className="site-header"><div className="container nav-inner"><Brand tenant={tenant}/><nav aria-label="Navegación principal"><Link href="/">Inicio</Link><Link href="/propiedades">Propiedades</Link><Link href="/#nosotros">Nosotros</Link><Link href="/#contacto">Contacto</Link></nav><Link href="/propiedades" className="btn btn-red"><Menu size={17}/> Explorar propiedades</Link><details className="mobile-menu"><summary aria-label="Abrir menú"><Menu size={24}/></summary><div><Link href="/">Inicio</Link><Link href="/propiedades">Propiedades</Link><Link href="/#nosotros">Nosotros</Link><Link href="/#contacto">Contacto</Link></div></details></div></header>;}
