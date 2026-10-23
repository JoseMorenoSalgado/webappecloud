import "server-only";
import {prisma} from "@/lib/prisma";
import {parseRoles} from "@/lib/roles";

/** Best-effort, tenant-scoped load-balanced assignment for unowned web inquiries.
 * Does not override explicit property ownership or change existing leads.
 */
export async function selectFallbackLeadAgent(tenantId:string):Promise<string|null>{
 const users=await prisma.adminUser.findMany({
  where:{tenantId,active:true},
  select:{id:true,role:true},
  orderBy:{id:"asc"}
 });
 const agents=users.filter(user=>parseRoles(user.role).includes("agente"));
 if(!agents.length)return null;
 const counts=await prisma.lead.groupBy({
  by:["assignedAgentId"],where:{tenantId,assignedAgentId:{in:agents.map(agent=>agent.id)}},
  _count:{_all:true}
 });
 const byId=new Map(counts.map(row=>[row.assignedAgentId,row._count._all]));
 agents.sort((a,b)=>(byId.get(a.id)||0)-(byId.get(b.id)||0)||a.id.localeCompare(b.id));
 return agents[0].id;
}
