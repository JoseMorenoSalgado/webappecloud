import Link from "next/link";
import {notFound} from "next/navigation";
import {MapPin,BedDouble,Bath,Maximize2,Download} from "lucide-react";
import {getPublicContext} from "@/lib/tenant";
import {formatPrice,contactUrl} from "@/lib/demo";
import {SiteNav} from "@/components/site-nav";
import {ContactForm} from "@/components/contact-form";
export const dynamic="force-dynamic";
export default async function Detail({params}:{params:Promise<{slug:string}>}){
 const {slug}=await params;const {tenant,properties,demo}=await getPublicContext();
 const p=properties.find(x=>x.slug===slug);if(!p)notFound();
 return <><SiteNav tenant={tenant}/><main className="container page-top"><Link href="/propiedades" className="inline-link">← Volver al catálogo</Link><p className="eyebrow">REF. {p.refCode}</p><div className="heading-row"><div><h1>{p.title}</h1><p className="subline"><MapPin size={17}/>{p.neighborhood}, {p.city}</p></div><h2 className="detail-price">{formatPrice(p)}</h2></div><div className="detail-image">{p.image?<img src={p.image} alt={p.title}/>:<span>Fotografía pendiente</span>}</div>{demo&&<p className="demo-note">Ficha ilustrativa: datos y precios de demostración.</p>}<div className="detail-grid"><section><h2>Descripción</h2><p>{p.description}</p><div className="detail-facts"><span><BedDouble/>{p.bedrooms} habitaciones</span><span><Bath/>{p.bathrooms} baños</span><span><Maximize2/>{p.areaM2} m²</span></div><p><strong>Tipo:</strong> {p.type} · <strong>Operación:</strong> {p.operation}</p><a href={`/api/propiedades/${p.slug}/pdf`} className="btn btn-dark"><Download size={17}/> Descargar ficha PDF</a></section><aside className="form-panel"><h3>¿Te interesa esta propiedad?</h3><ContactForm propertyId={p.id}/><a className="btn btn-whatsapp" href={contactUrl(tenant,p)} target={tenant.whatsapp?"_blank":undefined} rel="noopener noreferrer">Consultar por WhatsApp →</a></aside></div></main></>;
}
