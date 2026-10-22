import {requireAdmin} from "@/lib/auth";
import {prisma} from "@/lib/prisma";
import {ROLE_LABELS,parseRoles} from "@/lib/roles";
import {redirect} from "next/navigation";
import Link from "next/link";
export const dynamic="force-dynamic";
const messages:Record<string,string>={creado:"Usuario creado correctamente.",guardado:"Permisos actualizados.",datos:"Revisa los datos ingresados.",duplicado:"No se pudo crear el usuario. Verifica si el correo ya existe.",propia:"No puedes modificar tu propia cuenta desde esta sección.",ultimo:"Debe quedar al menos un administrador activo."};
export default async function Users({searchParams}:{searchParams:Promise<{status?:string}>}){
 const admin=await requireAdmin();if(!admin.roles.includes("admin"))redirect("/admin");
 const users=await prisma.adminUser.findMany({where:{tenantId:admin.tenantId},orderBy:[{active:"desc"},{createdAt:"asc"}],select:{id:true,name:true,email:true,role:true,active:true}});
 const clientCount=await prisma.lead.count({where:{tenantId:admin.tenantId}});
 const agents=users.filter(u=>parseRoles(u.role).includes("agente"));
 const {status}=await searchParams;
 return <section className="user-management">
  <div className="admin-heading"><div><h1>Clientes y agentes</h1><p>Gestiona colaboradores y asigna varios roles a cada cuenta.</p></div></div>
  <div className="client-agent-summary"><div><strong>{agents.length}</strong><span>Agentes registrados</span></div><div><strong>{clientCount}</strong><span>Prospectos y clientes potenciales</span><Link href="/admin/leads">Abrir seguimiento comercial →</Link></div><div><strong>{users.length}</strong><span>Usuarios con acceso al dashboard</span></div></div>
  <p className="property-help">Los clientes potenciales se gestionan en Solicitudes / Leads y no reciben automáticamente acceso administrativo. Los agentes son colaboradores con roles asignados.</p>
  {status&&messages[status]&&<p role="status" className={["creado","guardado"].includes(status)?"property-success":"admin-error"}>{messages[status]}</p>}
  <div className="admin-panel"><h2>Crear usuario</h2><form className="user-create-form" method="post" action="/api/admin/users">
   <input type="hidden" name="intent" value="create"/>
   <label>Nombre completo<input name="name" minLength={2} maxLength={120} required autoComplete="name"/></label>
   <label>Correo de acceso<input name="email" type="email" required autoComplete="off"/></label>
   <label>Contraseña inicial<input name="password" type="password" minLength={12} maxLength={256} required autoComplete="new-password"/></label>
   <fieldset className="user-role-options"><legend>Roles del usuario (puedes seleccionar varios)</legend>{Object.entries(ROLE_LABELS).map(([value,label])=><label key={value}><input type="checkbox" name="roles" value={value} defaultChecked={value==="agente"}/>{label}</label>)}</fieldset>
   <button className="btn btn-red" type="submit">Crear usuario</button>
  </form><p className="property-help">Las contraseñas deben tener al menos 12 caracteres. Cada usuario solo accederá a la empresa a la que pertenece.</p></div>
  <div className="admin-panel"><h2>Usuarios registrados ({users.length})</h2><div className="user-table-wrap"><table className="admin-table"><thead><tr><th>Usuario</th><th>Estado</th><th>Rol</th><th>Acciones</th></tr></thead><tbody>{users.map(u=><tr key={u.id}><td><strong>{u.name}</strong><div className="user-email">{u.email}</div></td><td>{u.active?"Activo":"Inactivo"}</td><td>{parseRoles(u.role).map(r=>ROLE_LABELS[r]).join(", ")||"Sin rol válido"}</td><td>{u.id===admin.userId?<span>Tu cuenta</span>:<div className="user-actions"><form method="post" action="/api/admin/users"><input type="hidden" name="intent" value="role"/><input type="hidden" name="id" value={u.id}/><fieldset className="user-role-options user-role-inline"><legend>Roles</legend>{Object.entries(ROLE_LABELS).map(([value,label])=><label key={value}><input type="checkbox" name="roles" value={value} defaultChecked={parseRoles(u.role).includes(value as keyof typeof ROLE_LABELS)}/>{label}</label>)}</fieldset><button type="submit">Guardar rol</button></form><form method="post" action="/api/admin/users"><input type="hidden" name="intent" value="toggle"/><input type="hidden" name="id" value={u.id}/><button type="submit">{u.active?"Desactivar":"Activar"}</button></form></div>}</td></tr>)}</tbody></table></div></div>
 </section>;
}
