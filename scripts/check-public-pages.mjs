import assert from "node:assert/strict";
const origin=process.env.TEST_ORIGIN||"http://localhost:3000";
assert.equal(new URL(origin).hostname,"localhost","Do not test production hosts.");
const pages=[
 ["/","Encuentra tu próxima propiedad"],
 ["/nosotros","Personas primero."],
 ["/servicios","NUESTROS SERVICIOS"],
 ["/contacto","SOLICITAR INFORMACIÓN"],
 ["/propiedades","Filtrar propiedades"]
];
for(const [path,needle] of pages){
 const res=await fetch(origin+path);
 assert.equal(res.status,200,"Page "+path+" must load");
 const html=await res.text();
 assert.ok(html.includes(needle),"Expected "+needle+" on "+path);
 for(const href of ['href="/nosotros"','href="/servicios"','href="/contacto"','href="/propiedades"']){
  assert.ok(html.includes(href),"Navigation "+href+" absent on "+path);
 }
 assert.ok(html.includes("nexus-footer"),"Unified footer absent on "+path);
 assert.ok(!html.includes('href="/#nosotros"')&&!html.includes('href="/#contacto"'),"Old anchor navigation should be removed");
}
const form=new FormData();
form.set("email",process.env.ADMIN_EMAIL);
form.set("password",process.env.ADMIN_PASSWORD);
const login=await fetch(origin+"/api/admin/session",{method:"POST",headers:{Origin:origin},body:form,redirect:"manual"});
assert.equal(login.status,303,"Cannot login as test administrator");
const cookie=login.headers.getSetCookie().find(s=>s.startsWith("webappecloud_session="))?.split(";")[0];
assert.ok(cookie);
const editor=await fetch(origin+"/admin/personalizar",{headers:{Cookie:cookie}});
assert.equal(editor.status,200);
const adminHtml=await editor.text();
for(const field of ["aboutMission","aboutVision","servicesHeading","servicesDescription","servicesSale","servicesRent","servicesAdvice"]){
 assert.ok(adminHtml.includes('name="'+field+'"'),"Missing editable field "+field);
}
const values={
 heroEyebrow:"NEXUS INMOBILIARIA NICARAGUA",heroHeading:"Tu nuevo hogar comienza",heroHighlight:"con nosotros.",
 heroDescription:"Encuentra opciones de compra y alquiler y solicita información sobre cada inmueble.",
 heroButton:"Explorar catálogo",sectionEyebrow:"INMUEBLES DESTACADOS",
 sectionHeading:"Explora estas",sectionHighlight:"propiedades.",
 sectionDescription:"Explora el catálogo de propiedades actualizado por nuestros asesores.",
 aboutTitle:"Una inmobiliaria más cercana",
 aboutDescription:"Te ayudamos a conocer las opciones disponibles y a elegir con información clara.",
 aboutMission:"Nuestra misión editorial de prueba es ofrecer información inmobiliaria útil a cada cliente.",
 aboutVision:"Nuestra visión editorial de prueba es conectar personas con opciones de vivienda.",
 servicesHeading:"Servicios inmobiliarios a tu alcance",
 servicesDescription:"Comprar, alquilar y encontrar la propiedad que buscas.",
 servicesSale:"Servicio personalizado para explorar opciones de compra y venta.",
 servicesRent:"Servicio de búsqueda de apartamentos, casas y terrenos en alquiler.",
 servicesAdvice:"Atención directa para ayudarte a comparar inmuebles disponibles.",
 contactHeading:"Estamos para ayudarte",contactHighlight:"a encontrar tu inmueble.",
 contactDescription:"Consulta propiedades y nuestro equipo responderá tus dudas.",
 tagline:"Pensando en tu futuro",whatsapp:"88887777",contactEmail:"contacto@nexus.example",primaryColor:"#9C1A27"
};
const settingsForm=new FormData();
for(const [name,value] of Object.entries(values))settingsForm.set(name,value);
const posted=await fetch(origin+"/api/admin/site-settings",{
 method:"POST",headers:{Cookie:cookie,Origin:origin},body:settingsForm,redirect:"manual"
});
assert.equal(posted.status,303);
assert.match(posted.headers.get("location")||"",/saved=1/);
const about=await (await fetch(origin+"/nosotros")).text();
const service=await (await fetch(origin+"/servicios")).text();
const home=await (await fetch(origin+"/")).text();
const contact=await (await fetch(origin+"/contacto")).text();
assert.ok(about.includes(values.aboutMission)&&about.includes(values.aboutVision),"About content not applied");
assert.ok(service.includes(values.servicesHeading)&&service.includes(values.servicesSale)&&service.includes(values.servicesRent)&&service.includes(values.servicesAdvice),"Services content not applied");
assert.ok(home.includes(values.heroHeading)&&home.includes(values.heroButton),"Editable hero not applied");
assert.ok(contact.includes(values.contactHeading)&&contact.includes(values.contactDescription),"Contact intro not applied");
for(const route of ["/nosotros","/servicios","/contacto"]){
 const noTenant=await fetch(origin+route,{headers:{Host:"unknown-tenant.invalid"},redirect:"manual"});
 assert.equal(noTenant.status,404,"Unregistered host unexpectedly served "+route);
}
console.log("Public experience: all five routes, navigation, shared footer, editable page content, unknown-tenant isolation passed.");
