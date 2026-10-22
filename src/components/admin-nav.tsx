"use client";
import Link from "next/link";
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

export function AdminNav({role="admin"}:{role?:string}){
 const pathname=usePathname();
 const visible=available.filter(x=>role==="admin"|| (role==="editor"&&x.href!=="/admin/reportes")||(role==="agente"&&["/admin","/admin/leads"].includes(x.href))||(role==="lector"&&x.href==="/admin"));
 const items=()=> <>
  {visible.map(x=>{
   const selected=x.href==="/admin"?pathname===x.href:pathname===x.href||pathname.startsWith(x.href+"/");
   return <Link href={x.href} key={x.href} aria-current={selected?"page":undefined} className={selected?"active":undefined}><x.Icon size={18} aria-hidden="true"/>{x.label}</Link>;
  })}
  {role==="admin"&&<Link href="/admin/clientes-agentes" className={pathname.startsWith("/admin/clientes-agentes")?"active":undefined}><ShieldCheck size={18}/>Clientes y agentes</Link>}
  {role==="admin"&&upcoming.map(x=><span key={x.label} title="Disponible próximamente" aria-disabled="true"><x.Icon size={18} aria-hidden="true"/>{x.label}</span>)}
 </>;
 return <>
  <nav className="admin-nav admin-nav-desktop" aria-label="Menú de administración">{items()}</nav>
  <details className="admin-nav-mobile">
   <summary><Menu size={20} aria-hidden="true"/> <span>Menú</span><ChevronDown size={16} className="mobile-nav-chevron" aria-hidden="true"/></summary>
   <nav className="admin-nav" aria-label="Menú de administración móvil">{items()}</nav>
  </details>
 </>;
}
