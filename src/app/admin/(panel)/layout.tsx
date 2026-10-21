import Link from "next/link";
import {requireAdmin} from "@/lib/auth";
import {signOut} from "../actions";
import {AdminNav} from "@/components/admin-nav";
import {Building2,ExternalLink} from "lucide-react";
export const dynamic="force-dynamic";
export default async function DashboardLayout({children}:{children:React.ReactNode}){
 const admin=await requireAdmin();
 const isNexus=admin.tenantName.toLowerCase()==="nexus real estate";
 return <div className="admin-shell"><aside className="admin-sidebar"><Link href="/admin" className="brand"><span className="brand-icon">{isNexus?<img src="/brand/nexus-icon.webp" alt=""/>:<Building2 size={28}/>}</span><span><strong>{isNexus?"NEXUS":admin.tenantName}</strong><small>{isNexus?"REAL ESTATE":"Mi sitio web"}</small></span></Link><AdminNav/><div className="admin-sidebar-bottom"><p>Tu página pública</p><Link href="/" className="inline-link" target="_blank">Visitar sitio <ExternalLink size={14}/></Link></div></aside><div className="admin-main"><header className="admin-topbar"><div><strong>{admin.tenantName}</strong><br/><span>Administración del sitio web</span></div><div style={{display:"flex",alignItems:"center",gap:16}}><span>{admin.name}</span><form action={signOut}><button>Cerrar sesión</button></form></div></header><div className="admin-body">{children}</div></div></div>;
}
