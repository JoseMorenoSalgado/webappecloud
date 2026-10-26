"use client";
import {useEffect} from "react";
import {usePathname} from "next/navigation";
import {MessageCircle} from "lucide-react";
import {type PublicTenant,type PublicProperty,contactUrl} from "@/lib/demo";
import {type SiteTemplateKey} from "@/lib/site-template-registry";
function log(kind:"view"|"whatsapp"){const body=JSON.stringify({kind,path:location.pathname});if(navigator.sendBeacon)navigator.sendBeacon("/api/events",new Blob([body],{type:"application/json"}));}
export function PageView(){useEffect(()=>{log("view");},[]);return null;}
export function WhatsAppButton({tenant,property}:{tenant:PublicTenant;property?:PublicProperty}){
 const href=contactUrl(tenant,property),enabled=Boolean(tenant.whatsapp);
 return <a className="btn btn-whatsapp" href={href} target={enabled?"_blank":undefined} rel={enabled?"noopener noreferrer":undefined} onClick={()=>{if(enabled)log("whatsapp");}}>Consultar por WhatsApp →</a>;
}
export function FloatingWhatsapp({tenant,property,templateKey="real-estate"}:{tenant:PublicTenant;property?:PublicProperty;templateKey?:SiteTemplateKey}){
 const pathname=usePathname();
 if(!tenant.whatsapp)return null;
 const href=pathname==="/asesoria-empresarial"?`https://wa.me/${tenant.whatsapp.replace(/\\D/g,"")}?text=${encodeURIComponent("Hola, deseo información sobre sus servicios de asesoría empresarial y cumplimiento normativo LA/FT/FP.")}`:contactUrl(tenant,property,templateKey);
 return <a className="floating-wa" href={href} target="_blank" rel="noopener noreferrer" aria-label="Consultar por WhatsApp" onClick={()=>log("whatsapp")}><MessageCircle size={28}/></a>;
}
