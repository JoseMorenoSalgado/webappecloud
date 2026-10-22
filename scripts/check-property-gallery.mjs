import assert from "node:assert/strict";
import {readFileSync} from "node:fs";
const origin=process.env.TEST_ORIGIN||"http://localhost:3000";
assert.equal(new URL(origin).hostname,"localhost","Use the isolated CI server only.");
const path="/propiedades/casa-zona-ejecutiva";
const response=await fetch(origin+path);
assert.equal(response.status,200,"Property detail must remain publicly accessible");
const html=await response.text();
assert.ok(html.includes("public-gallery-main"),"Missing main photograph container");
assert.ok(html.includes("Ver imagen completa"),"Photo must be viewable at user request");
assert.ok(html.includes("public-gallery-dialog"),"Full-view image viewer absent");
assert.ok(html.includes("public-gallery-thumbs")||html.includes("public-gallery-main"),
 "Gallery must render for the property");
assert.ok(html.includes("Descargar ficha automática"),"Property PDF must remain available");
const css=readFileSync("src/app/globals.css","utf8");
assert.match(css,/\.public-site \.public-gallery-main\s*>\s*img\s*\{[\s\S]*?width:auto;[\s\S]*?height:auto;[\s\S]*?max-width:100%;[\s\S]*?max-height:100%;[\s\S]*?object-fit:contain;/,
 "Main photo must display natural dimensions without upscaling or cropping");
assert.match(css,/\.public-site \.public-gallery-dialog-image\s*>\s*img\s*\{[^}]*object-fit:contain/,
 "Full-view modal must preserve the full image");
assert.match(css,/@media\(max-width:720px\)\{[^}]*\.public-site \.public-gallery-main\{height:clamp/,
 "Mobile photo must have a responsive frame");
console.log("Property gallery: full photograph, no automatic upscaling, dialog and mobile layout passed.");
