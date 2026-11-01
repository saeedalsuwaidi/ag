(()=>{'use strict';document.querySelectorAll('.ag-direct-form').forEach(form=>{
 const ar=form.dataset.lang==='ar',button=form.querySelector('[type=submit]'),status=form.querySelector('.application-status');
 let busy=false,id=globalThis.crypto?.randomUUID?.()||Date.now().toString(36)+'-'+Math.random().toString(36).slice(2)+'-'+Math.random().toString(36).slice(2);
 form.addEventListener('submit',async e=>{e.preventDefault();if(busy||!form.reportValidity())return;
 const file=form.querySelector('[name=cv]')?.files[0];if(file&&(file.size>5*1024*1024||!file.name.toLowerCase().endsWith('.pdf'))){status.textContent=ar?'السيرة الذاتية: PDF فقط، حتى 5 ميغابايت.':'CV: PDF only, up to 5 MB.';return;}
 if(!globalThis.AGForms){status.textContent=ar?'الإرسال متاح في نسخة ووردبريس المثبتة.':'Submission is available on the installed WordPress site.';return;}
 busy=true;button.disabled=true;form.setAttribute('aria-busy','true');status.textContent=ar?'جارٍ إرسال الطلب…':'Sending your enquiry…';
 try{const tr=await fetch(AGForms.tokenUrl,{cache:'no-store',credentials:'same-origin'});if(!tr.ok)throw Error();const token=await tr.json();const body=new FormData(form);body.set('kind',form.dataset.kind);body.set('lang',form.dataset.lang);body.set('time',token.time);body.set('token',token.token);body.set('submission_id',id);
 const response=await fetch(AGForms.submitUrl,{method:'POST',body,credentials:'same-origin'});const result=await response.json();if(!response.ok||!result.success){status.textContent=result.message||(ar?'تعذر الإرسال. حاول مرة أخرى.':'Submission failed. Please try again.');return;}
 status.textContent=result.message+' '+result.reference;form.reset();id=globalThis.crypto?.randomUUID?.()||Date.now().toString(36)+'-'+Math.random().toString(36).slice(2)+'-'+Math.random().toString(36).slice(2);
 }catch{status.textContent=ar?'تعذر الاتصال بالخادم. احتفظ ببياناتك وأعد المحاولة.':'Unable to reach the server. Your fields are preserved; please retry.';}finally{busy=false;button.disabled=false;form.removeAttribute('aria-busy');}
 });
});})();
