import assert from "node:assert/strict";
import {readFileSync} from "node:fs";

const origin=process.env.TEST_ORIGIN||"http://localhost:3000";
assert.equal(new URL(origin).hostname,"localhost","Production hosts are never used in CI.");

const realEstate=[
 ["/",["nexus-home-premium","nexus-site-premium","nexus-hero"]],
 ["/propiedades",["nexus-site-premium","nexus-public-page","nexus-catalog-filter-card","nexus-featured-grid","catalog-primary-filters"]],
 ["/propiedades/casa-zona-ejecutiva",["nexus-site-premium","property-detail-layout","property-detail-sidebar","property-gallery-viewport","property-contact-card","Descargar ficha automática"]],
 ["/nosotros",["nexus-site-premium","nexus-about-page","nexus-principles-grid","nexus-value-list"]],
 ["/servicios",["nexus-site-premium","nexus-services-page-root","nexus-service-details","nexus-steps-grid"]],
 ["/contacto",["nexus-site-premium","nexus-contact-page-root","nexus-contact-methods","nexus-contact-form-card"]]
];
for(const [path,markers] of realEstate){
 const result=await fetch(origin+path);
 assert.equal(result.status,200,path+" must render");
 const html=await result.text();
 for(const marker of markers)assert.ok(html.includes(marker),path+" must preserve "+marker);
 assert.ok(html.includes("nexus-footer"),path+" must preserve footer");
}
const css=readFileSync("src/app/styles/templates/nexus-site-premium.css","utf8");
const homeCss=readFileSync("src/app/styles/templates/nexus-home-premium.css","utf8");
const frame=readFileSync("src/components/public-frame.tsx","utf8");
const layout=readFileSync("src/app/layout.tsx","utf8");
assert.ok(layout.includes('import "./styles/templates/nexus-home-premium.css";'),"Premium Home CSS must load");
assert.ok(layout.includes('import "./styles/templates/nexus-site-premium.css";'),"Inner-page CSS must load");
assert.ok(layout.indexOf('import "./styles/templates/nexus-home-premium.css";')<layout.indexOf('import "./styles/templates/nexus-site-premium.css";'),"Shared public stylesheet must load last");
assert.ok(frame.includes('template.key!=="real-estate"?" public-site-organization":" nexus-site-premium"'),"Only real-estate tenants may use premium styles");
for(const file of [
 "src/app/propiedades/page.tsx","src/app/nosotros/page.tsx",
 "src/app/servicios/page.tsx","src/app/contacto/page.tsx"
]){
 const source=readFileSync(file,"utf8");
 assert.ok(source.includes("nexus-public-page"),"Missing page-local scope in "+file);
 assert.ok(source.includes("PageExtraSections"),"CMS extras were lost in "+file);
}
for(const key of [
 ".public-site.nexus-site-premium .nexus-inner-hero",
 ".public-site.nexus-site-premium .nexus-catalog-filter-card",
 ".public-site.nexus-site-premium .property-detail-layout",
 ".public-site.nexus-site-premium .property-gallery-photo",
 ".public-site.nexus-site-premium .nexus-principles-grid",
 ".public-site.nexus-site-premium .nexus-service-details article",
 ".public-site.nexus-site-premium .nexus-contact-form-card",
 ".public-site.nexus-site-premium .nexus-footer"
])assert.ok(css.includes(key),"Shared design does not define "+key);
assert.ok(css.includes("object-fit:contain!important"),"Property details must preserve original image proportions");
assert.ok(css.includes("position:static"),"The property CTA must stack normally on tablets");
for(const breakpoint of ["1024px","820px","610px","375px"])
 assert.ok(css.includes("@media(max-width:"+breakpoint+")"),"Responsive breakpoint missing: "+breakpoint);
assert.ok(css.includes("font-size:16px"),"Mobile form controls need >=16px text");
assert.ok(css.includes("prefers-reduced-motion:reduce"),"Reduced-motion support missing");
assert.ok(!css.includes("transition:all"),"Expensive transition detected");
assert.ok(!homeCss.includes("nexus-public-page"),"Home styling must stay independent");
assert.ok(!css.includes('".public-site-organization"'),"Organization template should never be styled by real estate theme");
for(const page of ["src/app/propiedades/[slug]/page.tsx","src/app/contacto/page.tsx"]){
 const source=readFileSync(page,"utf8");
 assert.ok(source.includes("ContactForm"),"Lead/contact forms must remain functional in "+page);
}
const details=readFileSync("src/app/propiedades/[slug]/page.tsx","utf8");
assert.ok(details.includes("WhatsAppButton")&&details.includes("hasCustomPdf"),"Property contact and custom PDF must remain supported");
const catalog=readFileSync("src/app/propiedades/page.tsx","utf8");
assert.ok(catalog.includes('getCatalogPage')&&catalog.includes('searchParams'),"Server-side property filters must remain in place");
assert.ok(catalog.includes('assertPagePublished')&&catalog.includes('tenantModuleEnabled'),"Tenant publication guard must remain in place");
console.log("NEXUS premium public site: six routes, tenant separation, CMS, media, forms, filters, PDF, responsive CSS passed.");
