import Link from "next/link";
import {notFound} from "next/navigation";
import {ArrowLeft,ExternalLink,Save} from "lucide-react";
import {requireAdmin} from "@/lib/auth";
import {getCustomPage,textBlocks} from "@/lib/site-pages";
export const dynamic="force-dynamic";
export default async function EditSitePage({params,searchParams}:{params:Promise<{slug:string}>;searchParams:Promise<{saved?:string;created?:string;error?:string}>}){
 const admin=await requireAdmin(),{slug}=await params,q=await searchParams;
 const page=await getCustomPage(admin.tenantId,slug);
 if(!page)notFound();
 const blocks=textBlocks(page.blocks),canEdit=["admin","editor"].includes(admin.role);
 return <>
  <div className="admin-heading"><div><Link className="inline-link" href="/admin/personalizar/paginas"><ArrowLeft size={16}/> Páginas</Link>
   <h1>{page.title}</h1><p>Publica, edita o guarda el contenido de tu página.</p></div>
   {page.published&&<Link href={"/pagina/"+slug} target="_blank" className="btn wizard-back">Ver página <ExternalLink size={16}/></Link>}</div>
  {(q.saved||q.created)&&<p className="property-success" role="status">Página {q.created?"creada":"actualizada"} correctamente.</p>}
  {q.error&&<p className="admin-error" role="alert">No se pudo guardar la página. Comprueba los textos y el estado de publicación.</p>}
  {canEdit?<form className="admin-panel site-page-editor" method="post" action={"/api/admin/site-pages/"+slug}>
   <label>Título<input name="title" required minLength={3} maxLength={90} defaultValue={page.title}/></label>
   <p className="property-help">Dirección pública: /pagina/{slug} · No se modifica para conservar los enlaces existentes.</p>
   <label>Descripción breve<textarea name="summary" maxLength={240} rows={3} defaultValue={blocks.summary}/></label>
   <label>Contenido principal<textarea name="body" maxLength={10000} rows={13} defaultValue={blocks.body}/></label>
   <label className="site-page-check"><input name="published" type="checkbox" defaultChecked={page.published}/> Página publicada</label>
   <div className="site-page-form-actions"><button className="btn btn-red"><Save size={17}/> Guardar página</button>
    <Link href="/admin/personalizar/menu" className="btn wizard-back">Organizar menú</Link></div>
  </form>:<p className="admin-error">No tienes permisos para editar.</p>}
 </>;
}
