import "server-only";
import type {Prisma} from "@prisma/client";

export type AgentScope={tenantId:string;userId:string;roles:string[]};
export function managesAllLeads(user:Pick<AgentScope,"roles">){
 return user.roles.includes("admin")||user.roles.includes("editor");
}
/** A commercial agent only sees leads on properties currently assigned to them.
 * Tenant scoping is enforced even when an agent ID is known.
 */
export function leadWhere(user:AgentScope):Prisma.LeadWhereInput{
 return {tenantId:user.tenantId,...(!managesAllLeads(user)?{
  property:{is:{tenantId:user.tenantId,assignedAgentId:user.userId}}
 }:{})};
}
export function propertyWhere(user:AgentScope):Prisma.PropertyWhereInput{
 return {tenantId:user.tenantId,...(!managesAllLeads(user)?{assignedAgentId:user.userId}:{})};
}
