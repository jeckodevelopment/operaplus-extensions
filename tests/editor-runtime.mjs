import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';
const base = 'http://127.0.0.1:8080';
const browser = await chromium.launch({headless:true});
mkdirSync('dist/editor-runtime',{recursive:true});
const page = await browser.newPage({viewport:{width:1440,height:1000}});
const errors=[];
page.on('pageerror', e=>errors.push(String(e)));
try {
  await page.goto(base+'/administrator/index.php');
  await page.locator('#mod-login-username').fill('ciadmin');
  await page.locator('#mod-login-password').fill('CiTestPassword-2026!');
  await page.getByRole('button',{name:'Log in',exact:true}).click();
  await page.locator('#mod-login-username').waitFor({state:'detached',timeout:30000});
  await page.goto(base+'/administrator/index.php?option=com_content&task=article.add');
  await page.locator('#jform_title').fill('CI editorial template test');
  await page.waitForFunction(()=>window.tinymce?.get('jform_articletext')?.initialized, {timeout:30000});
  const setContent = async html => page.evaluate(html=>{
    const ed=window.tinymce.get('jform_articletext');
    ed.setContent(html); ed.focus(); ed.selection.select(ed.getBody(),true); ed.selection.collapse(false);
  },html);
  const getContent = () => page.evaluate(()=>window.tinymce.get('jform_articletext').getContent());
  const openTemplates = async () => {
    // Use the actual TinyMCE menu, not a simulated editor adapter.
    await page.locator('.tox-toolbar button[aria-label="CMS Content"], .tox-toolbar button[title="CMS Content"], .tox-toolbar button:has-text("CMS Content"), .tox-toolbar-overlord button[aria-label="CMS Content"]').first().click();
    await page.getByRole('menuitem',{name:'Opera+ templates',exact:true}).click();
    await page.locator('dialog[open] select').waitFor();
  };
  await setContent('<p>Existing article text</p>');
  const before=await getContent();
  await openTemplates();
  await page.locator('dialog[open]').getByRole('button',{name:'Cancel',exact:true}).click();
  await page.locator('dialog[open]').waitFor({state:'hidden'});
  assert.equal(await getContent(),before,'Cancel altered article');
  for (const kind of ['article','interview','review','longform','dossier','news']) {
    await setContent('<p>Existing article text</p>');
    await openTemplates();
    await page.locator('dialog[open] select').selectOption(kind);
    await page.screenshot({path:'dist/editor-runtime/'+kind+'-chooser.png'});
    await page.locator('dialog[open]').getByRole('button',{name:'Insert',exact:true}).click();
    await page.locator('dialog[open]').waitFor({state:'hidden'});
    await page.waitForFunction(()=>window.tinymce.get('jform_articletext').getContent().includes('op-editorial-lead'));
    const content=await getContent();
    assert.ok(content.includes('Existing article text'),'Existing content overwritten');
    if(kind==='interview') assert.ok(content.includes('op-interview-question')&&content.includes('op-interview-answer'));
    if(kind==='longform') assert.ok(content.includes('<blockquote>'));
    console.log('TinyMCE inserted '+kind);
  }
  await setContent('<p>Existing article text</p>');
  await openTemplates();
  await page.locator('dialog[open] select').selectOption('interview');
  await page.locator('dialog[open]').getByRole('button',{name:'Insert',exact:true}).click();
  await page.waitForFunction(()=>window.tinymce.get('jform_articletext').getContent().includes('op-interview-question'));
  await page.screenshot({path:'dist/editor-runtime/interview-in-editor.png'});
  await page.locator('#toolbar-apply button, #toolbar-apply').first().click();
  await page.waitForURL(url=>url.searchParams.get('layout')==='edit'&&Number(url.searchParams.get('id'))>0,{timeout:30000});
  await page.reload();
  await page.waitForFunction(()=>window.tinymce?.get('jform_articletext')?.initialized);
  const saved=await getContent();
  assert.ok(saved.includes('Existing article text')&&saved.includes('op-interview-question')&&saved.includes('op-interview-answer'),'Saved article lost editorial markup');
  console.log('TinyMCE cancel, six inserts and persisted interview markup passed.');
} catch(e) {
  await page.screenshot({path:'dist/editor-runtime/failure.png',fullPage:true}).catch(()=>{});
  console.error('Page URL:',page.url(),'Browser errors:',errors);
  console.error((await page.locator('body').innerText()).slice(0,2500));
  throw e;
} finally {await browser.close();}
