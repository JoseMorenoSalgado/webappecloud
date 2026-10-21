import Link from "next/link";
import {createProperty} from "@/app/admin/actions";
import {requireAdmin} from "@/lib/auth";
export const dynamic="force-dynamic";
export default async function NewProperty(){
 await requireAdmin();
 return <><div className="admin-heading"><div><h1>Nueva propiedad</h1><p>Agrega un inmueble al catálogo de Nexus Real Estate.</p></div><Link href="/admin/propiedades" className="inline-link">← Cancelar</Link></div>
 <form className="admin-form" action={createProperty}>
  <div className="admin-form-grid"><label>Referencia *<input name="refCode" placeholder="NX-102" minLength={3} maxLength={40} required/></label><label>Título *<input name="title" placeholder="Casa moderna en Managua" minLength={5} maxLength={140} required/></label></div>
  <div className="admin-form-grid"><label>Ciudad *<input name="city" defaultValue="Managua" required/></label><label>Zona<input name="neighborhood" placeholder="Las Colinas"/></label></div>
  <div className="admin-form-grid"><label>Operación<select name="operation"><option value="venta">En venta</option><option value="alquiler">En alquiler</option></select></label><label>Tipo<select name="type"><option>Casa</option><option>Apartamento</option><option>Terreno</option><option>Local</option><option>Otro</option></select></label></div>
  <div className="admin-form-grid"><label>Precio (USD) *<input name="price" type="number" min="1" step="0.01" required/></label><label>URL HTTPS de fotografía<input name="image" type="url" placeholder="https://..."/></label></div>
  <div className="admin-form-grid"><label>Habitaciones<input name="bedrooms" type="number" min="0" defaultValue="0"/></label><label>Baños<input name="bathrooms" type="number" min="0" defaultValue="0"/></label></div>
  <label>Superficie (m²)<input name="areaM2" type="number" min="0" defaultValue="0"/></label>
  <label>Descripción *<textarea name="description" required minLength={10} maxLength={8000} placeholder="Descripción del inmueble"/></label>
  <label style={{display:"flex",gap:9,alignItems:"center"}}><input type="checkbox" name="published" defaultChecked/> Publicar al guardar</label>
  <p className="admin-empty">La carga directa de fotografías se incorporará en la siguiente fase; por ahora puedes utilizar una URL HTTPS.</p>
  <button className="btn btn-red">Guardar propiedad</button>
 </form></>;
}
