import type {Metadata} from "next";
import "./globals.css";
import "./admin-tidy.css";
import "./home-tidy.css";
import "./inventory-tidy.css";
import "./editor-tidy.css";
export const metadata:Metadata={title:{default:"Nexus Real Estate | Pensando en tu futuro",template:"%s | Nexus Real Estate"},description:"Propiedades en venta y alquiler. Descubre tu futuro con Nexus Real Estate."};
export default function RootLayout({children}:{children:React.ReactNode}){return <html lang="es"><body>{children}</body></html>;}
