import {readFile} from "node:fs/promises";
import {NextRequest,NextResponse} from "next/server";
import {prisma} from "@/lib/prisma";
import {currentAdmin} from "@/lib/auth";
import {tenantFromHost} from "@/lib/tenant";
import {mediaFilePath,isPhotoId} from "@/lib/property-media";
export const runtime="nodejs";
export const dynamic="force-dynamic";
export async function GET(req:NextRequest,{params}:{params:Promise<{id:string}>}){
 const {id}=await params;
 if(!isPhotoId(id))return new NextResponse(null,{status:404});
 const tenant=await tenantFromHost(req.headers.get("host")||"");
 if(!tenant)return new NextResponse(null,{status:404});
 const photo=await prisma.photo.findFirst({where:{id,property:{tenantId:tenant.tenantId}},select:{id:true,url:true,property:{select:{published:true}}}});
 if(!photo||photo.url!=="/api/media/"+id)return new NextResponse(null,{status:404});
 if(!photo.property.published){
  const admin=await currentAdmin();
  if(!admin||admin.tenantId!==tenant.tenantId)return new NextResponse(null,{status:404});
 }
 try{
  const buffer=await readFile(mediaFilePath(tenant.tenantId,id));
  return new NextResponse(new Uint8Array(buffer),{headers:{
   "Content-Type":"image/webp","X-Content-Type-Options":"nosniff",
   "Cache-Control":"private, no-store","Content-Disposition":"inline"
  }});
 }catch{return new NextResponse(null,{status:404});}
}
