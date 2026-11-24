import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const script = readFileSync(new URL('../plg_editorsxtd_operaplus_templates/media/js/templates.js', import.meta.url), 'utf8')
  .replace("import { JoomlaEditorButton } from 'editor-api';", "const JoomlaEditorButton = {registerAction() {}};");
const { buildTemplate } = await import('data:text/javascript;base64,' + Buffer.from(script).toString('base64'));
const labels = Object.fromEntries(['heading','intro','paragraph','question','answer','verdict','context','quote','sources'].map(k => [k, '<unsafe>&"']));
for (const kind of ['article','interview','review','longform','dossier','news']) {
  const html = buildTemplate(kind, labels);
  assert.ok(html.includes('op-editorial-lead'));
  assert.ok(html.includes('&lt;unsafe&gt;&amp;&quot;'));
  assert.ok(!html.includes('<unsafe>'));
  assert.ok(!html.includes('<h1>'));
}
assert.equal(buildTemplate('unknown', labels), '');
assert.equal((buildTemplate('interview', labels).match(/op-interview-question/g) || []).length, 2);
console.log('Editorial template tests passed');
