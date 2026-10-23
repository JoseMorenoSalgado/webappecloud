import Link from "next/link";
import {requireAdmin} from "@/lib/auth";
import {signOut} from "../actions";
import {AdminNav} from "@/components/admin-nav";
import {Building2,ExternalLink,Bell} from "lucide-react";
import {commercialNotifications} from "@/lib/commercial-notifications";
export const dynamic="force-dynamic";
export default async function DashboardLayout({children}:{children:React.ReactNode}){
 const admin=await requireAdmin();
 const isNexus=admin.tenantName.toLowerCase()==="nexus real estate";
 let alertCount=0;
 if(admin.roles.some(role=>["admin","editor","agente"].includes(role))){
  try{alertCount=(await commercialNotifications(admin,{limit:0})).total;}
  catch(error){
   console.warn("[notifications] counters unavailable",error instanceof Error?error.name:"unknown");
  }
 }
 return <div className="admin-shell"><aside className="admin-sidebar"><Link href="/admin" className="brand"><span className="brand-icon">{isNexus?<img src="/brand/nexus-icon.webp" alt=""/>:<Building2 size={28}/>}</span><span><strong>{isNexus?"NEXUS":admin.tenantName}</strong><small>{isNexus?"REAL ESTATE":"Mi sitio web"}</small></span></Link><AdminNav role={admin.role} roles={admin.roles} userId={admin.userId} notificationCount={alertCount}/><div className="admin-sidebar-bottom"><p>Tu página pública</p><Link href="/" className="inline-link" target="_blank">Visitar sitio <ExternalLink size={14}/></Link></div></aside><div className="admin-main"><header className="admin-topbar"><div><strong>{admin.tenantName}</strong><br/><span>Administración del sitio web</span></div><div className="admin-session">
 {admin.roles.some(role=>["admin","editor","agente"].includes(role))&&<Link href="/admin/notificaciones" className="admin-notification-trigger"
 aria-label={alertCount?"Abrir "+alertCount+" gestiones pendientes":"Abrir centro de notificaciones"}>
  <Bell size={19}/>{alertCount>0&&<span className="admin-notification-count">{alertCount>99?"99+":alertCount}</span>}
 </Link>}<span>{admin.name}</span><form action={signOut}><button>Cerrar sesión</button></form></div></header><div className="admin-body">{children}</div></div></div>;
}
