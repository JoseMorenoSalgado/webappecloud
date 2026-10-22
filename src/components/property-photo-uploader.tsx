"use client";
import {useEffect,useRef,useState} from "react";
import {useRouter} from "next/navigation";
import {ImagePlus,Trash2} from "lucide-react";
type Photo={id:string;url:string;alt:string};
export function PropertyPhotoUploader({propertyId,photos}:{propertyId:string;photos:Photo[]}){
 const router=useRouter(),inputRef=useRef<HTMLInputElement>(null);
 const [busy,setBusy]=useState(false),[error,setError]=useState(""),[success,setSuccess]=useState("");
 useEffect(()=>{setError("");},[propertyId]);
 async function addImages(files:FileList|null){
  if(!files?.length||busy)return;
  setError("");setSuccess("");
  const remaining=15-photos.length;
  if(files.length>remaining){setError("Solo quedan "+remaining+" espacios disponibles.");return;}
  setBusy(true);
  try{
   for(const file of Array.from(files)){
    if(file.size>8*1024*1024){setError("La imagen "+file.name+" supera los 8 MB.");break;}
    const body=new FormData();body.append("photo",file);
    const response=await fetch("/api/admin/properties/"+encodeURIComponent(propertyId)+"/photos",{
     method:"POST",body,credentials:"same-origin",cache:"no-store"
    });
    const data=await response.json();
    if(!response.ok){setError(data.error||"No fue posible subir la imagen.");break;}
    setSuccess("Fotografía guardada correctamente.");
   }
   router.refresh();
  }catch{setError("No se pudo enviar la fotografía. Revisa la conexión.")}
  finally{setBusy(false);if(inputRef.current)inputRef.current.value="";}
 }
 async function removePhoto(id:string){
  if(busy||!window.confirm("¿Eliminar esta fotografía?"))return;
  setBusy(true);setError("");setSuccess("");
  try{
   const response=await fetch("/api/admin/properties/"+encodeURIComponent(propertyId)+"/photos/"+encodeURIComponent(id),{
    method:"DELETE",credentials:"same-origin",cache:"no-store"
   });
   const data=await response.json();
   if(!response.ok)throw Error(data.error||"No se pudo eliminar.");
   setSuccess("Fotografía eliminada.");
   router.refresh();
  }catch(error){setError(error instanceof Error?error.message:"Error de conexión.");}
  finally{setBusy(false);}
 }
 return <div className="property-upload-area">
  <label className="property-upload-button"><ImagePlus size={19}/>{busy?"Procesando fotografías...":"Seleccionar fotografías"}
   <input ref={inputRef} type="file" accept="image/jpeg,image/png,image/webp" multiple disabled={busy||photos.length>=15} onChange={e=>void addImages(e.target.files)}/>
  </label>
  <span className="property-photo-count">{photos.length} de 15 fotografías</span>
  {error&&<p className="admin-error" role="alert">{error}</p>}
  {success&&<p className="property-success" role="status">{success}</p>}
  {photos.length>0?<div className="property-upload-grid">
   {photos.map((photo,i)=><div key={photo.id} className="property-upload-tile">
    <img src={photo.url} alt={photo.alt||"Fotografía "+(i+1)} loading="lazy"/>
    {i===0&&<span className="property-cover-label">Portada</span>}
    <button type="button" onClick={()=>void removePhoto(photo.id)} disabled={busy} aria-label={"Eliminar fotografía "+(i+1)}><Trash2 size={16}/></button>
   </div>)}
  </div>:<p className="property-help">No has subido fotografías. La primera imagen será la portada del inmueble.</p>}
 </div>;
}
