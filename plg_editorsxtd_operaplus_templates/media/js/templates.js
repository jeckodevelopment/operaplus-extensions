import { JoomlaEditorButton } from 'editor-api';

const escape = (text) => String(text ?? '').replace(/[&<>"']/g, (c) => ({
  '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[c]));

export function buildTemplate(kind, labels) {
  const p = (key, cls = '') => '<p' + (cls ? ' class="' + cls + '"' : '') + '>' + escape(labels[key]) + '</p>';
  const h = (key) => '<h2>' + escape(labels[key]) + '</h2>';
  const lead = p('intro', 'op-editorial-lead');
  const body = p('paragraph');
  const qa = '<p class="op-interview-question"><strong>' + escape(labels.question) + '</strong></p>' + p('answer', 'op-interview-answer');
  const quote = '<blockquote><p>' + escape(labels.quote) + '</p></blockquote>';
  switch (kind) {
    case 'article': return lead + h('heading') + body;
    case 'interview': return lead + qa + qa;
    case 'review': return lead + h('context') + body + h('verdict') + body;
    case 'longform': return lead + h('heading') + body + quote + h('context') + body;
    case 'dossier': return lead + h('context') + body + h('heading') + body + h('sources') + body;
    case 'news': return lead + body;
    default: return '';
  }
}

JoomlaEditorButton.registerAction('operaplus-insert-template', (editor, options) => {
  const labels = options.labels;
  if (!labels) return;
  const dialog = document.createElement('dialog');
  dialog.className = 'p-4 rounded border';
  const form = document.createElement('form');
  form.method = 'dialog';
  const label = document.createElement('label');
  label.className = 'form-label';
  label.textContent = labels.choose;
  const select = document.createElement('select');
  select.className = 'form-select mb-3';
  select.setAttribute('aria-label', labels.choose);
  for (const kind of ['article','interview','review','longform','dossier','news']) {
    const option = document.createElement('option');
    option.value = kind;
    option.textContent = labels[kind];
    select.append(option);
  }
  const insert = document.createElement('button');
  insert.type = 'submit';
  insert.className = 'btn btn-primary me-2';
  insert.value = 'insert';
  insert.textContent = labels.insert;
  const cancel = document.createElement('button');
  cancel.type = 'button';
  cancel.className = 'btn btn-secondary';
  cancel.textContent = labels.cancel;
  cancel.addEventListener('click', () => dialog.close());
  label.append(select);
  form.append(label, document.createElement('div'), insert, cancel);
  dialog.append(form);
  dialog.addEventListener('close', () => {
    if (dialog.returnValue === 'insert') {
      const content = buildTemplate(select.value, labels);
      if (content) editor.replaceSelection(content);
    }
    dialog.remove();
  }, { once: true });
  document.body.append(dialog);
  dialog.showModal();
  select.focus();
});
