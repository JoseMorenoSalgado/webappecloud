import {headers} from "next/headers";
import {redirect} from "next/navigation";
import {installerAvailable} from "@/lib/server-config";
import {normalizeSiteHost} from "@/lib/preview";
import InstallerForm from "@/components/installer-form";
export const dynamic="force-dynamic";
export default async function InstallPage(){
 if(!installerAvailable())redirect("/admin/acceso");
 const hostname=normalizeSiteHost((await headers()).get("host")||"");
 const ready=Boolean(process.env.INSTALLER_TOKEN&&process.env.INSTALLER_TOKEN.length>=24);
 return <main className="install-screen"><div className="install-wrap">
  <header className="install-header"><div className="install-logo">W</div><div><strong>WebAppECloud</strong><span>Asistente de instalación inicial</span></div></header>
  <div className="install-card">
   <div className="install-head"><span className="install-kicker">CONFIGURACIÓN INICIAL</span><h1>Bienvenido a tu plataforma</h1><p>Configura la base de datos, registra tu institución y crea el usuario que administrará tu sitio web.</p></div>
   {!ready?<div className="wizard-error"><strong>Instalador protegido.</strong> Antes de continuar, configura en Dockploy una variable secreta llamada <code>INSTALLER_TOKEN</code> con al menos 24 caracteres aleatorios y vuelve a cargar esta página.</div>:<InstallerForm domain={hostname}/>}
  </div>
  <p className="install-footer">WebAppECloud · Elearning Cloud · La configuración se guarda fuera del código público.</p>
 </div></main>;
}
