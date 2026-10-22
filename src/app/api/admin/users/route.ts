import {NextRequest,NextResponse} from "next/server";
import {prisma} from "@/lib/prisma";
import {currentAdmin,hashPassword} from "@/lib/auth";
import {isUserRole} from "@/lib/roles";
import {validMutationOrigin} from "@/lib/admin-property-request";
export const runtime="nodejs";
function redirect(req:NextRequest,status:string){return NextResponse.redirect(new URL("/admin/usuarios?status="+status,req.url),303);}
export async function POST(req:NextRequest){
 const admin=await currentAdmin();
 if(!admin||admin.role!=="admin")return new NextResponse("No autorizado",{status:403});
 if(!validMutationOrigin(req))return new NextResponse("Origen no autorizado",{status:403});
 const size=Number(req.headers.get("content-length")||0);
 if(!Number.isSafeInteger(size)||size>10000)return redirect(req,"datos");
 let form:FormData;
 try{form=await req.formData();}catch{return redirect(req,"datos");}
 const intent=String(form.get("intent")||"");
 if(intent==="create"){
  const name=String(form.get("name")||"").trim().slice(0,120);
  const email=String(form.get("email")||"").trim().toLowerCase().slice(0,180);
  const password=String(form.get("password")||"");
  const role=String(form.get("role")||"");
  if(name.length<2||!/^\S+@\S+\.\S+$/.test(email)||password.length<12||password.length>256||!isUserRole(role))return redirect(req,"datos");
  try{await prisma.adminUser.create({data:{tenantId:admin.tenantId,name,email,role,passwordHash:hashPassword(password),active:true}});}
  catch{return redirect(req,"duplicado");}
  return redirect(req,"creado");
 }
 if(intent==="role"||intent==="toggle"){
  const id=String(form.get("id")||"");
  if(!id||id.length>100)return redirect(req,"datos");
  const target=await prisma.adminUser.findFirst({where:{id,tenantId:admin.tenantId}});
  if(!target)return redirect(req,"datos");
  if(target.id===admin.userId)return redirect(req,"propia");
  if(target.role==="admin"){
   const admins=await prisma.adminUser.count({where:{tenantId:admin.tenantId,role:"admin",active:true}});
   if(admins<=1)return redirect(req,"ultimo");
  }
  if(intent==="role"){
   const role=String(form.get("role")||"");
   if(!isUserRole(role))return redirect(req,"datos");
   await prisma.adminUser.update({where:{id:target.id},data:{role}});
  }else{
   await prisma.adminUser.update({where:{id:target.id},data:{active:!target.active}});
  }
  await prisma.adminSession.deleteMany({where:{userId:target.id}});
  return redirect(req,"guardado");
 }
 return redirect(req,"datos");
}
