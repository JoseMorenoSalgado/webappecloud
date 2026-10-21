import "server-only";
import {PrismaClient} from "@prisma/client";
import {databaseUrl} from "@/lib/server-config";

const g=globalThis as unknown as {webappPrisma?:PrismaClient;webappPrismaUrl?:string};
export function prismaFor(url:string){return new PrismaClient({datasources:{db:{url}}});}
export function getPrisma(){
 const url=databaseUrl();
 if(!url)throw new Error("Base de datos sin configurar. Visita /instalar.");
 if(!g.webappPrisma||g.webappPrismaUrl!==url){
  if(g.webappPrisma)void g.webappPrisma.$disconnect();
  g.webappPrisma=prismaFor(url);
  g.webappPrismaUrl=url;
 }
 return g.webappPrisma;
}
// Mantiene las APIs actuales sin conectar a MariaDB hasta completar la instalación.
export const prisma=new Proxy({} as PrismaClient,{
 get(_target,prop){
  const client=getPrisma();
  const value=Reflect.get(client,prop,client);
  return typeof value==="function"?value.bind(client):value;
 }
});
