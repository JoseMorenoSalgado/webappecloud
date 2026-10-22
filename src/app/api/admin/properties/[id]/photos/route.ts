import {randomUUID} from "node:crypto";
import {mkdir,writeFile,unlink} from "node:fs/promises";
import {dirname} from "node:path";
import {NextRequest,NextResponse} from "next/server";
import sharp from "sharp";
import {prisma} from "@/lib/prisma";
import {MAX_IMAGE_BYTES,MAX_PHOTOS,mediaFilePath} from "@/lib/property-media";
import {propertyEditor,validMutationOrigin} from "@/lib/admin-property-request";

export const runtime="nodejs";
function json(error:string,status:number){return NextResponse.json({error},{status,headers:{"Cache-Control":"no-store"}});}
export async function POST(req:NextRequest,{params}:{params:Promise<{id:string}>}){
 const admin=await propertyEditor();
 if(!admin.canEdit)return json("No autorizado",403);
 if(!validMutationOrigin(req))return json("Origen no permitido",403);
 const length=Number(req.headers.get("content-length")||0);
 if(!length||length>MAX_IMAGE_BYTES+1000000)return json("Archivo demasiado grande (máximo 8 MB).",413);
 const {id}=await params;
 const property=await prisma.property.findFirst({where:{id,tenantId:admin.tenantId},select:{id:true,title:true}});
 if(!property)return json("Inmueble no encontrado.",404);
 let file:FormDataEntryValue|null;
 try{file=(await req.formData()).get("photo");}
 catch{return json("No se pudo leer la imagen.",400);}
 if(!(file instanceof File))return json("Selecciona una fotografía.",400);
 if(file.size<100||file.size>MAX_IMAGE_BYTES)return json("La fotografía debe tener entre 100 bytes y 8 MB.",413);
 if(!["image/jpeg","image/png","image/webp"].includes(file.type))return json("Solo JPG, PNG y WebP.",415);
 // Count and position are reserved inside the same transaction to prevent over-quota writes.
 let output:Buffer;
 try{
  const input=Buffer.from(await file.arrayBuffer());
  const converter=sharp(input,{limitInputPixels:40_000_000,failOn:"error"});
  const meta=await converter.metadata();
  if(!["jpeg","png","webp"].includes(meta.format||""))return json("Formato de imagen no reconocido.",415);
  output=await converter.rotate().resize({width:1920,height:1920,fit:"inside",withoutEnlargement:true}).webp({quality:82,effort:4}).toBuffer();
 }catch{return json("La imagen está dañada o supera las dimensiones permitidas.",422);}
 const photoId=randomUUID();
 const path=mediaFilePath(admin.tenantId,photoId);
 try{
  await mkdir(dirname(path),{recursive:true,mode:0o700});
  await writeFile(path,output,{flag:"wx",mode:0o600});
  await prisma.$transaction(async tx=>{
   // Lock the parent row during the quota check to serialize concurrent uploads for one property.
   if((await tx.property.findFirst({where:{id,tenantId:admin.tenantId},select:{id:true}}))===null)throw Error("MISSING");
   const photoCount=await tx.photo.count({where:{propertyId:id}});
   if(photoCount>=MAX_PHOTOS)throw Error("LIMIT");
   await tx.photo.create({data:{id:photoId,propertyId:id,url:"/api/media/"+photoId,alt:property.title,sortOrder:photoCount}});
  });
  return NextResponse.json({ok:true,id:photoId,url:"/api/media/"+photoId},{status:201,headers:{"Cache-Control":"no-store"}});
 }catch(error){
  try{await unlink(path);}catch{}
  if(error instanceof Error&&error.message==="LIMIT")return json("Máximo de 15 fotografías por inmueble.",409);
  if(error instanceof Error&&error.message==="MISSING")return json("Inmueble no encontrado.",404);
  console.error("[property-media] upload",error instanceof Error?error.name:"unknown");
  return json("No se pudo guardar la fotografía.",500);
 }
}
