import assert from "node:assert/strict";
const base=process.env.TEST_ORIGIN||"http://localhost:3000";
assert.equal(new URL(base).hostname,"localhost","Esta prueba solo se ejecuta en CI local.");

const form=new FormData();
form.set("email",process.env.ADMIN_EMAIL||"");
form.set("password",process.env.ADMIN_PASSWORD||"");
const login=await fetch(base+"/api/admin/session",{
 method:"POST",headers:{Origin:base},body:form,redirect:"manual"
});
assert.equal(login.status,303,"No se pudo acceder al panel");
const cookie=login.headers.getSetCookie().find(x=>x.startsWith("webappecloud_session="))?.split(";")[0];
assert.ok(cookie,"Sin cookie de sesión");
const dashboard=await fetch(base+"/admin",{headers:{Cookie:cookie}});
assert.equal(dashboard.status,200);
const html=await dashboard.text();
assert.match(html,/<h1>Resumen<\/h1>/);
assert.match(html,/dashboard-simple-list/);
assert.match(html,/dashboard-details/);
assert.match(html,/Más herramientas/);
assert.match(html,/Prospectos/);
assert.ok(!html.includes("Próximamente</span>"),"No debe mostrar enlaces de navegación que no funcionan");
const home=await fetch(base+"/");
assert.equal(home.status,200);
const publicHtml=await home.text();
assert.ok(publicHtml.includes("nexus-hero-layout-simple"));
assert.ok(!publicHtml.includes("nexus-hero-side-mark"),"No debe verse el bloque decorativo lateral");
assert.ok(!publicHtml.includes("01 \/ EXPERIENCIAS"),"No debe mostrarse contador decorativo");
console.log("UX simplificada: navegación, KPIs, recientes y home verificados.");
