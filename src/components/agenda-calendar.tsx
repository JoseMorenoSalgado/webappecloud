"use client";
import Link from "next/link";
import {useEffect,useState} from "react";
import {AGENDA_TYPES} from "@/lib/agenda-constants";
export type AgendaCalendarItem={id:string;title:string;type:string;status:string;startsAt:string;leadName:string};
const pad=(n:number)=>String(n).padStart(2,"0");
function dateKey(date:Date){return date.getFullYear()+"-"+pad(date.getMonth()+1)+"-"+pad(date.getDate());}
function monthLabel(ym:string){const [y,m]=ym.split("-").map(Number);return new Intl.DateTimeFormat("es",{month:"long",year:"numeric"}).format(new Date(y,m-1,1));}
export function AgendaLocalDate({iso}:{iso:string}){
 const [ready,setReady]=useState(false);
 useEffect(()=>setReady(true),[]);
 return <time dateTime={iso} suppressHydrationWarning>{ready?new Intl.DateTimeFormat("es",{dateStyle:"medium",timeStyle:"short"}).format(new Date(iso)):"Horario local al abrir"}</time>;
}
export function AgendaCalendar({month,events}:{month:string;events:AgendaCalendarItem[]}){
 const [ready,setReady]=useState(false);
 useEffect(()=>setReady(true),[]);
 const [year,mm]=month.split("-").map(Number),m=mm-1;
 const firstDay=(new Date(year,m,1).getDay()+6)%7;
 const count=new Date(year,m+1,0).getDate();
 const before=new Date(year,m-1,1),after=new Date(year,m+1,1);
 const prev=before.getFullYear()+"-"+pad(before.getMonth()+1);
 const next=after.getFullYear()+"-"+pad(after.getMonth()+1);
 const dates=Array.from({length:Math.ceil((firstDay+count)/7)*7},(_,i)=>i-firstDay+1);
 const byDay=new Map<string,AgendaCalendarItem[]>();
 if(ready)for(const ev of events){
  const key=dateKey(new Date(ev.startsAt));
  const group=byDay.get(key)||[];group.push(ev);byDay.set(key,group);
 }
 const today=ready?dateKey(new Date()):"";
 return <section className="admin-panel agenda-calendar">
  <header className="agenda-calendar-top">
   <div><h2>Calendario comercial</h2><p>{monthLabel(month)} · horario del dispositivo</p></div>
   <nav aria-label="Navegar meses"><Link href={"/admin/agenda?mes="+prev} aria-label="Mes anterior">←</Link><Link href={"/admin/agenda?mes="+next} aria-label="Mes siguiente">→</Link></nav>
  </header>
  <div className="agenda-calendar-scroll"><div className="agenda-calendar-grid">
   {["Lun","Mar","Mié","Jue","Vie","Sáb","Dom"].map(day=><strong key={day} className="agenda-weekday">{day}</strong>)}
   {dates.map((day,i)=>{
    const outside=day<1||day>count;
    const date=new Date(year,m,day),key=dateKey(date),dayEvents=outside?[]:byDay.get(key)||[];
    return <div key={key+"-"+i} className={"agenda-calendar-day"+(outside?" agenda-outside":"")+(today===key?" agenda-today":"")}>
     <span className="agenda-day-number">{date.getDate()}</span>
     {dayEvents.slice(0,3).map(ev=><a key={ev.id} href={"#agenda-"+ev.id} className={"agenda-day-event agenda-"+ev.type+" agenda-"+ev.status} title={ev.title+" · "+ev.leadName}>
      <span>{new Date(ev.startsAt).toLocaleTimeString("es",{hour:"2-digit",minute:"2-digit"})}</span> {ev.title}
     </a>)}
     {dayEvents.length>3&&<small>+{dayEvents.length-3} actividades</small>}
    </div>;
   })}
  </div></div>
  {!ready&&<p className="property-help">Preparando el calendario en tu zona horaria…</p>}
  <div className="agenda-calendar-legend">{AGENDA_TYPES.map(t=><span key={t.value} className={"agenda-"+t.value}>{t.label}</span>)}</div>
 </section>;
}
