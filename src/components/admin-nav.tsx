"use client";
import Link from "next/link";
import {useEffect,useRef,useState} from "react";
import {usePathname} from "next/navigation";
import {LayoutDashboard,Building2,UsersRound,Settings,Tags,UserRound,CalendarDays,ChartNoAxesCombined,Menu,ChevronDown,ShieldCheck} from "lucide-react";

const available=[
 {href:"/admin",label:"Inicio",Icon:LayoutDashboard},
 {href:"/admin/propiedades",label:"Propiedades",Icon:Building2},
 {href:"/admin/leads",label:"Solicitudes / Leads",Icon:UsersRound},
 {href:"/admin/reportes",label:"Reportes",Icon:ChartNoAxesCombined},
 {href:"/admin/personalizar",label:"Personalizar",Icon:Settings}
];
const upcoming=[
 {label:"Categorías",Icon:Tags},
 {label:"Calendario",Icon:CalendarDays},
];

export function AdminNav({role="admin",roles=[role]}:{role?:string;roles?:string[]}){
 const pathname=usePathname();
 const mobileRef=useRef<HTMLDetailsElement>(null);
 const [mobileOpen,setMobileOpen]=useState(false);
 useEffect(()=>{const close=()=>{setMobileOpen(false);};close();const onPointer=(event:PointerEvent)=>{if(mobileRef.current&&!mobileRef.current.contains(event.target as Node))close();};const onKey=(event:KeyboardEvent)=>{if(event.key==="Escape")close();};document.addEventListener("pointerdown",onPointer);document.addEventListener("keydown",onKey);return ()=>{document.removeEventListener("pointerdown",onPointer);document.removeEventListener("keydown",onKey);};},[pathname]);
 const closeMobile=()=>setMobileOpen(false);
 const has=(r:string)=>roles.includes(r);
 const visible=available.filter(x=>has("admin")||(has("editor"))||(has("agente")&&["/admin","/admin/leads"].includes(x.href))||(has("lector")&&x.href==="/admin"));
 const items=()=> <>
  {visible.map(x=>{
   const selected=x.href==="/admin"?pathname===x.href:pathname===x.href||pathname.startsWith(x.href+"/");
   return <Link href={x.href} onClick={closeMobile} key={x.href} aria-current={selected?"page":undefined} className={selected?"active":undefined}><x.Icon size={18} aria-hidden="true"/>{x.label}</Link>;
  })}
  {has("admin")&&<Link href="/admin/clientes-agentes" onClick={closeMobile} className={pathname.startsWith("/admin/clientes-agentes")?"active":undefined}><ShieldCheck size={18}/>Clientes y agentes</Link>}
  {role==="admin"&&upcoming.map(x=><span key={x.label} title="Disponible próximamente" aria-disabled="true"><x.Icon size={18} aria-hidden="true"/>{x.label}</span>)}
 </>;
 return <>
  <nav className="admin-nav admin-nav-desktop" aria-label="Menú de administración">{items()}</nav>
  <details ref={mobileRef} className="admin-nav-mobile">
   <summary><Menu size={20} aria-hidden="true"/> <span>Menú</span><ChevronDown size={16} className="mobile-nav-chevron" aria-hidden="true"/></summary>
   <nav className="admin-nav" aria-label="Menú de administración móvil">{items()}</nav>
  </details>
 </>;
}
