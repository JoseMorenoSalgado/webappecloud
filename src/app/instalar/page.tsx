import {headers} from "next/headers";
import {redirect} from "next/navigation";
import {installerAvailable,installerToken} from "@/lib/server-config";
import {normalizeSiteHost} from "@/lib/preview";
import InstallerForm from "@/components/installer-form";
import {APP_RELEASE} from "@/lib/release";
export const dynamic="force-dynamic";
export default async function InstallPage(){
 if(!installerAvailable())redirect("/admin/acceso");
 const hostname=normalizeSiteHost((await headers()).get("host")||"");
 let ready=false;
 try{ready=Boolean(installerToken());}
 catch(error){console.error("No se pudo preparar el almacenamiento del instalador",error instanceof Error?error.message:"Error desconocido");}
 return <main className="install-screen"><div className="install-wrap">
  <header className="install-header"><div className="install-logo">W</div><div><strong>WebAppECloud</strong><span>Asistente de instalación inicial</span></div></header>
  <div className="install-card">
   <div className="install-head"><span className="install-kicker">CONFIGURACIÓN INICIAL</span><h1>Bienvenido a tu plataforma</h1><p>Configura la base de datos, registra tu institución y crea el usuario que administrará tu sitio web.</p></div>
   {!ready?<div className="wizard-error"><strong>Instalador protegido.</strong> No fue posible crear la clave de instalación. Comprueba los permisos y el volumen persistente de <code>/app/.runtime</code>.</div>:<InstallerForm domain={hostname}/>}
  </div>
  <p className="install-footer">WebAppECloud · Elearning Cloud · Versión {APP_RELEASE} · Configuración privada.</p>
 </div></main>;
}
