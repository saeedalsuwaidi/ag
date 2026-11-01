(()=>{const menu=document.querySelector('.menu-toggle'),nav=document.querySelector('#main-nav');if(menu&&nav){menu.addEventListener('click',()=>{const open=menu.getAttribute('aria-expanded')!=='true';menu.setAttribute('aria-expanded',String(open));nav.classList.toggle('open',open)});nav.addEventListener('click',e=>{if(e.target.closest('a')){nav.classList.remove('open');menu.setAttribute('aria-expanded','false')}});document.addEventListener('keydown',e=>{if(e.key==='Escape'){nav.classList.remove('open');menu.setAttribute('aria-expanded','false')}})}if('IntersectionObserver'in window&&!matchMedia('(prefers-reduced-motion: reduce)').matches){document.documentElement.classList.add('js');const observer=new IntersectionObserver(entries=>entries.forEach(e=>{if(e.isIntersecting){e.target.classList.add('visible');observer.unobserve(e.target)}}),{threshold:.08});document.querySelectorAll('.reveal').forEach(el=>observer.observe(el))}})();

(()=>{
const preference=matchMedia('(prefers-reduced-motion: reduce)');
const writers=[];let timers=new Set();
function later(fn,ms){const id=setTimeout(()=>{timers.delete(id);fn()},ms);timers.add(id);return id}
document.querySelectorAll('.hero-typewriter').forEach(heading=>{
 if(document.querySelector('.hero-slideshow'))return;
 let pairs;try{pairs=JSON.parse(heading.dataset.phrases)}catch{return}
 const first=heading.querySelector('.type-first'),second=heading.querySelector('.type-second');
 if(!first||!second||!pairs.length)return;
 const state={first,second,pairs};writers.push(state);
 if(preference.matches)return;
 let index=0;
 function step(){
  if(preference.matches)return;
  const lines=pairs[index].map(s=>Array.from(s));let line=0,pos=0;
  first.textContent='';second.textContent='';second.classList.remove('type-cursor');first.classList.add('type-cursor');
  function type(){
   if(preference.matches)return;
   const el=line===0?first:second;el.textContent=lines[line].slice(0,pos).join('');
   if(pos<lines[line].length){pos++;later(type,65);return}
   if(line===0){first.classList.remove('type-cursor');second.classList.add('type-cursor');line=1;pos=0;later(type,220);return}
   later(erase,2400);
  }
  function erase(){
   if(preference.matches)return;
   const el=line===0?first:second;const chars=Array.from(el.textContent);
   if(chars.length){el.textContent=chars.slice(0,-1).join('');later(erase,28);return}
   if(line===1){second.classList.remove('type-cursor');first.classList.add('type-cursor');line=0;later(erase,90);return}
   index=(index+1)%pairs.length;later(step,350);
  }
  type();
 }
 later(step,450);
});
const counters=Array.from(document.querySelectorAll('[data-count]'));let activeFrames=new Set();
function finalValues(){counters.forEach(el=>el.textContent=String(Number(el.dataset.count)))}
function count(el){
 const target=Number(el.dataset.count);if(!Number.isFinite(target))return;
 if(preference.matches){el.textContent=String(target);return}
 let start;function frame(now){
  if(preference.matches){el.textContent=String(target);return}
  if(start===undefined)start=now;const p=Math.min((now-start)/1100,1);const eased=1-Math.pow(1-p,3);
  el.textContent=String(Math.round(target*eased));if(p<1){const id=requestAnimationFrame(frame);activeFrames.add(id)}else{el.textContent=String(target)}
 }
 const id=requestAnimationFrame(frame);activeFrames.add(id);
}
if(!preference.matches&&'IntersectionObserver'in window){
 counters.forEach(el=>{el.textContent='0';el.setAttribute('aria-label',String(Number(el.dataset.count)))});
 const observer=new IntersectionObserver(entries=>entries.forEach(entry=>{if(entry.isIntersecting){observer.unobserve(entry.target);count(entry.target)}}),{threshold:.25});
 counters.forEach(el=>observer.observe(el));
}
function reduce(event){if(!event.matches)return;timers.forEach(clearTimeout);timers.clear();activeFrames.forEach(cancelAnimationFrame);activeFrames.clear();writers.forEach(({first,second,pairs})=>{first.textContent=pairs[0][0];second.textContent=pairs[0][1];first.classList.remove('type-cursor');second.classList.remove('type-cursor')});finalValues()}
if(preference.addEventListener)preference.addEventListener('change',reduce);
})();



(()=>{
const header=document.querySelector('.site-header');
if(header){const update=()=>header.classList.toggle('is-scrolled',window.scrollY>36);update();window.addEventListener('scroll',update,{passive:true})}
document.querySelectorAll('.portfolio-explorer').forEach(explorer=>{
 const tabs=Array.from(explorer.querySelectorAll('[role="tab"]'));const panels=Array.from(explorer.querySelectorAll('[role="tabpanel"]'));
 function select(index,focus=false){tabs.forEach((tab,i)=>{tab.setAttribute('aria-selected',String(i===index));tab.tabIndex=i===index?0:-1;panels[i].hidden=i!==index});if(focus)tabs[index].focus()}
 tabs.forEach((tab,index)=>{tab.addEventListener('click',()=>select(index));tab.addEventListener('keydown',event=>{let next=index;const rtl=document.documentElement.dir==='rtl';if(event.key==='ArrowRight')next=(index+(rtl?-1:1)+tabs.length)%tabs.length;else if(event.key==='ArrowLeft')next=(index+(rtl?1:-1)+tabs.length)%tabs.length;else if(event.key==='Home')next=0;else if(event.key==='End')next=tabs.length-1;else return;event.preventDefault();select(next,true)})});
});
})();

(()=>{
 const container=document.querySelector('.hero-slideshow');if(!container)return;
 const slides=Array.from(container.querySelectorAll('.hero-slide'));
 const dots=Array.from(document.querySelectorAll('.hero-slide-dot'));const label=document.querySelector('.hero-slide-label');const pause=document.querySelector('.hero-pause');
 if(!slides.length||slides.length!==dots.length||!pause)return;
 const motion=matchMedia('(prefers-reduced-motion: reduce)');let index=0,paused=motion.matches,timer=null;
 function stop(){if(timer!==null){clearInterval(timer);timer=null}}
 const ar=document.documentElement.lang==='ar';
 const messages=ar?[
 ['إرثٌ إماراتي راسخ.','وأفقٌ بلا حدود.','من الإمارات إلى العالم، نبني قيمة مستدامة للمجتمع والأجيال القادمة.'],
 ['نطوّر المكان.','ونرتقي بالحياة.','استثمارات وتطوير عقاري برؤية طويلة الأمد، تصنع فرصاً للنمو وقيمة تدوم.'],
 ['نبني بثقة.','وننجز بإتقان.','خبرات في البناء والمقاولات تحوّل الرؤى إلى مشاريع، بجودة والتزام في كل خطوة.'],
 ['نصل الآفاق.','ونعبر إلى المستقبل.','حلول للشحن والنقل البحري تربط الأسواق وتدعم حركة التجارة.'],
 ['نستثمر في المعرفة.','ونبني أجيال المستقبل.','رؤية للتعليم تجمع الاستثمار في الإنسان وتنمية المهارات والتعليم القرآني وخدمة المجتمع.']
 ]:[
 ['An Emirati legacy.','A boundless horizon.','From the Emirates to the world, we build lasting value for society and future generations.'],
 ['Developing places.','Enriching lives.','Real estate investment and development with a long-term vision, creating opportunities and lasting value.'],
 ['Built on trust.','Delivered with care.','Construction expertise that turns vision into reality, with quality and commitment at every step.'],
 ['Connecting horizons.','Navigating tomorrow.','Shipping and maritime solutions that connect markets and support the flow of trade.'],
 ['Investing in knowledge.','Inspiring generations.','A broader educational vision connecting investment in people, skills, Quran education and community.']
 ];
 const heading=document.querySelector('.hero-typewriter'),copy=document.querySelector('.hero-copy');let textTimer=null;
 function syncText(){
  clearTimeout(textTimer);const message=messages[index];if(!heading||!message)return;
  const first=heading.querySelector('.type-first'),second=heading.querySelector('.type-second');
  heading.querySelector('.screen-reader-text').textContent=message[0]+' '+message[1];
  const sizer=heading.querySelector('.type-sizer');sizer.replaceChildren(document.createTextNode(message[0]),document.createElement('br'));const em=document.createElement('em');em.textContent=message[1];sizer.append(em);
  if(copy)copy.textContent=message[2];
  first.classList.remove('type-cursor');second.classList.remove('type-cursor');
  if(motion.matches){first.textContent=message[0];second.textContent=message[1];return;}
  first.textContent='';second.textContent='';let position=0;const count=message[0].length+message[1].length;
  function type(){position++;first.textContent=message[0].slice(0,position);second.textContent=message[1].slice(0,Math.max(0,position-message[0].length));if(position<count)textTimer=setTimeout(type,35)}type();
 }
 function show(next){index=(next+slides.length)%slides.length;slides.forEach((slide,i)=>slide.classList.toggle('is-active',i===index));dots.forEach((dot,i)=>dot.setAttribute('aria-current',String(i===index)));if(label)label.textContent=dots[index].dataset.label;syncText()}
 function schedule(){stop();if(!paused&&!document.hidden)timer=setInterval(()=>show(index+1),6500)}
 function setPaused(value){paused=value;pause.setAttribute('aria-pressed',String(paused));pause.textContent=paused?pause.dataset.play:pause.dataset.pause;schedule()}
 dots.forEach((dot,i)=>dot.addEventListener('click',()=>{show(i);setPaused(true)}));
 pause.addEventListener('click',()=>setPaused(!paused));document.addEventListener('visibilitychange',schedule);
 if(motion.addEventListener)motion.addEventListener('change',event=>{if(event.matches){setPaused(true);syncText()}});
 show(0);setPaused(paused);
})();


// Shared section dropdowns: mouse, keyboard and touch.
(()=>{
 const groups=Array.from(document.querySelectorAll('.nav-group'));
 const desktop=matchMedia('(min-width: 1241px)');
 function close(group){group.classList.remove('is-open');group.querySelector('.nav-toggle')?.setAttribute('aria-expanded','false')}
 function open(group){groups.forEach(other=>{if(other!==group)close(other)});group.classList.add('is-open');group.querySelector('.nav-toggle')?.setAttribute('aria-expanded','true')}
 groups.forEach(group=>{
  const button=group.querySelector('.nav-toggle'),list=group.querySelector('.nav-dropdown');if(!button||!list)return;
  button.addEventListener('click',()=>group.classList.contains('is-open')?close(group):open(group));
  group.addEventListener('pointerenter',event=>{if(desktop.matches&&event.pointerType==='mouse')open(group)});
  group.addEventListener('pointerleave',event=>{if(desktop.matches&&event.pointerType==='mouse'&&!group.contains(document.activeElement))close(group)});
  group.addEventListener('focusout',event=>{if(!group.contains(event.relatedTarget))close(group)});
  group.addEventListener('keydown',event=>{
   if(event.key==='Escape'){event.preventDefault();event.stopPropagation();close(group);button.focus()}
   if(event.key==='ArrowDown'&&(event.target===button||event.target===group.querySelector('.nav-group-trigger>a'))){event.preventDefault();open(group);list.querySelector('a')?.focus()}
  });
 });
 document.addEventListener('click',event=>{groups.forEach(group=>{if(!group.contains(event.target))close(group)})});
 document.querySelector('.menu-toggle')?.addEventListener('click',()=>{if(document.querySelector('.menu-toggle').getAttribute('aria-expanded')==='false')groups.forEach(close)});
 desktop.addEventListener('change',()=>{groups.forEach(close);document.querySelector('#main-nav')?.classList.remove('open');document.querySelector('.menu-toggle')?.setAttribute('aria-expanded','false')});
})();

