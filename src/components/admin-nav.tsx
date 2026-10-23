"use client";
import Link from "next/link";
import {useEffect,useState} from "react";
import {usePathname} from "next/navigation";
import {
 LayoutDashboard,Building2,UsersRound,Settings,CalendarDays,
 ChartNoAxesCombined,Menu,ChevronDown,ShieldCheck,UserRound,
 X,Bell,HandCoins,ExternalLink,type LucideIcon
} from "lucide-react";

type Entry={href:string;label:string;Icon:LucideIcon};
const primary:Entry[]=[
 {href:"/admin",label:"Inicio",Icon:LayoutDashboard},
 {href:"/admin/propiedades",label:"Propiedades",Icon:Building2},
 {href:"/admin/leads",label:"Prospectos",Icon:UsersRound},
 {href:"/admin/agenda",label:"Agenda",Icon:CalendarDays},
 {href:"/admin/cierres",label:"Ventas y alquileres",Icon:HandCoins}
];
const secondary:Entry[]=[
 {href:"/admin/notificaciones",label:"Notificaciones",Icon:Bell},
 {href:"/admin/reportes",label:"Reportes",Icon:ChartNoAxesCombined},
 {href:"/admin/personalizar",label:"Personalizar sitio",Icon:Settings}
];
function selectedLink(pathname:string,href:string){
 return href==="/admin"?pathname===href:pathname===href||pathname.startsWith(href+"/");
}
export function AdminNav({role="admin",roles=[role],userId,notificationCount=0}:{
 role?:string;roles?:string[];userId?:string;notificationCount?:number
}){
 const pathname=usePathname();
 const [mobileOpen,setMobileOpen]=useState(false);
 useEffect(()=>{setMobileOpen(false);},[pathname]);
 useEffect(()=>{
  if(!mobileOpen)return;
  const close=(event:KeyboardEvent)=>{if(event.key==="Escape")setMobileOpen(false);};
  document.addEventListener("keydown",close);
  return ()=>document.removeEventListener("keydown",close);
 },[mobileOpen]);
 const has=(r:string)=>roles.includes(r);
 const manager=has("admin")||has("editor");
 const agent=has("agente");
 const main=primary.filter(x=>manager||(agent&&["/admin","/admin/leads","/admin/agenda","/admin/cierres"].includes(x.href))||x.href==="/admin");
 const tools=secondary.filter(x=>manager||(agent&&x.href==="/admin/notificaciones"));
 if(has("admin")){
  tools.push({href:"/admin/clientes-agentes",label:"Clientes y agentes",Icon:ShieldCheck});
  tools.push({href:"/admin/sistema",label:"Estado técnico",Icon:Settings});
 }
 if(agent&&!manager){
  tools.push({href:"/admin/reportes/agentes",label:"Mi rendimiento",Icon:ChartNoAxesCombined});
 }
 if(agent&&userId){
  tools.push({href:"/admin/clientes-agentes/agentes/"+encodeURIComponent(userId),label:"Mi perfil comercial",Icon:UserRound});
 }
 const notifyBadge=(x:Entry)=>x.href==="/admin/notificaciones"&&notificationCount>0
  ? <span className="admin-nav-count" aria-label={notificationCount+" pendientes"}>{notificationCount>99?"99+":notificationCount}</span>:null;
 const entries=(links:Entry[],close:()=>void)=>links.map(x=>{
  const active=selectedLink(pathname,x.href);
  return <Link href={x.href} key={x.href} onClick={close} className={active?"active":undefined}
   aria-current={active?"page":undefined}><x.Icon size={18} aria-hidden="true"/><span>{x.label}</span>{notifyBadge(x)}</Link>;
 });
 const toolActive=tools.some(x=>selectedLink(pathname,x.href));
 return <>
  <nav className="admin-nav admin-nav-desktop admin-nav-simplified" aria-label="Menú de administración">
   <span className="admin-nav-section-label">Trabajo diario</span>
   {entries(main,()=>{})}
   {tools.length>0&&<details className="admin-nav-more" key={toolActive?"current-tools":"other-tools"} defaultOpen={toolActive}>
    <summary><Settings size={18} aria-hidden="true"/><span>Más herramientas</span><ChevronDown size={16} aria-hidden="true"/></summary>
    <div className="admin-nav-more-content">{entries(tools,()=>{})}</div>
   </details>}
   <Link href="/" target="_blank" rel="noopener noreferrer" className="admin-nav-website">
    <ExternalLink size={17} aria-hidden="true"/><span>Ver página pública</span>
   </Link>
  </nav>
  <div className="admin-nav-mobile">
   <button type="button" className="admin-mobile-trigger" onClick={()=>setMobileOpen(v=>!v)} aria-expanded={mobileOpen} aria-controls="admin-mobile-drawer">
    <Menu size={20}/> Menú <ChevronDown size={16}/>
   </button>
   {mobileOpen&&<>
    <button type="button" className="admin-mobile-overlay" onClick={()=>setMobileOpen(false)} aria-label="Cerrar navegación"/>
    <aside id="admin-mobile-drawer" className="admin-mobile-drawer" role="dialog" aria-modal="true" aria-label="Navegación del panel">
     <div className="admin-mobile-drawer-head"><strong>Navegación</strong><button type="button" onClick={()=>setMobileOpen(false)} aria-label="Cerrar menú"><X size={20}/></button></div>
     <nav className="admin-nav admin-nav-mobile-list" aria-label="Navegación móvil">
      <span className="admin-nav-section-label">Trabajo diario</span>{entries(main,()=>setMobileOpen(false))}
      {tools.length>0&&<><span className="admin-nav-section-label">Más herramientas</span>{entries(tools,()=>setMobileOpen(false))}</>}
      <Link href="/" target="_blank" rel="noopener noreferrer" onClick={()=>setMobileOpen(false)}><ExternalLink size={17}/>Ver página pública</Link>
     </nav>
    </aside>
   </>}
  </div>
 </>;
}
