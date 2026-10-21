import {headers} from "next/headers";
import {notFound} from "next/navigation";
import {prisma} from "@/lib/prisma";
import {demoTenant,demoProperties,type PublicTenant,type PublicProperty} from "@/lib/demo";
export function canonicalHost(raw:string){return raw.toLowerCase().trim().replace(/:\d+$/,"").replace(/\.$/,"");}
export async function tenantFromHost(hostname:string){
 if(!process.env.DATABASE_URL)return null;
 const d=await prisma.domain.findUnique({where:{hostname:canonicalHost(hostname)},include:{tenant:true}});
 return d?.tenant.active?d:null;
}
export async function getPublicContext():Promise<{tenant:PublicTenant;properties:PublicProperty[];demo:boolean}>{
 if(!process.env.DATABASE_URL){
   if(process.env.NODE_ENV==="production")notFound();
   return {tenant:demoTenant,properties:demoProperties,demo:true};
 }
 const h=await headers();const d=await tenantFromHost(h.get("host")||"");
 if(!d)notFound();
 const props=await prisma.property.findMany({where:{tenantId:d.tenantId,published:true},include:{photos:{orderBy:{sortOrder:"asc"}}},orderBy:{createdAt:"desc"}});
 return {tenant:{id:d.tenant.id,name:d.tenant.name,tagline:d.tenant.tagline||"",whatsapp:d.tenant.whatsapp||"",logoUrl:d.tenant.logoUrl||""},demo:false,properties:props.map(p=>({
 id:p.id,slug:p.slug,refCode:p.refCode,title:p.title,description:p.description,city:p.city,neighborhood:p.neighborhood||"",type:p.type,
 operation:p.operation==="alquiler"?"alquiler" as const:"venta" as const,price:Number(p.price),
 bedrooms:p.bedrooms||0,bathrooms:p.bathrooms||0,areaM2:p.areaM2||0,image:p.photos[0]?.url||"",photos:p.photos.map(x=>x.url)
 }))};
}
