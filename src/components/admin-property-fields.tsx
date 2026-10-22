type Data={
 refCode:string;title:string;city:string;neighborhood:string|null;type:string;operation:string;
 price:number;bedrooms:number|null; bathrooms:number|null;areaM2:number|null;
 description:string;featured:boolean;published:boolean;
};
export function AdminPropertyFields({property}:{property?:Data}){
 const value=(key:keyof Data,fallback:string|number=""):string|number=>{const x=property?.[key];return typeof x==="string"||typeof x==="number"?x:fallback;};
 return <>
  <div className="property-form-section"><h2>Datos del inmueble</h2><p>Información que se mostrará en el catálogo y en la ficha técnica.</p>
   <div className="admin-form-grid">
    <label>Referencia *<input name="refCode" placeholder="NX-102" defaultValue={value("refCode")} minLength={3} maxLength={40} required/></label>
    <label>Título *<input name="title" placeholder="Casa moderna en Zona Ejecutiva" defaultValue={value("title")} minLength={5} maxLength={140} required/></label>
   </div>
   <div className="admin-form-grid">
    <label>Ciudad *<input name="city" defaultValue={value("city","Managua")} maxLength={80} required/></label>
    <label>Zona o residencial<input name="neighborhood" defaultValue={value("neighborhood")} placeholder="Las Colinas" maxLength={80}/></label>
   </div>
   <div className="admin-form-grid">
    <label>Operación<select name="operation" defaultValue={value("operation","venta")}>
      <option value="venta">En venta</option><option value="alquiler">En alquiler</option>
    </select></label>
    <label>Tipo de propiedad<select name="type" defaultValue={value("type","Casa")}>
      {["Casa","Apartamento","Terreno","Local","Otro"].map(t=><option key={t} value={t}>{t}</option>)}
    </select></label>
   </div>
   <div className="admin-form-grid">
    <label>Precio (USD) *<input name="price" inputMode="decimal" type="number" min="0.01" max="99999999999" step="0.01" defaultValue={value("price")} required/></label>
    <label>Superficie (m²)<input name="areaM2" type="number" min="0" max="10000000" defaultValue={value("areaM2",0)} required/></label>
   </div>
   <div className="admin-form-grid property-form-grid-three">
    <label>Habitaciones<input name="bedrooms" type="number" min="0" max="10000000" defaultValue={value("bedrooms",0)} required/></label>
    <label>Baños<input name="bathrooms" type="number" min="0" max="10000000" defaultValue={value("bathrooms",0)} required/></label>
   </div>
   <label>Descripción *<textarea name="description" minLength={10} maxLength={8000} defaultValue={value("description")} placeholder="Características, servicios y beneficios de la propiedad" required/></label>
  </div>
  <div className="property-form-section"><h2>Visibilidad</h2>
   <label className="property-check"><input type="checkbox" name="published" defaultChecked={Boolean(property?.published)}/> Publicar en el sitio web</label>
   <label className="property-check"><input type="checkbox" name="featured" defaultChecked={Boolean(property?.featured)}/> Destacar esta propiedad</label>
   <p className="property-help">Las propiedades en borrador no aparecen en el catálogo público ni en sus fichas descargables.</p>
  </div>
 </>;
}
