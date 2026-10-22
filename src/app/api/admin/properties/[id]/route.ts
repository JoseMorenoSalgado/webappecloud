import {NextRequest} from "next/server";
import {prisma} from "@/lib/prisma";
import {parseProperty,PropertyFormError} from "@/lib/property-form";
import {propertyRedirect,validMutationOrigin,propertyEditor,propertyErrorPath} from "@/lib/admin-property-request";
import {unlink} from "node:fs/promises";
import {mediaFilePath} from "@/lib/property-media";
export const runtime="nodejs";
export async function POST(req:NextRequest,{params}:{params:Promise<{id:string}>}){
 const {id}=await params;
 const admin=await propertyEditor();
 if(!admin.canEdit)return propertyRedirect("/admin/propiedades?error=permisos");
 if(!validMutationOrigin(req))return propertyRedirect(propertyErrorPath("origen",id));
 if(Number(req.headers.get("content-length")||0)>28000)return propertyRedirect(propertyErrorPath("datos",id));
 const property=await prisma.property.findFirst({where:{id,tenantId:admin.tenantId},include:{photos:{select:{id:true,url:true}}}});
 if(!property)return propertyRedirect("/admin/propiedades?error=noexiste");
 try{
  const form=await req.formData();
  const intent=String(form.get("intent")||"save");
  if(intent==="publish"||intent==="unpublish"){
   await prisma.property.update({where:{id:property.id},data:{published:intent==="publish"}});
   return propertyRedirect("/admin/propiedades?updated=1");
  }
  if(intent==="delete"){
   if(!admin.canDelete||form.get("confirmation")!==property.refCode)return propertyRedirect(propertyErrorPath("confirmacion",id));
   await prisma.property.delete({where:{id:property.id}});
   await Promise.all(property.photos.filter(p=>p.url==="/api/media/"+p.id).map(async p=>{
    try{await unlink(mediaFilePath(admin.tenantId,p.id));}catch{/* Stale files can be swept by storage maintenance */ }
   }));
   return propertyRedirect("/admin/propiedades?deleted=1");
  }
  if(intent!=="save")return propertyRedirect(propertyErrorPath("datos",id));
  const data=parseProperty(form);
  await prisma.property.update({where:{id:property.id},data});
  return propertyRedirect("/admin/propiedades/"+encodeURIComponent(id)+"/editar?saved=1");
 }catch(error){
  if(error instanceof PropertyFormError)return propertyRedirect(propertyErrorPath(error.code,id));
  if(error&&typeof error==="object"&&"code" in error&&error.code==="P2002")return propertyRedirect(propertyErrorPath("duplicado",id));
  console.error("[properties] change failed",error instanceof Error?error.name:"unknown");
  return propertyRedirect(propertyErrorPath("servidor",id));
 }
}
