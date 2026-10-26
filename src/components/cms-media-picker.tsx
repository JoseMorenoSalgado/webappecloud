"use client";
import {useState} from "react";
import {Images,X} from "lucide-react";
type Media={id:string;slug:string;pageTitle:string;url:string};

/** Import creates a separate tenant-scoped file owned by the target page. */
export function CmsMediaPicker({targetSlug,onChoose,disabled=false}:{targetSlug:string;onChoose:(id:string)=>void;disabled?:boolean}){
 const [open,setOpen]=useState(false),[items,setItems]=useState<Media[]>([]);
 const [loading,setLoading]=useState(false),[selecting,setSelecting]=useState<string|null>(null),[error,setError]=useState("");
 async function show(){
  setOpen(true);setLoading(true);setError("");
  try{
   const res=await fetch("/api/admin/media",{credentials:"same-origin",cache:"no-store"});
   const value=await res.json() as {items?:Media[];error?:string};
   if(!res.ok)throw Error(value.error||"No se pudieron cargar las imágenes.");
   setItems(Array.isArray(value.items)?value.items:[]);
  }catch(e){setError(e instanceof Error?e.message:"Error de conexión.");}
  finally{setLoading(false);}
 }
 async function select(item:Media){
  if(selecting)return;
  setSelecting(item.id);setError("");
  try{
   const res=await fetch("/api/admin/media/import",{
    method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},
    body:JSON.stringify({sourceSlug:item.slug,targetSlug,imageId:item.id})
   });
   const v=await res.json() as {id?:string;error?:string};
   if(!res.ok||!v.id)throw Error(v.error||"No se pudo reutilizar la imagen.");
   onChoose(v.id);setOpen(false);
  }catch(e){setError(e instanceof Error?e.message:"Error de conexión.");}
  finally{setSelecting(null);}
 }
 return <div className="cms-media-picker">
  <button type="button" className="cms-media-open" onClick={()=>void show()} disabled={disabled}><Images size={16}/> Usar imagen existente</button>
  {open&&<div className="cms-media-sheet" role="dialog" aria-modal="true" aria-label="Biblioteca de imágenes">
   <div className="cms-media-head"><div><strong>Biblioteca de imágenes</strong><p>Elige una imagen que ya subiste a este sitio. Se guardará una copia para esta página.</p></div>
    <button type="button" onClick={()=>setOpen(false)} aria-label="Cerrar biblioteca"><X size={19}/></button>
   </div>
   {loading&&<p role="status">Cargando fotografías...</p>}
   {error&&<p role="alert" className="admin-error">{error}</p>}
   {!loading&&!items.length&&<p>No hay fotografías disponibles. Sube una imagen primero.</p>}
   {!loading&&items.length>0&&<div className="cms-media-grid">{items.map(item=><button
    type="button" key={item.slug+":"+item.id} disabled={Boolean(selecting)}
    onClick={()=>void select(item)} aria-label={"Usar imagen de "+item.pageTitle}>
    <img loading="lazy" src={item.url} alt="" aria-hidden="true"/>
    <span>{item.pageTitle}</span>
   </button>)}</div>}
   {selecting&&<p role="status">Preparando imagen para esta página...</p>}
  </div>}
 </div>;
}
