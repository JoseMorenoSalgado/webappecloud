import Link from "next/link";
import {ArrowUpRight,FileText,ListTree,Palette,Plus,Search,ExternalLink} from "lucide-react";
import {requireAdmin} from "@/lib/auth";
import {getPageDefinitions,getSiteMenu} from "@/lib/site-pages";
export const dynamic="force-dynamic";
type Query={saved?:string;error?:string;estado?:string;buscar?:string};
export default async function PagesAdmin({searchParams}:{searchParams:Promise<Query>}){
 const admin=await requireAdmin(),q=await searchParams;
 const [pages,menu]=await Promise.all([getPageDefinitions(admin.tenantId),getSiteMenu(admin.tenantId)]);
 const canEdit=["admin","editor"].includes(admin.role);
 const search=(q.buscar||"").trim().slice(0,90).toLocaleLowerCase("es");
 const status=q.estado==="publicadas"?"publicadas":q.estado==="borradores"?"borradores":"todas";
 const results=pages.filter(p=>(status==="todas"||(status==="publicadas"?p.published:!p.published))&&
  (!search||[p.title,p.href,p.summary].join(" ").toLocaleLowerCase("es").includes(search)));
 const published=pages.filter(p=>p.published).length;
 const tabs=[{id:"todas",label:"Todas",total:pages.length},{id:"publicadas",label:"Publicadas",total:published},{id:"borradores",label:"Borradores",total:pages.length-published}];
 return <>
  <div className="admin-heading cms-heading"><div><h1>Páginas</h1>
   <p>Gestiona las páginas de tu sitio web: edita el contenido, publica o guarda borradores.</p></div>
   {canEdit&&<Link href="/admin/personalizar/paginas/nueva" className="btn btn-red"><Plus size={17}/> Nueva página</Link>}
  </div>
  <nav aria-label="Herramientas de personalización" className="cms-toolbar">
   <Link href="/admin/personalizar/paginas" className="cms-toolbar-current"><FileText size={17}/> Páginas</Link>
   <Link href="/admin/personalizar/menu"><ListTree size={17}/> Menú principal</Link>
   <Link href="/admin/personalizar/identidad"><Palette size={17}/> Identidad visual</Link>
   <Link href="/" target="_blank" className="cms-toolbar-visit">Ver sitio <ExternalLink size={16}/></Link>
  </nav>
  {q.saved&&<p className="property-success" role="status">Estado de publicación actualizado correctamente.</p>}
  {q.error&&<p className="admin-error" role="alert">No se pudieron actualizar los datos. Comprueba los permisos.</p>}
  <section className="admin-panel cms-list-panel">
   <div className="cms-list-controls">
    <div className="cms-status-tabs">{tabs.map(t=><Link href={"/admin/personalizar/paginas?estado="+t.id} key={t.id}
      aria-current={status===t.id?"page":undefined}>{t.label} <span>{t.total}</span></Link>)}</div>
    <form action="/admin/personalizar/paginas" method="get" className="cms-search">
     <input type="hidden" name="estado" value={status}/>
     <label><Search size={16}/><input aria-label="Buscar páginas" name="buscar" defaultValue={q.buscar||""} placeholder="Buscar páginas" maxLength={90}/></label>
     <button type="submit" className="btn wizard-back">Buscar</button>
    </form>
   </div>
   <div className="cms-table-wrap">
    <table className="cms-page-table">
     <thead><tr><th>Página</th><th>Estado</th><th>Dirección</th><th>Acciones</th></tr></thead>
     <tbody>{results.map(p=><tr key={p.id}>
      <td className="cms-page-name"><Link href={"/admin/personalizar/paginas/"+p.slug}>{p.title}</Link>
       <small>{p.builtIn?"Página del sitio":"Página personalizada"}</small></td>
      <td><span className={"cms-status "+(p.published?"cms-status-live":"cms-status-draft")}>{p.published?"Publicada":"Borrador"}</span></td>
      <td><code>{p.href}</code></td>
      <td><div className="cms-row-actions">
       <Link href={"/admin/personalizar/paginas/"+p.slug}>Editar</Link>
       {p.published&&<Link href={p.href} target="_blank" aria-label={"Ver "+p.title}>Ver <ArrowUpRight size={14}/></Link>}
       {canEdit&&p.id!=="home"&&<form method="post" action={"/api/admin/site-pages/"+encodeURIComponent(p.slug)}>
        <input type="hidden" name="intent" value="status"/>
        {!p.published&&<input type="hidden" name="published" value="on"/>}
        <button type="submit">{p.published?"Borrador":"Publicar"}</button>
       </form>}
      </div></td>
     </tr>)}</tbody>
    </table>
    {!results.length&&<div className="cms-no-results"><strong>No se encontraron páginas.</strong>
     <p>Modifica la búsqueda o crea una página nueva.</p></div>}
   </div>
   <div className="cms-list-footer"><span>{results.length} páginas mostradas · {menu.length} enlaces en el menú</span>
    {canEdit&&<Link href="/admin/personalizar/paginas/nueva"><Plus size={14}/> Añadir nueva página</Link>}</div>
  </section>
  <p className="property-help">La portada siempre está publicada. Cada página se edita individualmente; el menú y la identidad visual se gestionan por separado.</p>
 </>;
}
