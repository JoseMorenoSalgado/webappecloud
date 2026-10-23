import Link from "next/link";
import {Layers3} from "lucide-react";
import {type PublicTenant} from "@/lib/demo";
import {sitePresentation} from "@/lib/site-presentation";

/** Reusable organization branding; NEXUS's approved identity is a preset. */
export function SiteBrand({tenant}:{tenant:PublicTenant}){
 const brand=sitePresentation(tenant);
 return <Link className="brand nexus-brand" href="/" aria-label={"Inicio · "+tenant.name}>
  <span className="brand-icon">{tenant.logoUrl?
   <img src={tenant.logoUrl} alt=""/>:
   brand.fallbackLogo?<img src={brand.fallbackLogo} alt=""/>:<Layers3 size={28} aria-hidden="true"/>}</span>
  <span><strong>{brand.name}</strong><small>{brand.subtitle}</small></span>
 </Link>;
}
