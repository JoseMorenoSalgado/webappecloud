import type {Metadata} from "next";
import Link from "next/link";
import {notFound} from "next/navigation";
import {ArrowRight,ArrowUpRight,BookOpenCheck,BriefcaseBusiness,CheckCircle2,ClipboardCheck,GraduationCap,MessageCircle,ShieldCheck} from "lucide-react";
import {getPublicContext} from "@/lib/tenant";
import {getSiteTemplate} from "@/lib/site-template-store";
import {getPageDefinitions} from "@/lib/site-pages";
import {tenantModuleEnabled} from "@/lib/tenant-modules";
import {PublicFrame} from "@/components/public-frame";
import {ContactForm} from "@/components/contact-form";
import {NexusInnerMotion} from "@/components/nexus-inner-motion";
import {PageExtraSections} from "@/components/page-extra-sections";

export const dynamic="force-dynamic";
export const metadata:Metadata={
 title:"Asesoría empresarial y cumplimiento normativo",
 description:"Auditorías externas de cumplimiento, asesoramiento y capacitación en prevención de LA/FT/FP para sujetos obligados en Nicaragua."
};
const areas=[
 {number:"01",Icon:ClipboardCheck,title:"Auditorías externas de cumplimiento",description:"Revisión independiente de políticas, procesos, controles y documentación relacionados con las obligaciones de prevención de LA/FT/FP, según el alcance acordado."},
 {number:"02",Icon:ShieldCheck,title:"Asesoría en prevención y cumplimiento",description:"Orientación para fortalecer controles internos, procedimientos y gestión de riesgos, considerando la normativa que resulte aplicable a cada organización."},
 {number:"03",Icon:GraduationCap,title:"Capacitaciones para sujetos obligados",description:"Formación dirigida a responsables de cumplimiento, equipos directivos y colaboradores sobre medidas preventivas, obligaciones y buenas prácticas."}
];
export default async function AdvisoryPage(){
 const {tenant,demo}=await getPublicContext({loadProperties:false});
 const template=await getSiteTemplate(tenant.id);
 if(template.key!=="real-estate")notFound();
 if(!demo){
  const pages=await getPageDefinitions(tenant.id);
  if(!pages.some(p=>p.id==="asesoria-empresarial"&&p.published))notFound();
 }
 const inquiriesEnabled=!demo&&await tenantModuleEnabled(tenant.id,"inquiries");
 const phone=tenant.whatsapp.replace(/\D/g,"");
 const whatsapp=phone?"https://wa.me/"+phone+"?text="+encodeURIComponent("Hola, deseo solicitar información sobre la asesoría empresarial, auditorías externas o capacitación en prevención LA/FT/FP."):null;
 return <PublicFrame tenant={tenant} demo={demo}>
  <main id="contenido" className="nexus-advisory" data-nexus-inner-motion="asesoria-empresarial"><NexusInnerMotion/>
   <section className="nexus-inner-hero nexus-advisory-hero">
    <div className="container nexus-advisory-hero-grid">
     <div>
      <span className="nexus-section-kicker nexus-kicker-light">SERVICIOS PROFESIONALES · NICARAGUA</span>
      <h1>Asesoría empresarial y <em>cumplimiento normativo.</em></h1>
      <p>Auditorías externas, acompañamiento técnico y formación en prevención del lavado de activos, financiamiento al terrorismo y financiamiento de la proliferación de armas de destrucción masiva.</p>
      <div className="nexus-advisory-actions">
       <a href="#solicitar-asesoria" className="btn nexus-hero-primary">Solicitar asesoría <ArrowUpRight size={18}/></a>
       <a href="#nuestros-servicios" className="nexus-advisory-hero-link">Conocer los servicios <ArrowRight size={17}/></a>
      </div>
     </div>
     <div className="nexus-advisory-hero-aside" aria-label="Áreas de asesoría">
      <span className="nexus-advisory-aside-label">ENFOQUE DE TRABAJO</span>
      <div><ClipboardCheck size={22}/><span>Auditorías de cumplimiento</span></div>
      <div><ShieldCheck size={22}/><span>Gestión y prevención de riesgos</span></div>
      <div><BookOpenCheck size={22}/><span>Formación especializada</span></div>
      <span className="nexus-advisory-aside-footer">LA / FT / FP</span>
     </div>
    </div>
   </section>

   <section className="nexus-advisory-section" id="nuestros-servicios" aria-labelledby="nexus-advisory-services-title">
    <div className="container">
     <div className="nexus-advisory-heading">
      <span className="nexus-section-kicker">QUÉ HACEMOS</span>
      <h2 id="nexus-advisory-services-title">Servicios para una gestión <em>responsable.</em></h2>
      <p>Apoyamos a las organizaciones en la evaluación, comprensión y aplicación de medidas de prevención de riesgos, de acuerdo con su actividad y obligaciones específicas.</p>
     </div>
     <div className="nexus-advisory-services">
      {areas.map(({number,Icon,title,description})=><article key={number} className="nexus-advisory-service">
       <div className="nexus-advisory-service-symbol"><Icon size={24}/></div>
       <div><span>{number} / SERVICIO</span><h3>{title}</h3><p>{description}</p></div>
       <a href="#solicitar-asesoria" aria-label={"Consultar sobre "+title}>Consultar <ArrowUpRight size={16}/></a>
      </article>)}
     </div>
    </div>
   </section>

   <section className="nexus-advisory-section nexus-advisory-audience">
    <div className="container nexus-advisory-two-columns">
     <div>
      <span className="nexus-section-kicker">A QUIÉNES NOS DIRIGIMOS</span>
      <h2>Capacitación orientada a <em>sujetos obligados.</em></h2>
      <p>Los programas están dirigidos a personas naturales y jurídicas sujetas a obligaciones de prevención de LA/FT/FP, así como a los equipos responsables de aplicarlas.</p>
      <p>Los contenidos y el alcance de cada asesoría se ajustan al sector, perfil de riesgo y requerimientos normativos de la organización.</p>
     </div>
     <div className="nexus-advisory-checklist">
      <h3>Áreas que pueden abordarse</h3>
      <ul>
       <li><CheckCircle2 size={18}/> Identificación y valoración de riesgos</li>
       <li><CheckCircle2 size={18}/> Políticas y procedimientos internos</li>
       <li><CheckCircle2 size={18}/> Debida diligencia y controles preventivos</li>
       <li><CheckCircle2 size={18}/> Responsabilidades y capacitación del personal</li>
      </ul>
     </div>
    </div>
   </section>

   <section className="nexus-advisory-section nexus-advisory-laws">
    <div className="container nexus-advisory-two-columns">
     <div>
      <span className="nexus-section-kicker">MARCO NORMATIVO</span>
      <h2>Referencias legales de <em>Nicaragua.</em></h2>
      <p>Nuestros servicios de capacitación y orientación consideran el marco jurídico aplicable, incluidas sus reformas y normas regulatorias complementarias.</p>
     </div>
     <div className="nexus-advisory-law-list">
      <div><span>LEY N.º 977</span><strong>Prevención del lavado de activos, financiamiento al terrorismo y financiamiento de la proliferación de armas de destrucción masiva.</strong></div>
      <div><span>LEY N.º 976</span><strong>Ley de la Unidad de Análisis Financiero (UAF).</strong></div>
      <a href="https://www.uaf.gob.ni/la-uaf/base-legal" target="_blank" rel="noopener noreferrer">Consultar el marco legal en la UAF <ArrowUpRight size={16}/></a>
     </div>
    </div>
    <div className="container"><p className="nexus-advisory-disclaimer">El alcance de cada servicio está sujeto a evaluación y contratación. La asesoría ofrecida no sustituye las competencias de las autoridades reguladoras o supervisoras.</p></div>
   </section>

   <section className="nexus-advisory-section nexus-advisory-contact" id="solicitar-asesoria">
    <div className="container nexus-advisory-contact-layout">
     <div className="nexus-advisory-contact-copy">
      <span className="nexus-section-kicker nexus-kicker-light">HABLEMOS DE TU ORGANIZACIÓN</span>
      <h2>Conversemos sobre tus <em>necesidades de cumplimiento.</em></h2>
      <p>Cuéntanos si necesitas una auditoría externa, orientación o un programa de capacitación. Nuestro equipo podrá coordinar los siguientes pasos contigo.</p>
      {whatsapp&&<a className="nexus-advisory-whatsapp" href={whatsapp} target="_blank" rel="noopener noreferrer"><MessageCircle size={20}/> Consultar por WhatsApp <ArrowUpRight size={17}/></a>}
      {tenant.contactEmail&&<a className="nexus-advisory-email" href={"mailto:"+tenant.contactEmail}>{tenant.contactEmail}</a>}
     </div>
     <div className="nexus-advisory-form">
      <span className="nexus-section-kicker">SOLICITAR INFORMACIÓN</span>
      <h3>¿En qué podemos ayudarte?</h3>
      <p>Déjanos tus datos y una descripción de la consulta.</p>
      {demo?<p className="preview-form-note">El formulario se habilitará al configurar el sitio.</p>:
       inquiriesEnabled?<ContactForm contextLabel="Asesoría empresarial · LA/FT/FP" messagePlaceholder="Cuéntanos el tipo de auditoría, asesoría o capacitación que necesitas"/>:
       <p>El formulario de consultas está desactivado. Utiliza los canales de contacto disponibles.</p>}
     </div>
    </div>
   </section>
   <PageExtraSections tenantId={tenant.id} slug="asesoria-empresarial" demo={demo}/>
  </main>
 </PublicFrame>;
}
