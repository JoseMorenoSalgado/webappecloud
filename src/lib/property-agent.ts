import "server-only";
import {prisma} from "@/lib/prisma";
import {parseRoles} from "@/lib/roles";
export async function validateAssignedAgent(tenantId:string,raw:FormDataEntryValue|null){
 if(typeof raw!=="string"||raw.length>100)throw Error("AGENT_INVALID");
 if(!raw)return null;
 const agent=await prisma.adminUser.findFirst({where:{id:raw,tenantId,active:true},select:{role:true}});
 if(!agent||!parseRoles(agent.role).includes("agente"))throw Error("AGENT_INVALID");
 return raw;
}
