import "server-only";
import {readFileSync,existsSync,mkdirSync,openSync,closeSync,unlinkSync,writeFileSync,renameSync,chmodSync} from "node:fs";
import {join,resolve} from "node:path";
import {randomUUID} from "node:crypto";

export type StoredInstallation={version:1;databaseUrl:string;installedAt:string};
export function configDir(){return resolve(process.env.APP_CONFIG_DIR||join(process.cwd(),".runtime"));}
export function configPath(){return join(configDir(),"installation.json");}
export function storedInstallation():StoredInstallation|null{
 const path=configPath();
 if(!existsSync(path))return null;
 const data=JSON.parse(readFileSync(path,"utf8")) as StoredInstallation;
 if(data.version!==1||!data.databaseUrl)throw new Error("Archivo de instalación inválido");
 return data;
}
export function databaseUrl():string|undefined{
 return storedInstallation()?.databaseUrl||process.env.DATABASE_URL||undefined;
}
export function hasDatabaseConfig(){return Boolean(databaseUrl());}
export function installerAvailable(){return !hasDatabaseConfig();}
export function saveInstallation(database:string){
 mkdirSync(configDir(),{recursive:true,mode:0o700});
 const path=configPath();
 if(existsSync(path))throw new Error("La plataforma ya está instalada");
 const temp=join(configDir(),".setup-"+randomUUID());
 try{
  writeFileSync(temp,JSON.stringify({version:1,databaseUrl:database,installedAt:new Date().toISOString()}),{flag:"wx",mode:0o600});
  chmodSync(temp,0o600);
  renameSync(temp,path);
 }finally{if(existsSync(temp))unlinkSync(temp);}
}
export function takeInstallLock(){
 mkdirSync(configDir(),{recursive:true,mode:0o700});
 const path=join(configDir(),".installer.lock");
 const fd=openSync(path,"wx",0o600);
 return ()=>{closeSync(fd);unlinkSync(path);};
}
