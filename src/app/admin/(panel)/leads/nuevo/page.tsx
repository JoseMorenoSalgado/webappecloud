import Link from "next/link";
import {ArrowLeft,UserPlus} from "lucide-react";
import {redirect} from "next/navigation";
import {requireAdmin} from "@/lib/auth";
import {crmTablesReady} from "@/lib/crm-schema";
import {prisma} from "@/lib/prisma";
export const dynamic="force-dynamic";
const errors:Record<string,string>={
 datos:"Revisa el nombre, teléfono y los campos del formulario.",
 correo:"Introduce un correo electrónico válido.",
 propiedad:"La propiedad seleccionada no pertenece a tu inmobiliaria.",
 consentimiento:"Confirma que el cliente autorizó el seguimiento de su consulta.",
 permisos:"Tu usuario no tiene permisos de edición.",
 origen:"La solicitud se hizo desde un origen no autorizado.",
 servidor:"No se pudo registrar el prospecto. Inténtalo nuevamente."
};
export default async function CreateLead({searchParams}:{searchParams:Promise<{error?:string}>}){
 const admin=await requireAdmin();
 if(!await crmTablesReady())redirect("/admin/leads");
 const props=await prisma.property.findMany({where:{tenantId:admin.tenantId},select:{id:true,title:true,refCode:true},orderBy:{createdAt:"desc"},take:200});
 const canEdit=["admin","editor"].includes(admin.role);
 const q=await searchParams;
 return <>
  <div className="crm-detail-head"><Link href="/admin/leads" className="inline-link"><ArrowLeft size={16}/> Volver a prospectos</Link></div>
  <div className="admin-heading"><div><h1>Registrar prospecto</h1><p>Guarda un contacto recibido por WhatsApp, teléfono o atención directa para darle seguimiento.</p></div></div>
  {q.error&&<p role="alert" className="admin-error">{errors[q.error]||errors.servidor}</p>}
  {!canEdit?<p className="admin-error">Tu usuario no tiene permisos para registrar prospectos.</p>:<form method="post" action="/api/admin/crm/leads" className="admin-form property-editor crm-create-form">
   <div className="property-form-section"><h2>Datos de contacto</h2>
    <div className="admin-form-grid">
     <label>Nombre completo *<input name="name" maxLength={120} minLength={2} required placeholder="Nombre del cliente"/></label>
     <label>Teléfono / WhatsApp *<input type="tel" name="phone" maxLength={30} minLength={7} required placeholder="8888 8888"/></label>
    </div>
    <div className="admin-form-grid">
     <label>Correo electrónico<input name="email" type="email" maxLength={180} placeholder="cliente@ejemplo.com"/></label>
     <label>Canal de entrada<select name="source" defaultValue="whatsapp"><option value="whatsapp">WhatsApp</option><option value="telefono">Llamada telefónica</option><option value="manual">Atención directa</option></select></label>
    </div>
    <label>Propiedad de interés<select name="propertyId" defaultValue=""><option value="">Consulta general, sin propiedad específica</option>{props.map(p=><option key={p.id} value={p.id}>{p.refCode} · {p.title}</option>)}</select></label>
    <label>Observaciones de la consulta<textarea name="message" maxLength={2000} placeholder="Ej.: Solicita precio y disponibilidad para visitar la propiedad."/></label>
   </div>
   <div className="property-form-section"><h2>Privacidad del contacto</h2>
    <label className="property-check"><input type="checkbox" name="consent" required/> Confirmo que el cliente autorizó que lo contactemos por esta consulta.</label>
    <p className="property-help">No registres datos personales si el cliente no ha autorizado el contacto.</p>
   </div>
   <div className="property-editor-actions"><button type="submit" className="btn btn-red"><UserPlus size={17}/> Guardar prospecto</button><Link href="/admin/leads" className="btn wizard-back">Cancelar</Link></div>
  </form>}
 </>;
}
