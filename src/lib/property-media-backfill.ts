import "server-only";
import {access,readFile,writeFile} from "node:fs/promises";
import sharp from "sharp";
import {prisma} from "@/lib/prisma";
import {mediaFilePath,mediaVariantPath,type MediaVariant} from "@/lib/property-media";

const variants:readonly MediaVariant[]=["card","thumb"];
/** Manual tenant-limited maintenance task. Run one small batch per request. */
export async function backfillPropertyThumbnails(tenantId:string,cursor?:string){
 const batchSize=5;
 const properties=await prisma.photo.findMany({
  where:{property:{tenantId}},
  select:{id:true,url:true},
  orderBy:{id:"asc"},take:batchSize,
  ...(cursor?{cursor:{id:cursor},skip:1}:{})
 });
 let created=0,skipped=0,failed=0;
 for(const photo of properties){
  if(photo.url!=="/api/media/"+photo.id){skipped++;continue;}
  const pending:MediaVariant[]=[];
  for(const variant of variants){
   try{await access(mediaVariantPath(tenantId,photo.id,variant));}
   catch{pending.push(variant);}
  }
  if(!pending.length){skipped++;continue;}
  let input:Buffer;
  try{input=await readFile(mediaFilePath(tenantId,photo.id));}
  catch{failed++;continue;}
  for(const variant of pending){
   const target=mediaVariantPath(tenantId,photo.id,variant);
   try{
    const width=variant==="card"?900:240,quality=variant==="card"?78:72;
    const output=await sharp(input,{limitInputPixels:40_000_000}).resize({width,height:width,fit:"inside",withoutEnlargement:true}).webp({quality,effort:3}).toBuffer();
    await writeFile(target,output,{flag:"wx",mode:0o600});
    created++;
   }catch(error){
    if((error as NodeJS.ErrnoException).code==="EEXIST")skipped++;
    else failed++;
   }
  }
 }
 return {created,skipped,failed,scanned:properties.length,batchSize,nextCursor:properties.length===batchSize?properties[properties.length-1].id:null};
}
