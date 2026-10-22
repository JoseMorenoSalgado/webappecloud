import Link from "next/link";
import {notFound} from "next/navigation";
import {MapPin,BedDouble,Bath,Maximize2,Download} from "lucide-react";
import {getPublicContext} from "@/lib/tenant";
import {formatPrice,contactUrl} from "@/lib/demo";
import {SiteNav} from "@/components/site-nav";
import {ContactForm} from "@/components/contact-form";
import {PreviewBanner} from "@/components/preview-banner";
import {PropertyGallery} from "@/components/property-gallery";
import {hasCustomPdf} from "@/lib/property-document";
import {PageView,WhatsAppButton,FloatingWhatsapp} from "@/components/traffic-events";
export const dynamic="force-dynamic";
export default async function Detail({params}:{params:Promise<{slug:string}>}){
 const {slug}=await params;const {tenant,properties,demo}=await getPublicContext();
 const p=properties.find(x=>x.slug===slug);if(!p)notFound();
 const customPdfAvailable=!demo&&await hasCustomPdf(tenant.id,p.id);
 return <><SiteNav tenant={tenant}/>{demo?<PreviewBanner/>:<PageView/>}<main className="container page-top"><Link href="/propiedades" className="inline-link">← Volver al catálogo</Link><p className="eyebrow">REF. {p.refCode}</p><div className="heading-row"><div><h1>{p.title}</h1><p className="subline"><MapPin size={17}/>{p.neighborhood}, {p.city}</p></div><h2 className="detail-price">{formatPrice(p)}</h2></div><PropertyGallery title={p.title} photos={p.photos.length?p.photos:p.image?[p.image]:[]}/>{demo&&<p className="demo-note">Ficha ilustrativa: datos y precios de demostración.</p>}<div className="detail-grid"><section><h2>Descripción</h2><p>{p.description}</p><div className="detail-facts"><span><BedDouble/>{p.bedrooms} habitaciones</span><span><Bath/>{p.bathrooms} baños</span><span><Maximize2/>{p.areaM2} m²</span></div><p><strong>Tipo:</strong> {p.type} · <strong>Operación:</strong> {p.operation}</p><div className="property-pdf-downloads">
  <a href={`/api/propiedades/${p.slug}/pdf`} className="btn btn-dark"><Download size={17}/> Descargar ficha automática</a>
  {customPdfAvailable&&<a href={`/api/propiedades/${p.slug}/pdf-personalizado`} className="btn btn-red"><Download size={17}/> Descargar ficha de NEXUS</a>}
 </div></section><aside className="form-panel"><h3>¿Te interesa esta propiedad?</h3>{demo?<p className="preview-form-note">Esta propiedad es de demostración. La captación de contactos se habilitará al conectar MariaDB.</p>:<><ContactForm propertyId={p.id}/><WhatsAppButton tenant={tenant} property={p}/></>}</aside></div></main><FloatingWhatsapp tenant={tenant} property={p}/></>;
}
