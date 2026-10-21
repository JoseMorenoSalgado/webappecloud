import {headers} from "next/headers";
import {notFound,redirect} from "next/navigation";
import {hasDatabaseConfig} from "@/lib/server-config";
import {isPreviewHost,normalizeSiteHost} from "@/lib/preview";
import {prisma} from "@/lib/prisma";
import {demoTenant,demoProperties,type PublicTenant,type PublicProperty} from "@/lib/demo";
export const canonicalHost = normalizeSiteHost;
export async function tenantFromHost(hostname:string){
 if(!hasDatabaseConfig())return null;
 const d=await prisma.domain.findUnique({where:{hostname:canonicalHost(hostname)},include:{tenant:true}});
 return d?.tenant.active?d:null;
}
export async function getPublicContext():Promise<{tenant:PublicTenant;properties:PublicProperty[];demo:boolean}>{
 const h=await headers();
 const hostname=h.get("host")||"";
 const preview=isPreviewHost(hostname);
 const demoContext={tenant:demoTenant,properties:demoProperties,demo:true} as const;
 if(!hasDatabaseConfig()){
   if(preview)return demoContext;
   if(process.env.NODE_ENV!=="production")return demoContext;
   redirect("/instalar");
 }
 let d:Awaited<ReturnType<typeof tenantFromHost>>;
 try{d=await tenantFromHost(hostname);}
 catch(error){
   if(!preview)throw error;
   console.warn("Preview mode: database not ready");
   return demoContext;
 }
 if(!d){
   if(preview)return demoContext;
   notFound();
 }
 const props=await prisma.property.findMany({where:{tenantId:d.tenantId,published:true},include:{photos:{orderBy:{sortOrder:"asc"}}},orderBy:{createdAt:"desc"}});
 return {tenant:{id:d.tenant.id,name:d.tenant.name,tagline:d.tenant.tagline||"",whatsapp:d.tenant.whatsapp||"",logoUrl:d.tenant.logoUrl||""},demo:false,properties:props.map(p=>({
 id:p.id,slug:p.slug,refCode:p.refCode,title:p.title,description:p.description,city:p.city,neighborhood:p.neighborhood||"",type:p.type,
 operation:p.operation==="alquiler"?"alquiler" as const:"venta" as const,price:Number(p.price),
 bedrooms:p.bedrooms||0,bathrooms:p.bathrooms||0,areaM2:p.areaM2||0,image:p.photos[0]?.url||"",photos:p.photos.map(x=>x.url)
 }))};
}
