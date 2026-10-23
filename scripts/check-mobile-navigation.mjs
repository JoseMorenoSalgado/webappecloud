import assert from "node:assert/strict";
import {readFileSync} from "node:fs";
const css=readFileSync("src/app/globals.css","utf8");
const focused=readFileSync("src/app/admin-tidy.css","utf8");
const nav=readFileSync("src/components/admin-nav.tsx","utf8");
assert.ok(!css.includes(".admin-nav span{opacity:.42"),
 "Los textos de navegación no deben recibir opacidad de elementos deshabilitados.");
assert.ok(!css.includes(".admin-nav a,.admin-nav span{"),
 "La regla de enlaces no debe aplicar estilos a todos los spans anidados.");
assert.match(focused,/\.admin-mobile-drawer \.admin-nav > a\.active > span/);
assert.match(focused,/opacity:1!important/);
assert.match(focused,/min-height:44px!important/);
assert.match(focused,/overflow-y:auto!important/);
assert.match(focused,/color:white!important/);
assert.match(nav,/aria-current=\{active\?"page":undefined\}/);
assert.match(nav,/aria-label="Cerrar menú"/);
assert.match(nav,/document\.body\.style\.overflow="hidden"/);
assert.match(nav,/event\.key==="Escape"/);
console.log("Menú móvil: contraste, enlaces, tamaños, desplazamiento y cierre accesible verificados.");
