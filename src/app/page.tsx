import Link from "next/link";
import {Search,ArrowUpRight,ShieldCheck,Building2,MapPin} from "lucide-react";
import {getPublicContext} from "@/lib/tenant";
import {SiteNav} from "@/components/site-nav";
import {PropertyCard} from "@/components/property-card";
import {ContactForm} from "@/components/contact-form";
import {PreviewBanner} from "@/components/preview-banner";
import {PageView,FloatingWhatsapp} from "@/components/traffic-events";
export const dynamic="force-dynamic";
const DEFAULT_EYEBROW="NEXUS REAL ESTATE · NICARAGUA";
export default async function Home(){
 const {tenant,properties,demo,settings,heroImage}=await getPublicContext();
 const chosen=settings.featuredIds.length>0?properties.filter(p=>settings.featuredIds.includes(p.id)).sort((a,b)=>settings.featuredIds.indexOf(a.id)-settings.featuredIds.indexOf(b.id)):properties;
 const displayed=chosen.length?chosen.slice(0,3):properties.slice(0,3);
 const siteStyle={"--red":tenant.primaryColor,"--red-dark":tenant.primaryColor} as React.CSSProperties;
 return <div style={siteStyle}><SiteNav tenant={tenant}/>{demo?<PreviewBanner/>:<PageView/>}<main>
  <section className={"hero "+(heroImage?"has-custom-hero":"")}><div className="container hero-content"><p className="eyebrow light">{demo?settings.heroEyebrow:settings.heroEyebrow===DEFAULT_EYEBROW?tenant.name.toUpperCase()+" · NICARAGUA":settings.heroEyebrow}</p><h1>{settings.heroHeading} <em>{settings.heroHighlight}</em></h1><p>{settings.heroDescription}</p><Link href="/propiedades" className="btn btn-red">{settings.heroButton} <ArrowUpRight size={17}/></Link></div></section>
  <div className="container"><form method="get" action="/propiedades" className="search-panel"><div><label>Operación</label><select name="operacion"><option value="">Venta o alquiler</option><option value="venta">En venta</option><option value="alquiler">En alquiler</option></select></div><div><label>Tipo de propiedad</label><select name="tipo"><option value="">Todos los tipos</option><option value="Casa">Casa</option><option value="Apartamento">Apartamento</option><option value="Terreno">Terreno</option></select></div><div><label>Ubicación</label><input name="ubicacion" placeholder="Ciudad o zona"/></div><button className="btn btn-dark"><Search size={18}/> Buscar propiedades</button></form></div>
  <section className="section"><div className="container"><p className="eyebrow">{settings.sectionEyebrow}</p><div className="heading-row"><div><h2>{settings.sectionHeading} <em>{settings.sectionHighlight}</em></h2><p>{settings.sectionDescription}</p></div><Link href="/propiedades" className="inline-link">Ver todo <ArrowUpRight size={18}/></Link></div>{demo&&<p className="demo-note">Propiedades y precios ilustrativos; se reemplazarán por el catálogo real.</p>}<div className="property-grid">{displayed.map(p=><PropertyCard property={p} key={p.id}/>)}</div>{properties.length===0&&<p>No hay propiedades publicadas todavía.</p>}</div></section>
  <section className="section features-strip" id="nosotros"><div className="container feature-columns"><div><ShieldCheck size={28}/><h3>{settings.aboutTitle}</h3><p>{settings.aboutDescription}</p></div><div><Building2 size={28}/><h3>Opciones para tu futuro</h3><p>Propiedades para compra, alquiler e inversión.</p></div><div><MapPin size={28}/><h3>Conectados contigo</h3><p>Recibe atención y consulta las opciones disponibles.</p></div></div></section>
  <section className="section contact-section" id="contacto"><div className="container contact-grid"><div><p className="eyebrow light">{tenant.tagline||"PENSANDO EN TU FUTURO"}</p><h2>{settings.contactHeading} <em>{settings.contactHighlight}</em></h2><p>{settings.contactDescription}</p>{tenant.contactEmail&&<p><a href={"mailto:"+tenant.contactEmail}>{tenant.contactEmail}</a></p>}</div><div className="form-panel"><h3>Solicitar información</h3>{demo?<p className="preview-form-note">Formulario desactivado durante la vista previa. Configuraremos los contactos al conectar MariaDB.</p>:<ContactForm/>}</div></div></section>
 </main><FloatingWhatsapp tenant={tenant}/><footer><div className="container footer-content"><strong>{tenant.name.toUpperCase()}</strong><span>{tenant.tagline||"Pensando en tu futuro"} · {new Date().getFullYear()}</span><Link href="/admin">Acceso administrativo</Link></div></footer></div>;
}
