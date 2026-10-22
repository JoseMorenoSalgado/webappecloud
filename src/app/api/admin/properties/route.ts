import {NextRequest} from "next/server";
import {prisma} from "@/lib/prisma";
import {parseProperty,propertySlug,PropertyFormError} from "@/lib/property-form";
import {propertyRedirect,validMutationOrigin,propertyEditor,propertyErrorPath} from "@/lib/admin-property-request";
export const runtime="nodejs";
export async function POST(req:NextRequest){
 const admin=await propertyEditor();
 if(!admin.canEdit)return propertyRedirect("/admin/propiedades?error=permisos");
 if(!validMutationOrigin(req))return propertyRedirect(propertyErrorPath("origen"));
 if(Number(req.headers.get("content-length")||0)>28000)return propertyRedirect(propertyErrorPath("datos"));
 try{
  const form=await req.formData();
  const data=parseProperty(form);
  const property=await prisma.property.create({data:{
   ...data,slug:propertySlug(data.title,data.refCode),tenantId:admin.tenantId
  }});
  return propertyRedirect("/admin/propiedades/"+encodeURIComponent(property.id)+"/editar?created=1");
 }catch(error){
  if(error instanceof PropertyFormError)return propertyRedirect(propertyErrorPath(error.code));
  if(error&&typeof error==="object"&&"code" in error&&error.code==="P2002")return propertyRedirect(propertyErrorPath("duplicado"));
  console.error("[properties] create failed",error instanceof Error?error.name:"unknown");
  return propertyRedirect(propertyErrorPath("servidor"));
 }
}
