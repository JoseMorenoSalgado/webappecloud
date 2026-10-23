import type {Metadata} from "next";
import "./globals.css";
import "./styles/legacy/admin-shell.css";
import "./styles/legacy/real-estate-admin.css";
import "./styles/templates/nexus-public.css";
import "./styles/legacy/cms.css";
import "./styles/templates/nexus-property.css";
import "./styles/legacy/agent-admin.css";
import "./styles/templates/nexus-property-cover.css";
import "./styles/legacy/commercial-workflows.css";
import "./admin-tidy.css";
import "./home-tidy.css";
import "./inventory-tidy.css";
import "./editor-tidy.css";
import "./cms-tidy.css";
export const metadata:Metadata={title:{default:"Nexus Real Estate | Pensando en tu futuro",template:"%s | Nexus Real Estate"},description:"Propiedades en venta y alquiler. Descubre tu futuro con Nexus Real Estate."};
export default function RootLayout({children}:{children:React.ReactNode}){return <html lang="es"><body>{children}</body></html>;}
