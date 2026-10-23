import "server-only";
import type {Prisma} from "@prisma/client";
import {leadWhere,type AgentScope} from "@/lib/agent-scope";

export const AGENDA_TYPES=[
 {value:"visita",label:"Visita al inmueble"},
 {value:"llamada",label:"Llamada de seguimiento"},
 {value:"reunion",label:"Reunión comercial"}
] as const;
export const AGENDA_STATUSES=[
 {value:"pendiente",label:"Pendiente"},
 {value:"realizada",label:"Realizada"},
 {value:"cancelada",label:"Cancelada"}
] as const;
export const AGENDA_DURATIONS=[15,30,45,60,90,120,180] as const;
export type AgendaType=typeof AGENDA_TYPES[number]["value"];
export type AgendaStatus=typeof AGENDA_STATUSES[number]["value"];
export function isAgendaType(v:string):v is AgendaType{return AGENDA_TYPES.some(t=>t.value===v);}
export function isAgendaStatus(v:string):v is AgendaStatus{return AGENDA_STATUSES.some(t=>t.value===v);}
export function agendaScope(user:AgentScope):Prisma.AgendaEventWhereInput{
 return {tenantId:user.tenantId,lead:{is:leadWhere(user)}};
}
export function parseAgendaStartsAt(raw:FormDataEntryValue|null):Date|null{
 if(typeof raw!=="string"||!/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{3}Z$/.test(raw))return null;
 const date=new Date(raw);
 if(!Number.isFinite(date.getTime())||date.toISOString()!==raw)return null;
 if(date.getTime()<Date.now()-365*864e5||date.getTime()>Date.now()+730*864e5)return null;
 return date;
}
export function agendaLabel(value:string){
 return AGENDA_TYPES.find(t=>t.value===value)?.label||"Actividad";
}
export function agendaStatusLabel(value:string){
 return AGENDA_STATUSES.find(s=>s.value===value)?.label||value;
}
