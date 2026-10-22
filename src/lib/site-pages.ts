import "server-only";
import {notFound} from "next/navigation";
import {type Prisma} from "@prisma/client";
import {prisma} from "@/lib/prisma";

export type SiteMenuItem={id:string;label:string;href:string};
export type PageDefinition={id:string;slug:string;title:string;href:string;published:boolean;builtIn:boolean;summary:string};
const staticPages=[
 {id:"home",slug:"home",title:"Inicio",href:"/"},
 {id:"propiedades",slug:"propiedades",title:"Propiedades",href:"/propiedades"},
 {id:"nosotros",slug:"nosotros",title:"Nosotros",href:"/nosotros"},
 {id:"servicios",slug:"servicios",title:"Servicios",href:"/servicios"},
 {id:"contacto",slug:"contacto",title:"Contacto",href:"/contacto"}
] as const;
export const DEFAULT_SITE_MENU:SiteMenuItem[]=staticPages.map(p=>({id:p.id,label:p.title,href:p.href}));
export const CUSTOM_PAGE_PREFIX="pagina-";
export const MENU_SLUG="site-navigation";
const customSlug=/^[a-z0-9]+(?:-[a-z0-9]+)*$/;
export function validCustomSlug(slug:string){return slug.length>=3&&slug.length<=65&&customSlug.test(slug)
 &&!["nueva","editar","menu","admin","api","instalar"].includes(slug);}
export function textBlocks(raw:unknown):{summary:string;body:string}{
 if(!raw||typeof raw!=="object"||Array.isArray(raw))return {summary:"",body:""};
 const obj=raw as Record<string,unknown>;
 return {summary:typeof obj.summary==="string"?obj.summary.slice(0,240):"",
  body:typeof obj.body==="string"?obj.body.slice(0,12000):""};
}
export async function getPageDefinitions(tenantId:string):Promise<PageDefinition[]>{
 const rows=await prisma.sitePage.findMany({where:{tenantId,OR:[
  {slug:{in:staticPages.map(x=>x.slug)}},{slug:{startsWith:CUSTOM_PAGE_PREFIX}}
 ]},select:{slug:true,title:true,published:true,blocks:true},orderBy:{updatedAt:"desc"}});
 const lookup=new Map(rows.map(x=>[x.slug,x]));
 const builtins:PageDefinition[]=staticPages.map(p=>({
  ...p,published:p.id==="home"?true:(lookup.get(p.slug)?.published??true),
  builtIn:true,summary:""
 }));
 const extras=rows.filter(r=>r.slug.startsWith(CUSTOM_PAGE_PREFIX)).map(r=>{
  const slug=r.slug.slice(CUSTOM_PAGE_PREFIX.length);
  return {id:"pagina:"+slug,slug,title:r.title,href:"/pagina/"+slug,
   published:r.published,builtIn:false,summary:textBlocks(r.blocks).summary};
 }).filter(x=>validCustomSlug(x.slug));
 return [...builtins,...extras];
}
type MenuRecord={id:string;label:string};
export function normalizedMenu(value:unknown,pages:PageDefinition[]):SiteMenuItem[]{
 const allowed=new Map(pages.filter(p=>p.published).map(p=>[p.id,p]));
 const values=Array.isArray(value)?value:[];
 const used=new Set<string>();
 const result:SiteMenuItem[]=[];
 for(const item of values){
  if(!item||typeof item!=="object"||Array.isArray(item))continue;
  const data=item as Record<string,unknown>;
  const id=typeof data.id==="string"?data.id:"",label=typeof data.label==="string"?data.label.trim():"";
  const p=allowed.get(id);
  if(!p||used.has(id)||label.length<2||label.length>48)continue;
  used.add(id);result.push({id,label,href:p.href});
 }
 return result;
}
export async function getSiteMenu(tenantId:string,existingPages?:PageDefinition[]):Promise<SiteMenuItem[]>{
 const pages=existingPages||await getPageDefinitions(tenantId);
 const stored=await prisma.sitePage.findUnique({where:{tenantId_slug:{tenantId,slug:MENU_SLUG}},select:{blocks:true}});
 if(!stored)return normalizedMenu(DEFAULT_SITE_MENU,pages);
 const blocks=stored.blocks;
 const items=blocks&&typeof blocks==="object"&&!Array.isArray(blocks)
  ?(blocks as {items?:unknown}).items:null;
 return normalizedMenu(items,pages);
}
export async function saveSiteMenu(tenantId:string,items:MenuRecord[]){
 const pages=await getPageDefinitions(tenantId);
 const parsed=normalizedMenu(items,pages);
 if(parsed.length!==items.length)throw new Error("MENU_INVALID");
 if(parsed.length>25)throw new Error("MENU_LIMIT");
 await prisma.sitePage.upsert({
  where:{tenantId_slug:{tenantId,slug:MENU_SLUG}},
  create:{tenantId,slug:MENU_SLUG,title:"Menú principal",blocks:{items:parsed} as Prisma.InputJsonValue,published:true},
  update:{blocks:{items:parsed} as Prisma.InputJsonValue,published:true,title:"Menú principal"}
 });
}
export async function assertPagePublished(tenantId:string,isDemo:boolean,id:string){
 if(isDemo||id==="home")return;
 const row=await prisma.sitePage.findUnique({where:{tenantId_slug:{tenantId,slug:id}},select:{published:true}});
 if(row&&!row.published)notFound();
}
export async function getCustomPage(tenantId:string,slug:string){
 if(!validCustomSlug(slug))return null;
 return prisma.sitePage.findUnique({where:{tenantId_slug:{tenantId,slug:CUSTOM_PAGE_PREFIX+slug}},
  select:{slug:true,title:true,blocks:true,published:true}});
}
