import {NextRequest} from "next/server";
import {prisma} from "@/lib/prisma";
import {requireAdmin} from "@/lib/auth";
import {propertyRedirect,validMutationOrigin} from "@/lib/admin-property-request";
import {CUSTOM_PAGE_PREFIX,validCustomSlug} from "@/lib/site-pages";
import {type Prisma} from "@prisma/client";
export const runtime="nodejs";
const builtins=["propiedades","nosotros","servicios","contacto"];
export async function POST(req:NextRequest,{params}:{params:Promise<{slug:string}>}){
 const admin=await requireAdmin();
 const {slug}=await params;
 if(!["admin","editor"].includes(admin.role))return propertyRedirect("/admin/personalizar/paginas?error=permisos");
 if(!validMutationOrigin(req))return propertyRedirect("/admin/personalizar/paginas?error=origen");
 const size=Number(req.headers.get("content-length")||0);
 if(!Number.isSafeInteger(size)||size<1||size>22000)return propertyRedirect("/admin/personalizar/paginas?error=datos");
 if(!builtins.includes(slug)&&!validCustomSlug(slug))return propertyRedirect("/admin/personalizar/paginas?error=datos");
 let form:FormData;
 try{form=await req.formData();}catch{return propertyRedirect("/admin/personalizar/paginas?error=datos");}
 const published=form.get("published")==="on";
 const builtin=builtins.includes(slug);
 const dbSlug=builtin?slug:CUSTOM_PAGE_PREFIX+slug;
 const destination=builtin?"/admin/personalizar/paginas":"/admin/personalizar/paginas/"+encodeURIComponent(slug);
 try{
  if(builtin){
   const titles:Record<string,string>={propiedades:"Propiedades",nosotros:"Nosotros",servicios:"Servicios",contacto:"Contacto"};
   await prisma.sitePage.upsert({
    where:{tenantId_slug:{tenantId:admin.tenantId,slug:dbSlug}},
    create:{tenantId:admin.tenantId,slug:dbSlug,title:titles[slug],blocks:{} as Prisma.InputJsonValue,published},
    update:{published}
   });
  }else{
   const existing=await prisma.sitePage.findUnique({where:{tenantId_slug:{tenantId:admin.tenantId,slug:dbSlug}},select:{id:true}});
   if(!existing)return propertyRedirect("/admin/personalizar/paginas?error=noexiste");
   if(form.get("intent")==="status"){
    await prisma.sitePage.update({where:{tenantId_slug:{tenantId:admin.tenantId,slug:dbSlug}},data:{published}});
    return propertyRedirect("/admin/personalizar/paginas?saved=1");
   }
   const title=form.get("title"),summary=form.get("summary"),body=form.get("body");
   if(typeof title!=="string"||title.trim().length<3||title.length>90||
    typeof summary!=="string"||summary.length>240||typeof body!=="string"||body.length>10000)
    return propertyRedirect(destination+"?error=datos");
   await prisma.sitePage.update({
    where:{tenantId_slug:{tenantId:admin.tenantId,slug:dbSlug}},
    data:{title:title.trim(),published,blocks:{summary:summary.trim(),body:body.trim()} as Prisma.InputJsonValue}
   });
  }
  return propertyRedirect(destination+"?saved=1");
 }catch(e){
  console.error("[site-pages] update",e instanceof Error?e.name:"unknown");
  return propertyRedirect(destination+"?error=servidor");
 }
}
