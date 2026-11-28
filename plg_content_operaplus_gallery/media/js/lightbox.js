(() => {
  'use strict';
  if (!window.HTMLDialogElement || !HTMLDialogElement.prototype.showModal) return;
  const el = (tag, cls='', text='') => {
    const n=document.createElement(tag); n.className=cls;n.textContent=text;return n;
  };
  let active=null;
  function open(gallery,links,start,trigger) {
    if(active) return;
    const dialog=el('dialog','op-photo-lightbox');
    dialog.setAttribute('aria-label',gallery.getAttribute('aria-label')||'Photo gallery');
    const top=el('div','op-photo-lb-top');
    const counter=el('span');counter.setAttribute('aria-live','polite');counter.setAttribute('aria-atomic','true');
    function btn(label,symbol,fn) {
      const b=el('button','',symbol);b.type='button';b.setAttribute('aria-label',label);b.title=label;
      b.addEventListener('click',fn);return b;
    }
    const close=btn(gallery.dataset.close,'×',()=>dialog.close());
    const stage=el('div','op-photo-lb-stage');
    const img=el('img');img.alt='';img.decoding='async';
    const caption=el('div','op-photo-lb-caption');
    const description=el('div'),credit=el('small');caption.append(description,credit);
    let index=start, touch=null;
    function show(next) {
      index=(next+links.length)%links.length;
      const link=links[index];img.alt=link.querySelector('img')?.alt||'';
      img.src=link.href;
      description.textContent=link.dataset.caption||'';credit.textContent=link.dataset.credit||'';
      counter.textContent=(gallery.dataset.counter||'%current% / %total%').replace('%current%',String(index+1)).replace('%total%',String(links.length));
    }
    const previous=btn(gallery.dataset.previous,'‹',()=>show(index-1));
    const next=btn(gallery.dataset.next,'›',()=>show(index+1));
    if(links.length===1){previous.style.visibility='hidden';next.style.visibility='hidden';previous.disabled=true;next.disabled=true;}
    top.append(counter,close);stage.append(previous,img,next);dialog.append(top,stage,caption);
    dialog.addEventListener('keydown',event=>{
      if(event.key==='ArrowLeft'||event.key==='ArrowRight'){event.preventDefault();show(index+(event.key==='ArrowLeft'?-1:1));}
      if(event.key==='Home'||event.key==='End'){event.preventDefault();show(event.key==='Home'?0:links.length-1);}
    });
    img.addEventListener('touchstart',event=>{if(event.touches.length===1)touch=[event.touches[0].clientX,event.touches[0].clientY];else touch=null;},{passive:true});
    img.addEventListener('touchend',event=>{
      if(!touch||!event.changedTouches.length)return;
      const dx=event.changedTouches[0].clientX-touch[0],dy=event.changedTouches[0].clientY-touch[1];touch=null;
      if(Math.abs(dx)>50&&Math.abs(dx)>Math.abs(dy)*1.5)show(index+(dx<0?1:-1));
    },{passive:true});
    dialog.addEventListener('close',()=>{dialog.remove();active=null;trigger.focus();},{once:true});
    document.body.append(dialog);active=dialog;show(start);dialog.showModal();close.focus();
  }
  document.addEventListener('click',event=>{
    const link=event.target.closest?.('.op-photo-link');
    if(!link||event.defaultPrevented||event.button!==0||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;
    const gallery=link.closest('[data-op-gallery]');
    if(!gallery)return;
    const links=Array.from(gallery.querySelectorAll('.op-photo-link'));
    if(!links.length)return;
    event.preventDefault();open(gallery,links,links.indexOf(link),link);
  });
})();
