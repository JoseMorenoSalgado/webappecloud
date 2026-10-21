import {hasDatabaseConfig} from "@/lib/server-config";
import {NextResponse,type NextRequest} from "next/server";
import {prisma} from "@/lib/prisma";
import {tenantFromHost} from "@/lib/tenant";
export async function POST(req:NextRequest){
 if(!hasDatabaseConfig())return NextResponse.json({error:"Formulario disponible cuando se configure la base de datos."},{status:503});
 const origin=req.headers.get("origin");if(origin){try{if(new URL(origin).host!==req.headers.get("host"))return NextResponse.json({error:"Origen no autorizado"},{status:403});}catch{return NextResponse.json({error:"Origen inválido"},{status:403});}}
 let b:Record<string,unknown>;try{b=await req.json();}catch{return NextResponse.json({error:"Datos inválidos"},{status:400});}
 if(b.website)return NextResponse.json({ok:true});
 const name=String(b.name||"").trim().slice(0,120),phone=String(b.phone||"").trim().slice(0,30),email=String(b.email||"").trim().slice(0,180),message=String(b.message||"").trim().slice(0,2000);
 if(name.length<2||!/^[+\d() -]{7,30}$/.test(phone)||b.consent!=="yes"||(email&&!/^\S+@\S+\.\S+$/.test(email)))return NextResponse.json({error:"Revisa tus datos y acepta el consentimiento de contacto."},{status:400});
 const tenant=await tenantFromHost(req.headers.get("host")||"");if(!tenant)return NextResponse.json({error:"Sitio no encontrado"},{status:404});
 let propertyId:string|undefined;
 if(typeof b.propertyId==="string"&&b.propertyId){
  const p=await prisma.property.findFirst({where:{id:b.propertyId,tenantId:tenant.tenantId,published:true}});if(!p)return NextResponse.json({error:"Propiedad no encontrada"},{status:400});propertyId=p.id;
 }
 await prisma.lead.create({data:{tenantId:tenant.tenantId,propertyId,name,phone,email:email||null,message:message||null,source:"web",consent:true}});
 return NextResponse.json({ok:true},{status:201});
}
