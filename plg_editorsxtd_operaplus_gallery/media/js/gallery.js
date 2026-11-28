import { JoomlaEditorButton } from 'editor-api';

const create = (tag, text = '', cls = '') => {
  const node = document.createElement(tag);
  node.textContent = text;
  if (cls) node.className = cls;
  return node;
};
const button = (text, fn, cls = 'btn btn-secondary btn-sm') => {
  const node = create('button', text, cls);
  node.type = 'button';
  node.addEventListener('click', fn);
  return node;
};
const field = (text, node) => {
  const label = create('label', text);
  label.append(node);
  return label;
};
const photoId = id => typeof id === 'string' && /^[a-f0-9]{24}$/.test(id);

JoomlaEditorButton.registerAction('operaplus-insert-gallery', (editor, options) => {
  const l = options.labels;
  if (!l || document.querySelector('.op-gallery-editor[open]')) return;
  const dialog = create('dialog', '', 'op-gallery-editor');
  dialog.setAttribute('aria-label', l.title);
  dialog.append(create('h2', l.title));
  const article = Number(options.article);
  const close = () => dialog.close();
  dialog.addEventListener('close', () => dialog.remove(), { once: true });
  document.body.append(dialog);
  if (!Number.isInteger(article) || article < 1) {
    dialog.append(create('p', l.draft), button(l.cancel, close));
    dialog.showModal();
    return;
  }
  let gallery = '', photos = [], expected = [], busy = false, inserted = false, dragId = '';
  const status = create('p', '', 'op-gallery-status');
  status.setAttribute('role', 'status');
  const log = create('ul', '', 'op-gallery-log');
  log.setAttribute('aria-live', 'polite');
  const preview = create('div', '', 'op-gallery-preview');
  const drop = create('div', l.drop, 'op-gallery-drop');
  const picker = create('input');
  picker.type = 'file'; picker.accept = 'image/jpeg,image/png,image/webp'; picker.multiple = true;
  picker.setAttribute('aria-label', l.choose);
  drop.append(picker);
  const credit = create('input', '', 'form-control'); credit.maxLength = 250;
  const columns = create('select', '', 'form-select');
  for (const count of [2,3,4]) {
    const opt = create('option', String(count)); opt.value = String(count); columns.append(opt);
  }
  columns.value = '4';
  const actions = create('div', '', 'op-gallery-actions');
  const insert = button(l.insert, () => save(), 'btn btn-primary');
  const cancel = button(l.cancel, () => attemptClose());
  actions.append(insert, cancel);
  dialog.append(drop, status, log, field(l.credit,credit), field(l.columns,columns), preview, actions);
  const controls = () => {
    picker.disabled = busy;
    insert.disabled = busy || !photos.length;
    cancel.disabled = busy;
    credit.disabled = busy;
    columns.disabled = busy;
    preview.querySelectorAll('button,input').forEach(el => el.disabled = busy);
    preview.querySelectorAll('[draggable]').forEach(el => el.draggable = !busy);
  };
  const attemptClose = () => {
    if (busy) return;
    if (!gallery || inserted || window.confirm(l.confirmcancel)) close();
  };
  dialog.addEventListener('cancel', e => {e.preventDefault(); attemptClose();});
  async function request(action, args={}) {
    const data = new FormData();
    data.set(options.token, '1');
    data.set('article', String(article)); data.set('action', action);
    if (gallery) data.set('gallery', gallery);
    for (const [k,v] of Object.entries(args)) data.set(k,v);
    const response = await fetch(options.endpoint, {method:'POST',body:data,credentials:'same-origin',cache:'no-store'});
    const result = await response.json();
    if (!response.ok || !result.success || !Array.isArray(result.data) || !result.data[0]) {
      throw new Error(typeof result.message === 'string' && result.message ? result.message : l.error);
    }
    return result.data[0];
  }
  function render() {
    preview.replaceChildren();
    photos.forEach((photo,i) => {
      const card = create('div', '', 'op-gallery-photo');
      card.draggable = !busy; card.dataset.photo = photo.id;
      const image = create('img');
      image.src = options.root + 'images/galleries/article-' + article + '/gallery-' + gallery + '/' + photo.id + '-thumb.webp';
      image.alt = photo.alt || '';
      card.append(image);
      for (const [key,label] of [['alt',l.alt],['caption',l.caption],['credit',l.individual]]) {
        const input = create('input','','form-control');
        input.value = photo[key] || ''; input.maxLength = 250;
        input.addEventListener('input', () => {photo[key] = input.value; if(key==='alt') image.alt=input.value;});
        card.append(field(label,input));
      }
      const a = create('div','','op-gallery-actions');
      const move = to => {
        if (busy || to<0 || to>=photos.length) return;
        photos.splice(to,0,photos.splice(i,1)[0]); render();
      };
      a.append(button(l.up,()=>move(i-1)), button(l.down,()=>move(i+1)), button(l.remove,()=>{
        if (!busy) {photos.splice(i,1);render();}
      }));
      card.append(a);
      card.addEventListener('dragstart',e=>{
        if (busy || e.target.closest('input,button')) {e.preventDefault();return;}
        dragId=photo.id;e.dataTransfer.setData('text/plain',dragId);e.dataTransfer.effectAllowed='move';
      });
      card.addEventListener('dragend',()=>{dragId='';});
      card.addEventListener('dragover',e=>{if(dragId)e.preventDefault();});
      card.addEventListener('drop',e=>{
        if(!dragId||busy)return;e.preventDefault();e.stopPropagation();
        const from=photos.findIndex(p=>p.id===dragId);
        if(from>=0&&from!==i){photos.splice(i,0,photos.splice(from,1)[0]);render();}
        dragId='';
      });
      preview.append(card);
    });
    controls();
  }
  async function upload(list) {
    if(busy || !list.length)return;
    busy=true;controls();
    let failed=0;
    try {
      if(!gallery){
        const result=await request('create');
        if(!photoId(result.gallery))throw new Error(l.error);
        gallery=result.gallery;
      }
      const files=Array.from(list);
      for(let i=0;i<files.length;i++){
        if(expected.length>=Number(options.limit)){status.textContent=l.limit;break;}
        const file=files[i];
        status.textContent=l.busy+' '+(i+1)+' / '+files.length;
        const item=create('li',file.name+' — '+l.busy);log.append(item);
        try {
          if(file.size>20*1024*1024 || !/\.(jpe?g|png|webp)$/i.test(file.name))throw new Error(l.failed);
          const result=await request('upload',{photo:file});
          if(!photoId(result.id))throw new Error(l.error);
          expected.push(result.id);photos.push(result);
          item.textContent=file.name+' — '+l.ready;
          render();
        } catch(error){failed++;item.textContent=file.name+' — '+l.failed+': '+error.message;}
      }
      status.textContent=(failed?l.retry:l.ready)+' ('+photos.length+')';
    }catch(error){status.textContent=error.message;}
    finally{busy=false;picker.value='';controls();}
  }
  async function save() {
    if(busy||!photos.length)return;
    busy=true;controls();status.textContent=l.busy;
    try {
      await request('save',{metadata:JSON.stringify({images:photos,credit:credit.value,expected})});
      const count=Number(columns.value);
      if(![2,3,4].includes(count)||!photoId(gallery))throw new Error(l.error);
      editor.replaceSelection('<p>{operaplusgallery id="'+gallery+'" columns="'+count+'"}</p>');
      inserted=true;close();
    }catch(error){status.textContent=error.message;}
    finally{busy=false;controls();}
  }
  picker.addEventListener('change',()=>upload(picker.files));
  drop.addEventListener('dragover',e=>{e.preventDefault();if(!busy)drop.classList.add('is-over');});
  drop.addEventListener('dragleave',()=>drop.classList.remove('is-over'));
  drop.addEventListener('drop',e=>{e.preventDefault();drop.classList.remove('is-over');if(!busy)upload(e.dataTransfer.files);});
  controls();dialog.showModal();
});
