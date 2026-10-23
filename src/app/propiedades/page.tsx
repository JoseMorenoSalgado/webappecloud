import Link from "next/link";
import type {Metadata} from "next";
import {ArrowUpRight,Search,SlidersHorizontal,Building2} from "lucide-react";
import {getPublicContext} from "@/lib/tenant";
import {getCatalogPage,CATALOG_PAGE_SIZE} from "@/lib/catalog";
import {assertPagePublished} from "@/lib/site-pages";
import {PublicFrame} from "@/components/public-frame";
import {PropertyCard} from "@/components/property-card";
export const dynamic="force-dynamic";
export const metadata:Metadata={title:"Propiedades",description:"Explora inmuebles disponibles para compra o alquiler en Nicaragua."};
type Filters={operacion?:string;tipo?:string;ubicacion?:string;pagina?:string};
export default async function Catalog({searchParams}:{searchParams:Promise<Filters>}){
 const raw=await searchParams;
 const f={operacion:(raw.operacion||"").slice(0,20),tipo:(raw.tipo||"").slice(0,40),ubicacion:(raw.ubicacion||"").slice(0,90)};
 const {tenant,properties:demoProperties,demo}=await getPublicContext({loadProperties:false});
 await assertPagePublished(tenant.id,demo,"propiedades");
 const requestedPage=/^[1-9]\d{0,5}$/.test(raw.pagina||"")?Number(raw.pagina):1;
 const normalizedLocation=f.ubicacion.toLocaleLowerCase();
 const normalizedType=f.tipo.toLocaleLowerCase();
 const demoMatching=demoProperties.filter(p=>
  (!f.operacion||p.operation===f.operacion)&&
  (!f.tipo||p.type.toLowerCase()===normalizedType)&&
  (!f.ubicacion||`${p.city} ${p.neighborhood}`.toLowerCase().includes(normalizedLocation))
 );
 const demoTotalPages=Math.max(1,Math.ceil(demoMatching.length/CATALOG_PAGE_SIZE));
 const demoPage=Math.min(requestedPage,demoTotalPages);
 const result=demo?{properties:demoMatching.slice((demoPage-1)*CATALOG_PAGE_SIZE,demoPage*CATALOG_PAGE_SIZE),count:demoMatching.length,currentPage:demoPage,totalPages:demoTotalPages}:await getCatalogPage(tenant.id,f,requestedPage);
 const matching=result.properties;
 const pageHref=(page:number)=>"/propiedades?"+new URLSearchParams({...Object.fromEntries(Object.entries(f).filter(([,v])=>v)),pagina:String(page)}).toString();
 return <PublicFrame tenant={tenant} demo={demo}><main id="contenido">
  <section className="nexus-inner-hero nexus-catalog-hero"><div className="container">
   <span className="nexus-section-kicker nexus-kicker-light">CATÁLOGO INMOBILIARIO</span>
   <div className="nexus-inner-hero-row"><div>
    <h1>Propiedades para <em>cada nueva etapa.</em></h1>
    <p>Explora viviendas, terrenos y apartamentos disponibles para comprar o alquilar.</p>
   </div><div className="nexus-inner-label">EXPLORA TU PRÓXIMO ESPACIO</div></div>
  </div></section>
  <section className="nexus-section nexus-catalog-section"><div className="container">
   <form className="nexus-catalog-filter-card" method="get" action="/propiedades">
    <div className="nexus-catalog-filter-heading"><SlidersHorizontal size={20}/><strong>Filtrar propiedades</strong></div>
    <div className="nexus-search-form">
     <label>Operación<select name="operacion" defaultValue={f.operacion}><option value="">Venta o alquiler</option><option value="venta">En venta</option><option value="alquiler">En alquiler</option></select></label>
     <label>Tipo de propiedad<select name="tipo" defaultValue={f.tipo}><option value="">Todos los tipos</option><option value="Casa">Casa</option><option value="Apartamento">Apartamento</option><option value="Terreno">Terreno</option></select></label>
     <label>Ciudad o zona<input name="ubicacion" defaultValue={f.ubicacion} maxLength={90} placeholder="Buscar ubicación"/></label>
     <button className="btn btn-red" type="submit"><Search size={17}/> Aplicar filtros</button>
    </div>
   </form>
   <div className="nexus-catalog-results"><div>
    <span className="nexus-section-kicker">DESCUBRE TU PRÓXIMO INMUEBLE</span>
    <h2>Propiedades <em>disponibles.</em></h2></div>
    <span className="nexus-catalog-count">{result.count} {result.count===1?"propiedad":"propiedades"} {demo?"(muestra)":""}</span>
   </div>
   {matching.length?<div className="property-grid nexus-featured-grid">{matching.map(p=><PropertyCard key={p.id} property={p}/>)}</div>:
   <div className="nexus-empty-listing"><Building2 size={33}/><h3>Sin propiedades que coincidan</h3><p>Prueba con otra ubicación o revisa todo el catálogo.</p><Link href="/propiedades" className="nexus-text-link">Ver todas las propiedades <ArrowUpRight size={17}/></Link></div>}
   {result.totalPages>1&&<nav aria-label="Páginas del catálogo" style={{display:"flex",gap:12,justifyContent:"center",alignItems:"center",marginTop:32}}>
    {result.currentPage>1&&<Link className="btn" href={pageHref(result.currentPage-1)}>Anterior</Link>}
    <span>Página {result.currentPage} de {result.totalPages}</span>
    {result.currentPage<result.totalPages&&<Link className="btn" href={pageHref(result.currentPage+1)}>Siguiente</Link>}
   </nav>}
  </div></section>
 </main></PublicFrame>;
}
