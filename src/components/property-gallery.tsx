"use client";
import {useRef,useState} from "react";
import {ChevronLeft,ChevronRight,Expand,Image as ImageIcon,X} from "lucide-react";

export function PropertyGallery({photos,title}:{photos:string[];title:string}){
 const [index,setIndex]=useState(0);
 const dialog=useRef<HTMLDialogElement>(null);
 if(!photos.length)return <div className="detail-image detail-image-empty"><ImageIcon size={36}/><span>Fotografías próximamente</span></div>;
 const selected=Math.min(index,photos.length-1);
 const current=photos[selected];
 const previous=()=>setIndex(v=>(v+photos.length-1)%photos.length);
 const next=()=>setIndex(v=>(v+1)%photos.length);
 return <section className="public-gallery" aria-label={"Galería de "+title}>
  <div className="public-gallery-main">
   <img src={current} alt={title+" — fotografía "+(selected+1)} loading="eager" decoding="async"/>
   <button type="button" className="public-gallery-expand" onClick={()=>dialog.current?.showModal()}
    aria-label={"Ampliar fotografía "+(selected+1)+" de "+photos.length}>
    <Expand size={17}/> <span>Ver imagen completa</span>
   </button>
   {photos.length>1&&<div className="public-gallery-controls">
    <button type="button" onClick={previous} aria-label="Fotografía anterior"><ChevronLeft size={20}/></button>
    <span>{selected+1} / {photos.length}</span>
    <button type="button" onClick={next} aria-label="Fotografía siguiente"><ChevronRight size={20}/></button>
   </div>}
  </div>
  <p className="public-gallery-hint">Fotografía completa, sin recortes ni ampliación artificial.</p>
  {photos.length>1&&<div className="public-gallery-thumbs">
   {photos.map((photo,i)=><button type="button" key={photo+"-"+i} onClick={()=>setIndex(i)}
    aria-label={"Ver fotografía "+(i+1)} aria-pressed={selected===i}
    className={selected===i?"selected":""}><img src={photo} alt="" loading="lazy"/></button>)}
  </div>}
  <dialog ref={dialog} className="public-gallery-dialog" aria-label={"Visualización de fotografías de "+title}>
   <div className="public-gallery-dialog-bar">
    <span>{title} · {selected+1} de {photos.length}</span>
    <button type="button" onClick={()=>dialog.current?.close()} aria-label="Cerrar fotografía"><X size={22}/></button>
   </div>
   <div className="public-gallery-dialog-image">
    <img src={current} alt={title+" — fotografía completa "+(selected+1)} decoding="async"/>
   </div>
   {photos.length>1&&<div className="public-gallery-dialog-actions">
    <button type="button" onClick={previous} aria-label="Fotografía anterior"><ChevronLeft size={19}/> Anterior</button>
    <button type="button" onClick={next} aria-label="Fotografía siguiente">Siguiente <ChevronRight size={19}/></button>
   </div>}
  </dialog>
 </section>;
}
