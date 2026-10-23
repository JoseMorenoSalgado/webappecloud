"use client";
import {useState} from "react";
export function ThumbnailMaintenance(){
 const [cursor,setCursor]=useState<string|null>(null);
 const [busy,setBusy]=useState(false);
 const [message,setMessage]=useState("");
 const [finished,setFinished]=useState(false);
 async function run(){
  if(busy||finished)return;
  setBusy(true);
  try{
   const response=await fetch("/api/admin/properties/photos/backfill",{
    method:"POST",credentials:"same-origin",cache:"no-store",
    headers:{"Content-Type":"application/json"},body:JSON.stringify({cursor})
   });
   const data=await response.json();
   if(!response.ok)throw new Error(data.error||"No se pudo procesar el lote.");
   setMessage(`Lote revisado: ${data.scanned} fotos. Versiones creadas: ${data.created}. Errores: ${data.failed}.`);
   setCursor(data.nextCursor);
   if(!data.nextCursor)setFinished(true);
  }catch(e){setMessage(e instanceof Error?e.message:"Error de conexión.");}
  finally{setBusy(false);}
 }
 return <div className="property-help">
  <p><strong>Optimizar fotografías antiguas</strong></p>
  <p>Regenera miniaturas faltantes en lotes de hasta cinco fotografías. Presiona nuevamente para continuar con el siguiente lote. Solo se procesan imágenes del sitio actual.</p>
  <button type="button" className="btn wizard-back" disabled={busy||finished} onClick={()=>void run()}>{busy?"Procesando lote…":finished?"Revisión completa":cursor?"Procesar siguiente lote":"Iniciar optimización"}</button>
  {message&&<p role="status">{message}</p>}
 </div>;
}
