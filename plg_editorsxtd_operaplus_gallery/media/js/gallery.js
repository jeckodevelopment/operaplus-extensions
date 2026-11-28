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
  node.setAttribute('aria-label',text);
  label.append(node);
  return label;
};
const photoId = id => typeof id === 'string' && /^[a-f0-9]{24}$/.test(id);

JoomlaEditorButton.registerAction('operaplus-insert-gallery', (editor, options) => {
  const l = options.labels;
  if (!l || document.querySelector('.op-gallery-editor[open]')) return;
  // Capture the actual editor range before the modal takes focus. TinyMCE
  // versions differ in how an asynchronous editor-button action restores it.
  const raw = editor.getType?.() === 'tinymce' ? editor.getRawInstance() : null;
  const bookmark = raw?.selection?.getBookmark(2, true);
  const selection = editor.getSelection?.() || '';
  const initial = editor.getValue();
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
  let gallery = '', photos = [], expected = [], busy = false, inserted = false, dragId = '', originalToken = '';
  const status = create('p', '', 'op-gallery-status');
  status.setAttribute('role', 'status');
  const log = create('ul', '', 'op-gallery-log');
  log.setAttribute('aria-live', 'polite');
  const preview = create('div', '', 'op-gallery-preview');
  const drop = create('div', l.drop, 'op-gallery-drop');
  const picker = create('input');
  picker.type = 'file'; picker.accept = 'image/jpeg,image/png,image/webp'; picker.multiple = true;
  picker.setAttribute('aria-label', l.choose);
  const maxBytes = Number(options.maxUploadBytes);
  const validLimit = Number.isSafeInteger(maxBytes) && maxBytes > 0;
  const maxText = validLimit ? new Intl.NumberFormat(document.documentElement.lang || undefined, {maximumFractionDigits:2}).format(Math.floor(maxBytes / 1048576 * 100) / 100) + ' MiB' : '';
  const limitNote = create('p', validLimit ? l.maxupload.replace('%s',maxText) : l.uploadunavailable,'op-gallery-upload-limit');
  limitNote.id = 'op-gallery-upload-limit';
  picker.setAttribute('aria-describedby',limitNote.id);
  drop.append(picker,limitNote);
  const credit = create('input', '', 'form-control'); credit.maxLength = 250;
  const columns = create('select', '', 'form-select');
  for (const count of [2,3,4]) {
    const opt = create('option', String(count)); opt.value = String(count); columns.append(opt);
  }
  columns.value = '4';
  const existing = create('select','','form-select');
  const newOption = create('option',l.new);newOption.value='';existing.append(newOption);
  const refs = Array.from(editor.getValue().matchAll(/\{operaplusgallery\s+id=(?:"|&quot;)([a-f0-9]{24})(?:"|&quot;)\s+columns=(?:"|&quot;)([234])(?:"|&quot;)\}/g));
  refs.forEach((ref,i)=>{const option=create('option',l.existing+' '+(i+1));option.value=String(i+1);existing.append(option);});
  const existingLabel=field(l.existing,existing);
  existingLabel.hidden=!refs.length;
  const actions = create('div', '', 'op-gallery-actions');
  const insert = button(l.insert, () => save(), 'btn btn-primary');
  const cancel = button(l.cancel, () => attemptClose());
  actions.append(insert, cancel);
  dialog.append(existingLabel, drop, status, log, field(l.credit,credit), field(l.columns,columns), preview, actions);
  const controls = () => {
    picker.disabled = busy || !validLimit;
    existing.disabled = busy || !!gallery;
    insert.disabled = busy || !photos.length;
    cancel.disabled = busy;
    credit.disabled = busy;
    columns.disabled = busy;
    preview.querySelectorAll('button,input').forEach(el => el.disabled = busy);
    preview.querySelectorAll('[draggable]').forEach(el => el.draggable = !busy);
  };
  const attemptClose = async () => {
    if(busy)return;
    if(!gallery||inserted){close();return;}
    if(!window.confirm(l.confirmcancel))return;
    busy=true;controls();
    try {await request('discard');close();}
    catch(error){status.textContent=error.message;}
    finally{busy=false;controls();}
  };
  dialog.addEventListener('cancel', e => {e.preventDefault(); attemptClose();});
  async function request(action, args={}) {
    const data = new FormData();
    data.set(options.token, '1');
    data.set('article', String(article)); data.set('action', action);
    if (gallery) data.set('gallery', gallery);
    for (const [k,v] of Object.entries(args)) data.set(k,v);
    const response = await fetch(options.endpoint, {method:'POST',body:data,credentials:'same-origin',cache:'no-store'});
    let result;
    try { result = await response.json(); } catch(error) { throw new Error(response.status === 413 ? l.toolarge : l.error); }
    if (!response.ok || !result.success || !Array.isArray(result.data) || !result.data[0]) {
      throw new Error(typeof result.message === 'string' && result.message ? result.message : l.error);
    }
    return result.data[0];
  }
  existing.addEventListener('change',async()=>{
    const ref=refs[Number(existing.value)-1];
    if(busy||gallery||!ref)return;
    busy=true;controls();
    try {
      const result=await request('fork',{gallery:ref[1]});
      if(!photoId(result.gallery)||!Array.isArray(result.images))throw new Error(l.error);
      gallery=result.gallery;originalToken=ref[0];photos=result.images;expected=photos.map(p=>p.id);
      credit.value=result.credit||'';columns.value=ref[2];insert.textContent=l.apply;render();
      status.textContent=l.ready+' ('+photos.length+')';
    }catch(error){existing.value='';status.textContent=error.message;}
    finally{busy=false;controls();}
  });
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
    if(busy || !validLimit || !list.length)return;
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
          if(file.size>maxBytes)throw new Error(l.toolarge);
          if(!/\.(jpe?g|png|webp)$/i.test(file.name))throw new Error(l.failed);
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
      const count=Number(columns.value);
      if(![2,3,4].includes(count)||!photoId(gallery))throw new Error(l.error);
      const token='{operaplusgallery id="'+gallery+'" columns="'+count+'"}';
      const content=editor.getValue();
      if(originalToken&&!content.includes(originalToken))throw new Error(l.changed);
      await request('save',{metadata:JSON.stringify({images:photos,credit:credit.value,expected})});
      inserted=true;
      close();
      if(originalToken) {
        editor.setValue(content.split(originalToken).join(token));
      } else {
        const markup='<p>'+token+'</p>';
        if(raw?.selection && bookmark && content===initial) {
          try {
            raw.focus();
            raw.selection.moveToBookmark(bookmark);
            raw.selection.collapse(false);
            raw.undoManager.transact(()=>raw.insertContent(markup));
            raw.save();
          } catch(error) {
            editor.setValue(content+markup);
          }
        } else if(selection || content!==initial) {
          // A selected passage or stale bookmark must never be overwritten.
          editor.setValue(content+markup);
        } else {
          editor.replaceSelection(markup);
        }
        // Last-resort guard against editor adapters losing content or selection.
        // Compare text excluding the newly inserted token, ignoring whitespace.
        const textOf=html=>{const doc=new DOMParser().parseFromString(html,'text/html');return (doc.body.textContent||'').replace(/\s+/g,'');};
        const after=editor.getValue();
        if(!after.includes(gallery) || textOf(after.replace(token,'')).indexOf(textOf(content))===-1) {
          editor.setValue(content+markup);
        }
      }
    }catch(error){status.textContent=error.message;}
    finally{busy=false;controls();}
  }
  picker.addEventListener('change',()=>upload(picker.files));
  drop.addEventListener('dragover',e=>{e.preventDefault();if(!busy)drop.classList.add('is-over');});
  drop.addEventListener('dragleave',()=>drop.classList.remove('is-over'));
  drop.addEventListener('drop',e=>{e.preventDefault();drop.classList.remove('is-over');if(!busy)upload(e.dataTransfer.files);});
  controls();dialog.showModal();
});
