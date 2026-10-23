import {NextRequest,NextResponse} from "next/server";
import {propertyEditor,validMutationOrigin} from "@/lib/admin-property-request";
import {isPhotoId} from "@/lib/property-media";
import {backfillPropertyThumbnails} from "@/lib/property-media-backfill";
export const runtime="nodejs";
export async function POST(req:NextRequest){
 const admin=await propertyEditor();
 if(!admin.canEdit)return NextResponse.json({error:"No autorizado"},{status:403});
 if(!validMutationOrigin(req))return NextResponse.json({error:"Origen no permitido"},{status:403});
 let body:unknown;
 try{body=await req.json();}catch{return NextResponse.json({error:"Solicitud inválida"},{status:400});}
 const cursor=body&&typeof body==="object"&&"cursor" in body?(body as {cursor:unknown}).cursor:null;
 if(cursor!==null&&(typeof cursor!=="string"||!isPhotoId(cursor)))return NextResponse.json({error:"Cursor inválido"},{status:400});
 try{
  const result=await backfillPropertyThumbnails(admin.tenantId,cursor||undefined);
  return NextResponse.json(result,{headers:{"Cache-Control":"no-store"}});
 }catch{
  return NextResponse.json({error:"No fue posible regenerar las miniaturas"},{status:500,headers:{"Cache-Control":"no-store"}});
 }
}
