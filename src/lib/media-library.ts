import "server-only";
import {readdir} from "node:fs/promises";
import {join} from "node:path";
import {getPageDefinitions} from "@/lib/site-pages";
import {configDir} from "@/lib/server-config";

export type TenantMedia={id:string;slug:string;pageTitle:string;url:string};
/** Read-only, bounded listing of images stored by one tenant; never includes another tenant's paths. */
export async function listTenantMedia(tenantId:string):Promise<TenantMedia[]>{
 const pages=await getPageDefinitions(tenantId);
 const base=join(configDir(),"page-images",tenantId);
 const items:TenantMedia[]=[];
 for(const page of pages.slice(0,150)){
  if(items.length>=120)break;
  let filenames:string[];
  try{filenames=await readdir(join(base,page.slug));}catch{continue;}
  for(const filename of filenames.slice(0,60)){
   const match=/^([a-f0-9]{32})\.webp$/.exec(filename);
   if(!match)continue;
   const id=match[1];
   items.push({id,slug:page.slug,pageTitle:page.title,
    url:"/api/admin/site-pages/"+encodeURIComponent(page.slug)+"/images/"+id});
   if(items.length>=120)break;
  }
 }
 return items;
}
