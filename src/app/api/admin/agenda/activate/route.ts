import {NextRequest} from "next/server";
import {requireAdmin} from "@/lib/auth";
import {activateAgendaTable} from "@/lib/agenda-schema";
import {propertyRedirect,validMutationOrigin} from "@/lib/admin-property-request";
export const runtime="nodejs";
export async function POST(req:NextRequest){
 const admin=await requireAdmin();
 if(!admin.roles.includes("admin"))return propertyRedirect("/admin/agenda?error=permisos");
 if(!validMutationOrigin(req))return propertyRedirect("/admin/agenda?error=origen");
 try{
  await activateAgendaTable();
  return propertyRedirect("/admin/agenda?activated=1");
 }catch(error){
  console.error("[agenda] activation failed",error instanceof Error?error.name:"unknown");
  return propertyRedirect("/admin/agenda?error=activacion");
 }
}
