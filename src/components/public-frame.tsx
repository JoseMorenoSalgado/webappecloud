import {type ReactNode} from "react";
import {type PublicTenant,type PublicProperty} from "@/lib/demo";
import {SiteNav} from "@/components/site-nav";
import {DEFAULT_SITE_MENU,getSiteMenu} from "@/lib/site-pages";
import {SiteFooter} from "@/components/site-footer";
import {PreviewBanner} from "@/components/preview-banner";
import {PageView,FloatingWhatsapp} from "@/components/traffic-events";

export async function PublicFrame({tenant,demo,children,property}:{tenant:PublicTenant;demo:boolean;children:ReactNode;property?:PublicProperty}){
 const links=demo?DEFAULT_SITE_MENU:await getSiteMenu(tenant.id);
 return <div className="public-site" style={{"--red":tenant.primaryColor,"--red-dark":tenant.primaryColor} as React.CSSProperties}>
  <SiteNav tenant={tenant} links={links}/>
  {demo?<PreviewBanner/>:<PageView/>}
  {children}
  <SiteFooter tenant={tenant} links={links}/>
  <FloatingWhatsapp tenant={tenant} property={property}/>
 </div>;
}
