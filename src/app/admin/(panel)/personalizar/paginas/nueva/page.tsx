import Link from "next/link";
import {ArrowLeft,Plus} from "lucide-react";
import {requireAdmin} from "@/lib/auth";
export const dynamic="force-dynamic";
export default async function NewSitePage({searchParams}:{searchParams:Promise<{error?:string}>}){
 const admin=await requireAdmin(),q=await searchParams;
 return <>
  <div className="admin-heading"><div><Link href="/admin/personalizar/paginas" className="inline-link"><ArrowLeft size={16}/> Páginas</Link>
   <h1>Nueva página</h1><p>Crea una página institucional con título, descripción y contenido. Puedes mantenerla como borrador.</p></div></div>
  {q.error&&<p role="alert" className="admin-error">Revisa los datos. La dirección debe ser única, con letras minúsculas, números o guiones.</p>}
  {["admin","editor"].includes(admin.role)?
   <form className="admin-panel site-page-editor" method="post" action="/api/admin/site-pages">
    <label>Título de la página<input name="title" minLength={3} maxLength={90} required placeholder="Ej. Preguntas frecuentes"/></label>
    <label>Dirección (slug)<input name="slug" pattern="[a-z0-9]+(-[a-z0-9]+)*" minLength={3} maxLength={65} required placeholder="preguntas-frecuentes"/></label>
    <label>Descripción corta<textarea name="summary" maxLength={240} rows={3} placeholder="Resumen para presentar la página"/></label>
    <label>Contenido<textarea name="body" rows={12} maxLength={10000} placeholder="Escribe el contenido de la página. Divide los párrafos con una línea en blanco."/></label>
    <label className="site-page-check"><input name="published" type="checkbox"/> Publicar inmediatamente</label>
    <div className="site-page-form-actions"><button type="submit" className="btn btn-red"><Plus size={17}/> Crear página</button><Link href="/admin/personalizar/paginas" className="btn wizard-back">Cancelar</Link></div>
   </form>:<p className="admin-error">No tienes permisos para crear páginas.</p>}
 </>;
}
