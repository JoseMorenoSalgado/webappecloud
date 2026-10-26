import "./modules.css";
import Link from "next/link";
import {CheckCircle2,LockKeyhole,Settings2} from "lucide-react";
import {requireRole} from "@/lib/auth";
import {getSiteTemplate} from "@/lib/site-template-store";
import {getTenantModules} from "@/lib/tenant-modules";
import {modulesForSector} from "@/lib/tenant-modules-registry";

export const dynamic="force-dynamic";
export default async function ModulesPage({searchParams}:{searchParams:Promise<{saved?:string;error?:string;inactive?:string}>}){
 const admin=await requireRole(["admin"]);
 const [template,params]=await Promise.all([getSiteTemplate(admin.tenantId),searchParams]);
 const enabled=await getTenantModules(admin.tenantId,template.key);
 const modules=modulesForSector(template.key);
 return <>
  <div className="admin-heading"><div><h1>Aplicaciones y módulos</h1>
   <p>Activa solo las herramientas que necesita tu empresa. Desactivar no elimina información.</p></div>
   <Link className="btn wizard-back" href="/admin/personalizar/plantilla">Cambiar sector</Link>
  </div>
  {params.saved&&<p className="property-success" role="status">Módulos guardados para esta empresa.</p>}
  {params.error&&<p className="admin-error" role="alert">No fue posible guardar los módulos. Inténtalo de nuevo.</p>}
  {params.inactive&&<p className="admin-error" role="status">Ese módulo está desactivado para tu empresa. Puedes habilitarlo aquí.</p>}
  <section className="admin-panel">
   <div className="module-heading"><Settings2 size={21}/><div><h2>Herramientas de {template.key==="real-estate"?"Inmobiliaria":template.key==="education"?"Educación":template.key==="ecommerce"?"Comercio electrónico":"Servicios"}</h2>
    <p>Solo se pueden activar módulos que ya están implementados. Los demás aparecerán disponibles en futuras versiones.</p></div></div>
   <form action="/api/admin/modules" method="post" className="module-form">
    <div className="module-grid">
     {modules.map(module=><label className={"module-card"+(!module.implemented?" module-soon":"")} key={module.key}>
      <span className="module-main">
       <strong>{module.label}</strong>
       <small>{module.description}</small>
       <span className="module-status">{!module.implemented?"Próximamente":module.mandatory?"Siempre activo":enabled.includes(module.key)?"Activo":"Inactivo"}</span>
      </span>
      <span className="module-toggle">
       {module.implemented&&module.mandatory?<LockKeyhole size={20} aria-label="Módulo obligatorio"/>:
        <input type="checkbox" name="modules" value={module.key} disabled={!module.implemented}
         defaultChecked={enabled.includes(module.key)} aria-label={"Activar "+module.label}/>}
      </span>
     </label>)}
    </div>
    <p className="property-help">Las opciones se guardan por empresa y por sector. Cambiar de plantilla no borra registros ni facturas.</p>
    <button className="btn btn-red" type="submit"><CheckCircle2 size={17}/> Guardar módulos activos</button>
   </form>
  </section>
 </>;
}
