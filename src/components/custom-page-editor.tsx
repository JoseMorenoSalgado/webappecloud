"use client";
import {useEffect,useMemo,useState,type MouseEvent} from "react";
import Link from "next/link";
import {ArrowLeft,Eye,FileText,Globe2,LockKeyhole,Plus,Save} from "lucide-react";
type EditorProps={mode:"create"|"edit";slug?:string;title?:string;summary?:string;body?:string;published?:boolean};
function slugify(value:string){
 return value.normalize("NFD").replace(/[\u0300-\u036f]/g,"").toLowerCase()
  .replace(/[^a-z0-9]+/g,"-").replace(/^-+|-+$/g,"").slice(0,65).replace(/-+$/,"");
}
export function CustomPageEditor({mode,slug:storedSlug,title:initialTitle="",summary:initialSummary="",body:initialBody="",published:initialPublished=false}:EditorProps){
 const [title,setTitle]=useState(initialTitle);
 const [slug,setSlug]=useState(storedSlug||"");
 const [slugChanged,setSlugChanged]=useState(false);
 const [summary,setSummary]=useState(initialSummary);
 const [body,setBody]=useState(initialBody);
 const [published,setPublished]=useState(initialPublished);
 const [submitting,setSubmitting]=useState(false);
 const paragraphs=useMemo(()=>body.split(/\n\s*\n/).map(x=>x.trim()).filter(Boolean),[body]);
 const destination=mode==="create"?"/api/admin/site-pages":"/api/admin/site-pages/"+storedSlug;
 const previewPath="/pagina/"+(slug||"tu-pagina");
 const isEdited=title!==initialTitle||summary!==initialSummary||body!==initialBody||published!==initialPublished||(mode==="create"&&slug.length>0);
 const textCount=body.trim().length;
 useEffect(()=>{
  if(!isEdited||submitting)return;
  const warn=(event:BeforeUnloadEvent)=>{event.preventDefault();event.returnValue="";};
  window.addEventListener("beforeunload",warn);
  return ()=>window.removeEventListener("beforeunload",warn);
 },[isEdited,submitting]);
 const confirmLeave=(event:MouseEvent<HTMLAnchorElement>)=>{
  if(isEdited&&!submitting&&!window.confirm("Hay cambios sin guardar. ¿Quieres salir sin guardarlos?"))event.preventDefault();
 };
 return <div className="cms-page-workspace">
  <form className="admin-panel site-page-editor cms-page-editor-form" action={destination} method="post" onSubmit={()=>setSubmitting(true)}>
   <div className="cms-editor-section-heading"><FileText size={18}/><div><h2>Contenido de la página</h2><p>Escribe el contenido y observa la vista previa antes de guardar.</p></div></div>
   <label>Título de la página
    <input name="title" value={title} onChange={e=>{const next=e.target.value;setTitle(next);if(mode==="create"&&!slugChanged)setSlug(slugify(next));}}
      required minLength={3} maxLength={90} placeholder="Ej. Preguntas frecuentes"/>
    <small>{title.length}/90 caracteres</small>
   </label>
   {mode==="create"?
    <label>Dirección de la página
     <input name="slug" value={slug} onChange={e=>{setSlug(slugify(e.target.value));setSlugChanged(true);}}
      required minLength={3} maxLength={65} pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="preguntas-frecuentes"/>
     <small>Se genera a partir del título. Puedes modificarla antes de crear la página.</small>
    </label>:
    <p className="cms-editor-url"><Globe2 size={16}/> Dirección fija: <code>{previewPath}</code> <span>Los enlaces existentes se conservan.</span></p>}
   <label>Descripción breve
    <textarea name="summary" rows={3} maxLength={240} value={summary} onChange={e=>setSummary(e.target.value)}
     placeholder="Una breve introducción para los visitantes"/>
    <small>{summary.length}/240 caracteres</small>
   </label>
   <label>Contenido principal
    <textarea name="body" rows={12} maxLength={10000} value={body} onChange={e=>setBody(e.target.value)}
     placeholder="Escribe aquí. Separa los párrafos dejando una línea vacía."/>
    <small>{textCount.toLocaleString("es")}/10.000 caracteres · {paragraphs.length} párrafo{paragraphs.length===1?"":"s"}</small>
   </label>
   <div className="cms-publish-control">
    <div className="cms-publish-icon">{published?<Globe2 size={20}/>:<LockKeyhole size={20}/>}</div>
    <div><strong>{published?"Publicar en el sitio":"Guardar como borrador"}</strong>
     <p>{published?"Después de guardar, estará disponible mediante su enlace. Para mostrarla en el menú debes agregarla allí.":"Los visitantes no podrán acceder mientras esté en borrador."}</p></div>
    <label className="cms-publish-switch"><input name="published" type="checkbox" checked={published} onChange={e=>setPublished(e.target.checked)}/>
     <span>Publicada</span></label>
   </div>
   <div className="site-page-form-actions cms-editor-actions">
    <button className="btn btn-red" type="submit" disabled={submitting}>
     {mode==="create"?<Plus size={17}/>:<Save size={17}/>}
     {submitting?"Guardando...":mode==="create"?"Crear página":"Guardar cambios"}</button>
    <Link href="/admin/personalizar/paginas" className="btn wizard-back" onClick={confirmLeave}><ArrowLeft size={16}/> Volver a páginas</Link>
    {isEdited&&<span className="cms-unsaved" role="status">Cambios sin guardar</span>}
   </div>
  </form>
  <aside className="cms-live-preview" aria-label="Vista previa no publicada">
   <div className="cms-preview-top"><Eye size={17}/> Vista previa <span>Sin publicar</span></div>
   <div className="cms-preview-body">
    <small>INFORMACIÓN</small>
    <h2>{title||"Título de tu página"}</h2>
    <p className="cms-preview-summary">{summary||"La descripción breve aparecerá aquí."}</p>
    <div className="cms-preview-divider"/>
    {paragraphs.length?paragraphs.slice(0,8).map((p,i)=><p key={i}>{p}</p>):<p className="cms-preview-empty">Escribe el contenido para verlo aquí antes de publicarlo.</p>}
    {paragraphs.length>8&&<p className="cms-preview-more">+ {paragraphs.length-8} párrafos adicionales</p>}
   </div>
   <p className="cms-preview-caption">Esta vista previa es orientativa. No publica cambios ni muestra datos de otros clientes.</p>
  </aside>
 </div>;
}
