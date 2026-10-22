import Link from "next/link";
import {Building2,ExternalLink,ImageIcon,LayoutTemplate,Save,Star,MessageCircle} from "lucide-react";
import {requireRole} from "@/lib/auth";
import {prisma} from "@/lib/prisma";
import {getSiteSettings} from "@/lib/site-settings";
import {hasSiteAsset} from "@/lib/site-assets";
import {SiteAssetManager} from "@/components/site-asset-manager";
export const dynamic="force-dynamic";
type Status={saved?:string;error?:string};
const errors:Record<string,string>={
 permisos:"Esta cuenta no puede modificar la página pública.",
 origen:"No se pudo validar el formulario. Vuelve a abrir esta página.",
 datos:"Revisa los campos de la portada, los colores y los datos de contacto.",
 destacadas:"Las propiedades destacadas deben pertenecer a esta inmobiliaria y estar publicadas.",
 servidor:"No se pudieron guardar los cambios. Consulta los registros en Dokploy."
};
export default async function SiteEditor({searchParams}:{searchParams:Promise<Status>}){
 const admin=await requireRole(["admin","editor"]);
 const [settings,tenant,properties,heroExists,logoExists,q]=await Promise.all([
  getSiteSettings(admin.tenantId),
  prisma.tenant.findUniqueOrThrow({where:{id:admin.tenantId},select:{tagline:true,whatsapp:true,contactEmail:true,primaryColor:true}}),
  prisma.property.findMany({where:{tenantId:admin.tenantId,published:true},
   select:{id:true,refCode:true,title:true},orderBy:{createdAt:"desc"},take:200}),
  hasSiteAsset(admin.tenantId,"hero"),hasSiteAsset(admin.tenantId,"logo"),
  searchParams
 ]);
 const canEdit=admin.roles.some(r=>["admin","editor"].includes(r));
 return <>
  <div className="admin-heading">
   <div><Link href="/admin/personalizar" className="inline-link">← Personalizar</Link><h1>Editar contenido e identidad</h1><p>Edita la portada, las imágenes y los datos de contacto sin modificar código.</p></div>
   <Link href="/" target="_blank" className="btn wizard-back">Ver página pública <ExternalLink size={16}/></Link>
  </div>
  {q.saved&&<p role="status" className="property-success">Cambios guardados. Ya aparecen en el sitio público.</p>}
  {q.error&&<p role="alert" className="admin-error">{errors[q.error]||errors.servidor}</p>}
  <section id="identidad" className="admin-panel site-editor-images">
   <div className="site-section-heading"><ImageIcon size={21}/><div><h2>Identidad visual y fotografías</h2><p>Las imágenes se actualizan por separado y permanecen en el volumen privado de la aplicación.</p></div></div>
   <div className="site-image-grid">
    <div><h3>Banner principal</h3><SiteAssetManager kind="hero" exists={heroExists} canEdit={canEdit}/></div>
    <div><h3>Logotipo</h3><SiteAssetManager kind="logo" exists={logoExists} canEdit={canEdit}/></div>
   </div>
  </section>
  {canEdit?<form className="site-editor-form" method="post" action="/api/admin/site-settings">
   <section id="inicio" className="admin-panel">
    <div className="site-section-heading"><LayoutTemplate size={21}/><div><h2>Portada principal</h2><p>Textos del banner que verá cada visitante al entrar en tu página.</p></div></div>
    <div className="site-form-fields">
     <label>Texto superior<input name="heroEyebrow" defaultValue={settings.heroEyebrow} maxLength={90} required/></label>
     <div className="admin-form-grid">
      <label>Título principal<input name="heroHeading" defaultValue={settings.heroHeading} maxLength={95} required/></label>
      <label>Frase destacada<input name="heroHighlight" defaultValue={settings.heroHighlight} maxLength={90} required/></label>
     </div>
     <label>Descripción<textarea name="heroDescription" defaultValue={settings.heroDescription} maxLength={480} required/></label>
     <label>Texto del botón<input name="heroButton" defaultValue={settings.heroButton} maxLength={45} required/></label>
    </div>
   </section>
   <section id="propiedades" className="admin-panel">
    <div className="site-section-heading"><Building2 size={21}/><div><h2>Presentación de propiedades</h2><p>Encabezados, descripción y selección de inmuebles destacados en el inicio.</p></div></div>
    <div className="site-form-fields">
     <label>Etiqueta de sección<input name="sectionEyebrow" maxLength={90} defaultValue={settings.sectionEyebrow} required/></label>
     <div className="admin-form-grid">
      <label>Título<input name="sectionHeading" maxLength={95} defaultValue={settings.sectionHeading} required/></label>
      <label>Texto destacado<input name="sectionHighlight" maxLength={90} defaultValue={settings.sectionHighlight} required/></label>
     </div>
     <label>Descripción<textarea name="sectionDescription" maxLength={320} defaultValue={settings.sectionDescription} required/></label>
     <div className="site-featured-picker"><strong><Star size={17}/> Propiedades destacadas</strong>
      <p>Selecciona hasta tres inmuebles publicados. Sin selección, se mostrarán los inmuebles marcados como destacados y los más recientes.</p>
      {properties.length?properties.map(p=><label key={p.id}>
       <input type="checkbox" name="featuredIds" value={p.id} defaultChecked={settings.featuredIds.includes(p.id)}/>
       <span><strong>{p.refCode}</strong> · {p.title}</span>
      </label>):<p className="property-help">Primero publica un inmueble desde el módulo de Propiedades.</p>}
     </div>
    </div>
   </section>
   <section id="nosotros" className="admin-panel">
    <div className="site-section-heading"><MessageCircle size={21}/><div><h2>Quiénes somos y contacto</h2><p>Actualiza los mensajes comerciales y la información de atención al cliente.</p></div></div>
    <div className="site-form-fields" id="contacto">
     <label>Título de presentación<input name="aboutTitle" maxLength={100} defaultValue={settings.aboutTitle} required/></label>
     <label>Descripción de presentación<textarea name="aboutDescription" maxLength={360} defaultValue={settings.aboutDescription} required/></label>
     <label>Nuestra misión<textarea name="aboutMission" maxLength={400} defaultValue={settings.aboutMission} required/></label>
     <label>Nuestra visión<textarea name="aboutVision" maxLength={400} defaultValue={settings.aboutVision} required/></label>
     <div className="admin-form-grid">
      <label>Título de contacto<input name="contactHeading" maxLength={100} defaultValue={settings.contactHeading} required/></label>
      <label>Frase destacada de contacto<input name="contactHighlight" maxLength={100} defaultValue={settings.contactHighlight} required/></label>
     </div>
     <label>Descripción de contacto<textarea name="contactDescription" maxLength={360} defaultValue={settings.contactDescription} required/></label>
     <div className="admin-form-grid">
      <label>WhatsApp (con código internacional)<input name="whatsapp" inputMode="tel" maxLength={35} defaultValue={tenant.whatsapp||""} placeholder="50588888888"/></label>
      <label>Correo de contacto<input type="email" name="contactEmail" maxLength={180} defaultValue={tenant.contactEmail||""} placeholder="contacto@empresa.com"/></label>
     </div>
     <div className="admin-form-grid">
      <label>Lema institucional<input name="tagline" maxLength={110} defaultValue={tenant.tagline||""}/></label>
      <label>Color principal<input name="primaryColor" type="color" defaultValue={tenant.primaryColor||"#CB2027"}/></label>
     </div>
    </div>
   </section>
   <section id="servicios" className="admin-panel">
    <div className="site-section-heading"><Building2 size={21}/><div><h2>Página de servicios</h2><p>Mensajes que aparecerán en la nueva sección independiente «Servicios».</p></div></div>
    <div className="site-form-fields">
     <label>Título de servicios<input name="servicesHeading" maxLength={110} defaultValue={settings.servicesHeading} required/></label>
     <label>Introducción<textarea name="servicesDescription" maxLength={380} defaultValue={settings.servicesDescription} required/></label>
     <label>Servicio de compra y venta<textarea name="servicesSale" maxLength={400} defaultValue={settings.servicesSale} required/></label>
     <label>Servicio de alquiler<textarea name="servicesRent" maxLength={400} defaultValue={settings.servicesRent} required/></label>
     <label>Asesoría inmobiliaria<textarea name="servicesAdvice" maxLength={400} defaultValue={settings.servicesAdvice} required/></label>
    </div>
   </section>
   <div className="site-save-bar"><p>Los textos y datos de contacto se publican al guardar. Las fotografías se guardan al subirlas.</p>
    <button type="submit" className="btn btn-red"><Save size={17}/> Guardar y publicar cambios</button></div>
  </form>:<div className="admin-error">Tu cuenta tiene acceso de consulta, pero no permisos para editar el sitio.</div>}
 </>;
}
