import {notFound} from "next/navigation";
import type {Metadata} from "next";
import Link from "next/link";
import {ArrowUpRight} from "lucide-react";
import {getPublicContext} from "@/lib/tenant";
import {getCustomPage,textBlocks,validCustomSlug} from "@/lib/site-pages";
import {PublicFrame} from "@/components/public-frame";
export const dynamic="force-dynamic";
export async function generateMetadata({params}:{params:Promise<{slug:string}>}):Promise<Metadata>{
 const {slug}=await params;
 if(!validCustomSlug(slug))return {};
 const {tenant,demo}=await getPublicContext();
 if(demo)return {};
 const page=await getCustomPage(tenant.id,slug);
 if(!page||!page.published)return {};
 return {title:page.title,description:textBlocks(page.blocks).summary.slice(0,160)};
}
export default async function CustomPublicPage({params}:{params:Promise<{slug:string}>}){
 const {slug}=await params;
 if(!validCustomSlug(slug))notFound();
 const {tenant,demo}=await getPublicContext();
 if(demo)notFound();
 const page=await getCustomPage(tenant.id,slug);
 if(!page||!page.published)notFound();
 const blocks=textBlocks(page.blocks);
 const paragraphs=blocks.body.split(/\n\s*\n/).map(x=>x.trim()).filter(Boolean);
 return <PublicFrame tenant={tenant} demo={false}><main id="contenido">
  <section className="nexus-inner-hero"><div className="container"><span className="nexus-section-kicker nexus-kicker-light">INFORMACIÓN</span>
   <h1>{page.title}</h1>{blocks.summary&&<p>{blocks.summary}</p>}</div></section>
  <section className="nexus-section"><div className="container site-custom-public-content">
   {paragraphs.length?paragraphs.map((p,i)=><p key={i}>{p}</p>):<p>Información disponible próximamente.</p>}
   <Link href="/contacto" className="nexus-text-link">¿Tienes alguna pregunta? Contáctanos <ArrowUpRight size={18}/></Link>
  </div></section>
 </main></PublicFrame>;
}
