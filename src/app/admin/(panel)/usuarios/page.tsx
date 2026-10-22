import {requireAdmin} from "@/lib/auth";
import {prisma} from "@/lib/prisma";
import {ROLE_LABELS,isUserRole} from "@/lib/roles";
import {redirect} from "next/navigation";
export const dynamic="force-dynamic";
const messages:Record<string,string>={creado:"Usuario creado correctamente.",guardado:"Permisos actualizados.",datos:"Revisa los datos ingresados.",duplicado:"No se pudo crear el usuario. Verifica si el correo ya existe.",propia:"No puedes modificar tu propia cuenta desde esta sección.",ultimo:"Debe quedar al menos un administrador activo."};
export default async function Users({searchParams}:{searchParams:Promise<{status?:string}>}){
 const admin=await requireAdmin();if(admin.role!=="admin")redirect("/admin");
 const users=await prisma.adminUser.findMany({where:{tenantId:admin.tenantId},orderBy:[{active:"desc"},{createdAt:"asc"}],select:{id:true,name:true,email:true,role:true,active:true}});
 const {status}=await searchParams;
 return <section className="user-management">
  <div className="admin-heading"><div><h1>Usuarios y roles</h1><p>Administra quién puede acceder al panel de tu empresa.</p></div></div>
  {status&&messages[status]&&<p role="status" className={["creado","guardado"].includes(status)?"property-success":"admin-error"}>{messages[status]}</p>}
  <div className="admin-panel"><h2>Crear usuario</h2><form className="user-create-form" method="post" action="/api/admin/users">
   <input type="hidden" name="intent" value="create"/>
   <label>Nombre completo<input name="name" minLength={2} maxLength={120} required autoComplete="name"/></label>
   <label>Correo de acceso<input name="email" type="email" required autoComplete="off"/></label>
   <label>Contraseña inicial<input name="password" type="password" minLength={12} maxLength={256} required autoComplete="new-password"/></label>
   <label>Rol<select name="role" defaultValue="agente">{Object.entries(ROLE_LABELS).map(([value,label])=><option key={value} value={value}>{label}</option>)}</select></label>
   <button className="btn btn-red" type="submit">Crear usuario</button>
  </form><p className="property-help">Las contraseñas deben tener al menos 12 caracteres. Cada usuario solo accederá a la empresa a la que pertenece.</p></div>
  <div className="admin-panel"><h2>Usuarios registrados ({users.length})</h2><div className="user-table-wrap"><table className="admin-table"><thead><tr><th>Usuario</th><th>Estado</th><th>Rol</th><th>Acciones</th></tr></thead><tbody>{users.map(u=><tr key={u.id}><td><strong>{u.name}</strong><div className="user-email">{u.email}</div></td><td>{u.active?"Activo":"Inactivo"}</td><td>{isUserRole(u.role)?ROLE_LABELS[u.role]:u.role}</td><td>{u.id===admin.userId?<span>Tu cuenta</span>:<div className="user-actions"><form method="post" action="/api/admin/users"><input type="hidden" name="intent" value="role"/><input type="hidden" name="id" value={u.id}/><select name="role" defaultValue={u.role}>{Object.entries(ROLE_LABELS).map(([value,label])=><option key={value} value={value}>{label}</option>)}</select><button type="submit">Guardar rol</button></form><form method="post" action="/api/admin/users"><input type="hidden" name="intent" value="toggle"/><input type="hidden" name="id" value={u.id}/><button type="submit">{u.active?"Desactivar":"Activar"}</button></form></div>}</td></tr>)}</tbody></table></div></div>
 </section>;
}
