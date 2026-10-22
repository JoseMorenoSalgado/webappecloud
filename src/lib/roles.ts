export const ROLE_LABELS={admin:"Administrador",editor:"Editor",agente:"Agente comercial",lector:"Consulta"} as const;
export type UserRole=keyof typeof ROLE_LABELS;
export function isUserRole(role:string):role is UserRole{return Object.prototype.hasOwnProperty.call(ROLE_LABELS,role);}
export function canManageUsers(role:string){return role==="admin";}
export function canEditProperties(role:string){return role==="admin"||role==="editor";}
export function canManageLeads(role:string){return role==="admin"||role==="editor"||role==="agente";}
export function canEditSite(role:string){return role==="admin"||role==="editor";}
export function canViewReports(role:string){return role==="admin"||role==="editor";}
