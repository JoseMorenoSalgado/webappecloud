"use client";
import {useState} from "react";
import {ChevronLeft,ChevronRight,Image as ImageIcon} from "lucide-react";
export function PropertyGallery({photos,title}:{photos:string[];title:string}){
 const [index,setIndex]=useState(0);
 if(!photos.length)return <div className="detail-image detail-image-empty"><ImageIcon size={36}/><span>Fotografías próximamente</span></div>;
 const current=photos[Math.min(index,photos.length-1)];
 return <section className="public-gallery" aria-label={"Galería de "+title}>
  <div className="public-gallery-main"><img src={current} alt={title+" — fotografía "+(index+1)} loading="eager"/>
   {photos.length>1&&<div className="public-gallery-controls">
    <button type="button" onClick={()=>setIndex(v=>(v+photos.length-1)%photos.length)} aria-label="Fotografía anterior"><ChevronLeft size={20}/></button>
    <span>{index+1} / {photos.length}</span>
    <button type="button" onClick={()=>setIndex(v=>(v+1)%photos.length)} aria-label="Fotografía siguiente"><ChevronRight size={20}/></button>
   </div>}
  </div>
  {photos.length>1&&<div className="public-gallery-thumbs">
   {photos.map((photo,i)=><button type="button" key={photo+"-"+i} onClick={()=>setIndex(i)} aria-label={"Ver fotografía "+(i+1)} aria-pressed={index===i} className={index===i?"selected":""}><img src={photo} alt="" loading="lazy"/></button>)}
  </div>}
 </section>;
}
