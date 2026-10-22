import Link from "next/link";
import {notFound} from "next/navigation";
import {ArrowLeft,ExternalLink,Save,Building2} from "lucide-react";
import {requireAdmin} from "@/lib/auth";
import {getCustomPage,textBlocks} from "@/lib/site-pages";
import {getSiteSettings} from "@/lib/site-settings";
import {prisma} from "@/lib/prisma";
export const dynamic="force-dynamic";
type Field={name:string;label:string;max:number;multiline?:boolean;hint?:string};
type Builtin="home"|"propiedades"|"nosotros"|"servicios"|"contacto";
const sections:Record<Builtin,{title:string;description:string;path:string;fields:Field[]}>={
 home:{title:"Página de inicio",description:"Edita exclusivamente los textos del banner principal. Otros contenidos permanecen como estaban.",path:"/",
  fields:[
   {name:"heroEyebrow",label:"Texto superior",max:90},
   {name:"heroHeading",label:"Título principal",max:95},
   {name:"heroHighlight",label:"Frase destacada",max:90},
   {name:"heroDescription",label:"Descripción del banner",max:480,multiline:true},
   {name:"heroButton",label:"Texto del botón principal",max:45}
  ]},
 propiedades:{title:"Propiedades",description:"Configura la presentación de propiedades destacadas del inicio; el catálogo y las fotografías se administran en Propiedades.",path:"/propiedades",
  fields:[
   {name:"sectionEyebrow",label:"Etiqueta de sección",max:90},
   {name:"sectionHeading",label:"Título de propiedades destacadas",max:95},
   {name:"sectionHighlight",label:"Frase destacada",max:90},
   {name:"sectionDescription",label:"Descripción de la sección",max:320,multiline:true}
  ]},
 nosotros:{title:"Nosotros",description:"Administra los textos institucionales que aparecen en la página Nosotros y en la presentación de la portada.",path:"/nosotros",
  fields:[
   {name:"aboutTitle",label:"Título institucional",max:100},
   {name:"aboutDescription",label:"Descripción de la empresa",max:360,multiline:true},
   {name:"aboutMission",label:"Misión",max:400,multiline:true},
   {name:"aboutVision",label:"Visión",max:400,multiline:true}
  ]},
 servicios:{title:"Servicios",description:"Cada servicio tiene su propio contenido y se muestra en la página Servicios.",path:"/servicios",
  fields:[
   {name:"servicesHeading",label:"Encabezado de la página",max:110},
   {name:"servicesDescription",label:"Introducción",max:380,multiline:true},
   {name:"servicesSale",label:"Compra y venta",max:400,multiline:true},
   {name:"servicesRent",label:"Alquileres",max:400,multiline:true},
   {name:"servicesAdvice",label:"Asesoría inmobiliaria",max:400,multiline:true}
  ]},
 contacto:{title:"Contacto",description:"Edita los mensajes y los canales de contacto que utilizarán tus visitantes.",path:"/contacto",
  fields:[
   {name:"contactHeading",label:"Título principal",max:100},
   {name:"contactHighlight",label:"Frase destacada",max:100},
   {name:"contactDescription",label:"Texto de invitación",max:360,multiline:true}
  ]}
};
const isBuiltin=(s:string):s is Builtin=>Object.prototype.hasOwnProperty.call(sections,s);
const canEditRole=(r:string)=>r==="admin"||r==="editor";
export default async function EditSitePage({params,searchParams}:{params:Promise<{slug:string}>;searchParams:Promise<{saved?:string;created?:string;error?:string}>}){
 const admin=await requireAdmin();
 const {slug}=await params,q=await searchParams;
 const canEdit=canEditRole(admin.role);
 if(isBuiltin(slug)){
  const [settings,properties,tenant]=await Promise.all([
   getSiteSettings(admin.tenantId),
   slug==="propiedades"?prisma.property.findMany({where:{tenantId:admin.tenantId,published:true},
    select:{id:true,refCode:true,title:true},orderBy:{createdAt:"desc"},take:200}):Promise.resolve([]),
   slug==="contacto"?prisma.tenant.findUniqueOrThrow({where:{id:admin.tenantId},select:{contactEmail:true,whatsapp:true}}):Promise.resolve(null)
  ]);
  const section=sections[slug];
  return <>
   <div className="admin-heading"><div><Link href="/admin/personalizar/paginas" className="inline-link"><ArrowLeft size={16}/> Todas las páginas</Link>
    <h1>Editar: {section.title}</h1><p>{section.description}</p></div>
    <Link className="btn wizard-back" href={section.path} target="_blank">Ver página <ExternalLink size={16}/></Link>
   </div>
   {q.saved&&<p role="status" className="property-success">Cambios de {section.title} guardados correctamente. No se modificaron las demás páginas.</p>}
   {q.error&&<p role="alert" className="admin-error">No se pudieron guardar los cambios. Verifica los datos y tus permisos.</p>}
   {canEdit?<form className="admin-panel site-page-editor site-section-independent" action="/api/admin/site-section" method="post">
    <input type="hidden" name="section" value={slug}/>
    <h2>Contenido de {section.title.toLowerCase()}</h2>
    {section.fields.map(f=><label key={f.name}>{f.label}
     {f.multiline?<textarea name={f.name} maxLength={f.max} rows={4} required defaultValue={settings[f.name as keyof typeof settings] as string}/>:
      <input name={f.name} maxLength={f.max} required defaultValue={settings[f.name as keyof typeof settings] as string}/>}
     {f.hint&&<small>{f.hint}</small>}
    </label>)}
    {slug==="propiedades"&&<div className="site-featured-picker">
     <strong>Elegir propiedades destacadas (máximo 3)</strong>
     <p>Estas propiedades aparecen en la portada. Solo se pueden destacar inmuebles publicados.</p>
     {properties.map(p=><label key={p.id}>
      <input type="checkbox" name="featuredIds" value={p.id} defaultChecked={settings.featuredIds.includes(p.id)}/>
      <span><strong>{p.refCode}</strong> · {p.title}</span>
     </label>)}
     {!properties.length&&<p>Aún no hay inmuebles publicados.</p>}
     <Link className="inline-link" href="/admin/propiedades">Administrar inmuebles <Building2 size={15}/></Link>
    </div>}
    {slug==="contacto"&&<div className="site-page-contact-fields">
     <label>WhatsApp (con código de país)<input name="whatsapp" maxLength={35} defaultValue={tenant?.whatsapp||""} placeholder="50588888888" inputMode="tel"/></label>
     <label>Correo electrónico<input type="email" name="contactEmail" maxLength={180} defaultValue={tenant?.contactEmail||""} placeholder="contacto@empresa.com"/></label>
    </div>}
    <div className="site-page-form-actions"><button type="submit" className="btn btn-red"><Save size={17}/> Guardar esta página</button>
     <Link className="btn wizard-back" href="/admin/personalizar/paginas">Volver a páginas</Link></div>
   </form>:<p className="admin-error">Tu cuenta no tiene permisos de edición.</p>}
   <p className="property-help">Las propiedades, menú y colores corporativos se administran por separado para evitar modificaciones accidentales.</p>
  </>;
 }
 const page=await getCustomPage(admin.tenantId,slug);
 if(!page)notFound();
 const blocks=textBlocks(page.blocks);
 return <>
  <div className="admin-heading"><div><Link className="inline-link" href="/admin/personalizar/paginas"><ArrowLeft size={16}/> Todas las páginas</Link>
   <h1>Editar: {page.title}</h1><p>Página independiente con su propio contenido y estado de publicación.</p></div>
   {page.published&&<Link href={"/pagina/"+slug} target="_blank" className="btn wizard-back">Ver página <ExternalLink size={16}/></Link>}</div>
  {(q.saved||q.created)&&<p className="property-success" role="status">Página {q.created?"creada":"actualizada"} correctamente.</p>}
  {q.error&&<p className="admin-error" role="alert">No se pudo guardar la página. Comprueba los textos y el estado de publicación.</p>}
  {canEdit?<form className="admin-panel site-page-editor" method="post" action={"/api/admin/site-pages/"+slug}>
   <label>Título<input name="title" required minLength={3} maxLength={90} defaultValue={page.title}/></label>
   <p className="property-help">Dirección pública: /pagina/{slug} · No se modifica para conservar los enlaces existentes.</p>
   <label>Descripción breve<textarea name="summary" maxLength={240} rows={3} defaultValue={blocks.summary}/></label>
   <label>Contenido principal<textarea name="body" maxLength={10000} rows={13} defaultValue={blocks.body}/></label>
   <label className="site-page-check"><input name="published" type="checkbox" defaultChecked={page.published}/> Página publicada</label>
   <div className="site-page-form-actions"><button className="btn btn-red"><Save size={17}/> Guardar página</button>
    <Link href="/admin/personalizar/menu" className="btn wizard-back">Organizar menú</Link></div>
  </form>:<p className="admin-error">No tienes permisos para editar.</p>}
 </>;
}
