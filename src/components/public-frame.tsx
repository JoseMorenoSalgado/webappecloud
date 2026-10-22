import {type ReactNode} from "react";
import {type PublicTenant,type PublicProperty} from "@/lib/demo";
import {SiteNav} from "@/components/site-nav";
import {SiteFooter} from "@/components/site-footer";
import {PreviewBanner} from "@/components/preview-banner";
import {PageView,FloatingWhatsapp} from "@/components/traffic-events";

export function PublicFrame({tenant,demo,children,property}:{tenant:PublicTenant;demo:boolean;children:ReactNode;property?:PublicProperty}){
 return <div className="public-site" style={{"--red":tenant.primaryColor,"--red-dark":tenant.primaryColor} as React.CSSProperties}>
  <SiteNav tenant={tenant}/>
  {demo?<PreviewBanner/>:<PageView/>}
  {children}
  <SiteFooter tenant={tenant}/>
  <FloatingWhatsapp tenant={tenant} property={property}/>
 </div>;
}
