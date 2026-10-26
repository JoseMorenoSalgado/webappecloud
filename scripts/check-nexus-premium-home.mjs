import assert from "node:assert/strict";
import {readFileSync} from "node:fs";

const origin=process.env.TEST_ORIGIN||"http://localhost:3000";
assert.equal(new URL(origin).hostname,"localhost","Only test isolated localhost CI.");
const home=await fetch(origin+"/");
assert.equal(home.status,200,"Public Home must load.");
const html=await home.text();
for(const marker of [
  'class="nexus-home-premium"','nexus-hero-layout-simple',
  'nexus-hero-photo-note','UNA NUEVA PERSPECTIVA',
  'nexus-search-header','Encuentra tu próxima propiedad',
  'name="operacion"','name="tipo"','name="ubicacion"',
  'action="/propiedades"','nexus-featured-grid',
  'id="propiedades-destacadas"','nexus-services-preview',
  'id="services-preview-title"','id="story-preview-title"',
  'nexus-lead-panel','nexus-footer'
])assert.ok(html.includes(marker),"Missing real Home element: "+marker);
assert.ok(!html.includes("500 Internal Server Error"),"Home rendered a server error");
const source=readFileSync("src/components/templates/real-estate-home.tsx","utf8");
const css=readFileSync("src/app/styles/templates/nexus-home-premium.css","utf8");
const layout=readFileSync("src/app/layout.tsx","utf8");
assert.ok(layout.includes('import "./styles/templates/nexus-home-premium.css";'),"Public stylesheet not loaded");
assert.ok(source.includes('<PageExtraSections tenantId={tenant.id} slug="home" demo={demo}/>'),"CMS additions must remain editable");
assert.ok(source.includes('settings.heroHeading')&&source.includes('settings.heroHighlight')&&source.includes('settings.heroDescription'),"Hero must remain configurable");
assert.ok(source.includes('settings.featuredIds')&&source.includes('PropertyCard'),"Real featured properties must remain dynamic");
assert.ok(source.includes('visible("propiedades")')&&source.includes('visible("servicios")')&&source.includes('visible("contacto")'),"Publishing permissions must still gate links and sections");
assert.ok(source.includes("ContactForm")&&source.includes('method="get" action="/propiedades"'),"Real contact and catalog actions required");
assert.ok(css.includes(".public-site .nexus-home-premium .nexus-hero"),"New styles must be scoped to real estate home");
assert.ok(!css.includes("transition:all"),"Avoid expensive broad animations");
assert.ok(!/\b(?:position|width):\s*fixed\b/.test(css),"Home must not add fixed layout panels");
assert.ok(css.includes("@media(max-width:960px)")&&css.includes("@media(max-width:610px)")&&css.includes("@media(max-width:375px)"),"Responsive breakpoints absent");
assert.ok(css.includes("font-size:16px"),"Mobile form controls must avoid forced zoom");
assert.ok(css.includes("prefers-reduced-motion:reduce"),"Reduced motion must be respected");
assert.ok(css.includes(".nexus-services-grid")&&css.includes("border-left:0"),"Services should become an unboxed single column on mobile");
const org=readFileSync("src/components/templates/organization-site.tsx","utf8");
assert.ok(!org.includes("nexus-home-premium"),"Institutional site must keep own design");
console.log("NEXUS premium Home: editorial hierarchy, preserved data/links, mobile controls, styling isolation and accessibility markers passed.");
