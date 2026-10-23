"use client";
import Link from "next/link";
import {useEffect,useState} from "react";
import {usePathname} from "next/navigation";
import {LayoutDashboard,Building2,UsersRound,Settings,Tags,CalendarDays,ChartNoAxesCombined,Menu,ChevronDown,ShieldCheck,UserRound,X,Bell,HandCoins} from "lucide-react";

const available=[
 {href:"/admin",label:"Inicio",Icon:LayoutDashboard},
 {href:"/admin/propiedades",label:"Propiedades",Icon:Building2},
 {href:"/admin/leads",label:"Solicitudes / Leads",Icon:UsersRound},
 {href:"/admin/agenda",label:"Agenda comercial",Icon:CalendarDays},
 {href:"/admin/notificaciones",label:"Notificaciones",Icon:Bell},
 {href:"/admin/cierres",label:"Ventas y alquileres",Icon:HandCoins},
 {href:"/admin/reportes",label:"Reportes",Icon:ChartNoAxesCombined},
 {href:"/admin/personalizar",label:"Personalizar",Icon:Settings}
];
export function AdminNav({role="admin",roles=[role],userId,notificationCount=0}:{role?:string;roles?:string[];userId?:string;notificationCount?:number}){
 const pathname=usePathname();
 const [mobileOpen,setMobileOpen]=useState(false);
 useEffect(()=>{setMobileOpen(false);},[pathname]);
 useEffect(()=>{
  if(!mobileOpen)return;
  const closeOnEscape=(event:KeyboardEvent)=>{if(event.key==="Escape")setMobileOpen(false);};
  document.addEventListener("keydown",closeOnEscape);
  return ()=>document.removeEventListener("keydown",closeOnEscape);
 },[mobileOpen]);
 const has=(r:string)=>roles.includes(r);
 const visible=available.filter(x=>has("admin")||has("editor")||(has("agente")&&["/admin","/admin/leads","/admin/agenda","/admin/notificaciones","/admin/cierres"].includes(x.href))||(has("lector")&&x.href==="/admin"));
 const items=(close:()=>void)=><>
  {visible.map(x=>{
   const selected=x.href==="/admin"?pathname===x.href:pathname===x.href||pathname.startsWith(x.href+"/");
   return <Link href={x.href} onClick={close} key={x.href} aria-current={selected?"page":undefined} className={selected?"active":undefined}><x.Icon size={18} aria-hidden="true"/>{x.label}{x.href==="/admin/notificaciones"&&notificationCount>0&&<span className="admin-nav-count" aria-label={notificationCount+" pendientes"}>{notificationCount>99?"99+":notificationCount}</span>}</Link>;
  })}
  {has("admin")&&<Link href="/admin/sistema" onClick={close} className={pathname.startsWith("/admin/sistema")?"active":undefined}><Settings size={18}/>Estado técnico</Link>}
  {has("admin")&&<Link href="/admin/clientes-agentes" onClick={close} className={pathname.startsWith("/admin/clientes-agentes")?"active":undefined}><ShieldCheck size={18}/>Clientes y agentes</Link>}
  {has("agente")&&!has("admin")&&!has("editor")&&<Link href="/admin/reportes/agentes" onClick={close} className={pathname.startsWith("/admin/reportes/agentes")?"active":undefined}><ChartNoAxesCombined size={18}/>Mi rendimiento</Link>}
  {has("agente")&&userId&&<Link href={"/admin/clientes-agentes/agentes/"+encodeURIComponent(userId)} onClick={close} className={pathname==="/admin/clientes-agentes/agentes/"+userId?"active":undefined}><UserRound size={18}/>Mi perfil comercial</Link>}
  {has("admin")&&<><span aria-disabled="true" title="Próximamente"><Tags size={18}/>Categorías</span></>}
 </>;
 return <>
  <nav className="admin-nav admin-nav-desktop" aria-label="Menú de administración">{items(()=>{})}</nav>
  <div className="admin-nav-mobile">
   <button type="button" className="admin-mobile-trigger" onClick={()=>setMobileOpen(v=>!v)} aria-expanded={mobileOpen} aria-controls="admin-mobile-drawer">
    <Menu size={20}/> Menú <ChevronDown size={16}/>
   </button>
   {mobileOpen&&<>
    <button type="button" className="admin-mobile-overlay" onClick={()=>setMobileOpen(false)} aria-label="Cerrar navegación"/>
    <aside id="admin-mobile-drawer" className="admin-mobile-drawer" role="dialog" aria-modal="true" aria-label="Navegación del panel">
     <div className="admin-mobile-drawer-head"><strong>Navegación</strong><button type="button" onClick={()=>setMobileOpen(false)} aria-label="Cerrar menú"><X size={20}/></button></div>
     <nav className="admin-nav" aria-label="Menú de administración móvil">{items(()=>setMobileOpen(false))}</nav>
    </aside>
   </>}
  </div>
 </>;
}
