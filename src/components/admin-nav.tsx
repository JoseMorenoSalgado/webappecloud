"use client";
import Link from "next/link";
import {usePathname} from "next/navigation";
import {LayoutDashboard,Building2,UsersRound,Settings,Tags,UserRound,CalendarDays,ChartNoAxesCombined} from "lucide-react";
const available=[{href:"/admin",label:"Inicio",Icon:LayoutDashboard},{href:"/admin/propiedades",label:"Propiedades",Icon:Building2},{href:"/admin/leads",label:"Solicitudes / Leads",Icon:UsersRound}];
const upcoming=[{label:"Categorías",Icon:Tags},{label:"Clientes y agentes",Icon:UserRound},{label:"Calendario",Icon:CalendarDays},{label:"Reportes avanzados",Icon:ChartNoAxesCombined},{label:"Configuración",Icon:Settings}];
export function AdminNav(){const pathname=usePathname();return <nav className="admin-nav" aria-label="Menú de administración">{available.map(x=><Link href={x.href} key={x.href} className={pathname===x.href?"active":undefined}><x.Icon size={18}/>{x.label}</Link>)}{upcoming.map(x=><span key={x.label} title="Módulo de próxima fase"><x.Icon size={18}/>{x.label}</span>)}</nav>;}
