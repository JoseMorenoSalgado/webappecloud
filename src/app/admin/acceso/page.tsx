import Link from "next/link";
import {signIn} from "../actions";
export const dynamic="force-dynamic";
export default async function Access({searchParams}:{searchParams:Promise<{error?:string}>}){
 const q=await searchParams;return <main className="login-page"><form action={signIn} className="login-card">
  <div className="brand"><span className="brand-icon"><img src="/brand/nexus-icon.webp" alt=""/></span><span><strong>NEXUS</strong><small>REAL ESTATE</small></span></div>
  <h1>Acceso administrativo</h1><p>Ingresa tus credenciales para gestionar tu sitio web.</p>
  {q.error&&<div className="admin-error">{q.error==="config"?"Configura MariaDB antes de iniciar sesión.":"Correo o contraseña incorrectos."}</div>}
  <label>Correo electrónico<input name="email" type="email" autoComplete="username" maxLength={180} required/></label>
  <label>Contraseña<input name="password" type="password" autoComplete="current-password" required/></label>
  <button className="btn btn-red" type="submit">Entrar al panel →</button>
  <Link href="/" className="inline-link">← Regresar al sitio web</Link>
 </form></main>;
}
