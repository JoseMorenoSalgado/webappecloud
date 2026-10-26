import {readdir} from "node:fs/promises";
import {join} from "node:path";
import {NextResponse} from "next/server";
import {requireAdmin} from "@/lib/auth";
import {getPageDefinitions} from "@/lib/site-pages";
import {configDir} from "@/lib/server-config";

export const runtime="nodejs";
export const dynamic="force-dynamic";
/** Bounded media listing across this tenant's editable pages. No URL from disk data. */
export async function GET(){
 const admin=await requireAdmin();
 if(!admin.roles.some(x=>x==="admin"||x==="editor"))
  return NextResponse.json({error:"Sin permisos."},{status:403});
 const pages=await getPageDefinitions(admin.tenantId);
 const base=join(configDir(),"page-images",admin.tenantId);
 const items:{id:string;slug:string;pageTitle:string;url:string}[]=[];
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
 return NextResponse.json({items},{headers:{"Cache-Control":"private, no-store"}});
}
