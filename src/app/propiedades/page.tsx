import Link from "next/link";
import {getPublicContext} from "@/lib/tenant";
import {SiteNav} from "@/components/site-nav";
import {PropertyCard} from "@/components/property-card";
import {PreviewBanner} from "@/components/preview-banner";
import {PageView,FloatingWhatsapp} from "@/components/traffic-events";
export const dynamic="force-dynamic";
type Filters={operacion?:string;tipo?:string;ubicacion?:string};
export default async function Catalog({searchParams}:{searchParams:Promise<Filters>}){
 const f=await searchParams;const {tenant,properties,demo}=await getPublicContext();
 const matching=properties.filter(p=>(!f.operacion||p.operation===f.operacion)&&(!f.tipo||p.type.toLowerCase()===f.tipo.toLowerCase())&&(!f.ubicacion||`${p.city} ${p.neighborhood}`.toLowerCase().includes(f.ubicacion.toLowerCase())));
 return <><SiteNav tenant={tenant}/>{demo?<PreviewBanner/>:<PageView/>}<main><div className="container page-top"><Link href="/" className="inline-link">← Inicio</Link><p className="eyebrow">EXPLORA TUS OPCIONES</p><h1>Catálogo de propiedades</h1><p>Encuentra la propiedad que se adapta a tus planes.</p><form className="catalog-filters" method="get"><select name="operacion" defaultValue={f.operacion||""}><option value="">Todas las operaciones</option><option value="venta">En venta</option><option value="alquiler">En alquiler</option></select><select name="tipo" defaultValue={f.tipo||""}><option value="">Todos los tipos</option><option value="Casa">Casa</option><option value="Apartamento">Apartamento</option><option value="Terreno">Terreno</option></select><input name="ubicacion" placeholder="Ciudad o zona" defaultValue={f.ubicacion||""}/><button className="btn btn-red">Filtrar</button></form><p>{matching.length} propiedades encontradas {demo?"(demostración)":""}</p><div className="property-grid">{matching.map(p=><PropertyCard key={p.id} property={p}/>)}</div>{matching.length===0&&<p>Sin propiedades que coincidan con la búsqueda.</p>}</div></main><FloatingWhatsapp tenant={tenant}/></>;
}
