import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
const browser = await chromium.launch();
const page = await browser.newPage({viewport:{width:1440,height:1000}});
const base = 'http://127.0.0.1:8080/administrator/index.php';
const png=execFileSync('php',['-r','$i=imagecreatetruecolor(2000,1000);imagefilledrectangle($i,0,0,2000,1000,imagecolorallocate($i,80,30,50));imagepng($i);']);
const open=async()=>{
  await page.waitForFunction(()=>window.tinymce?.get('jform_articletext')?.initialized);
  await page.evaluate(()=>tinymce.get('jform_articletext').getContainer().setAttribute('data-ci-gallery-editor','1'));
  await page.locator('[data-ci-gallery-editor="1"]').getByRole('button',{name:'CMS Content',exact:true}).click();
  await page.getByRole('menuitem',{name:'Opera+ photo gallery',exact:true}).click();
  await page.locator('dialog.op-gallery-editor[open]').waitFor();
};
try {
  await page.goto(base);
  await page.locator('#mod-login-username').fill(process.env.JOOMLA_TEST_USER);
  await page.locator('#mod-login-password').fill(process.env.JOOMLA_TEST_PASSWORD);
  await page.getByRole('button',{name:'Log in',exact:true}).click();
  await page.getByText('Home Dashboard',{exact:true}).first().waitFor({timeout:30000});
  await page.goto(base+'?option=com_content&task=article.add');
  await open();
  const dialog=page.locator('dialog.op-gallery-editor[open]');
  await dialog.getByText('Save the new article as unpublished first',{exact:false}).waitFor();
  assert.equal(await dialog.locator('input[type=file]').count(),0);
  await dialog.getByRole('button',{name:'Cancel'}).click();
  await page.locator('#jform_title').fill('CI gallery test');
  await page.locator('#jform_catid').evaluate(select=>{
    const option=Array.from(select.options).find(o=>Number(o.value)>0&&!o.disabled);
    select.value=option.value;select.dispatchEvent(new Event('change',{bubbles:true}));
  });
  await page.locator('#jform_state').evaluate(select=>{
    select.value='0';select.dispatchEvent(new Event('change',{bubbles:true}));
  });
  await page.evaluate(()=>tinymce.get('jform_articletext').setContent('<p>Existing article text</p>'));
  await page.getByRole('button',{name:'Save',exact:true}).click();
  await page.waitForURL(url=>url.searchParams.get('layout')==='edit'&&Number(url.searchParams.get('id'))>0,{timeout:30000});
  const article=Number(new URL(page.url()).searchParams.get('id'));
  await page.waitForFunction(()=>tinymce.get('jform_articletext')?.initialized);
  await page.evaluate(()=>{const ed=tinymce.get('jform_articletext');ed.focus();ed.selection.select(ed.getBody(),true);ed.selection.collapse(false);});
  await open();
  await dialog.locator('input[type=file]').setInputFiles([
    {name:'first.png',mimeType:'image/png',buffer:png},
    {name:'broken.png',mimeType:'image/png',buffer:Buffer.from('not an image')},
    {name:'second.png',mimeType:'image/png',buffer:png},
  ]);
  await dialog.getByText('second.png — WebP ready',{exact:true}).waitFor({timeout:60000});
  await dialog.getByText('broken.png — Upload failed',{exact:false}).waitFor();
  const cards=dialog.locator('.op-gallery-photo');
  assert.equal(await cards.count(),2);
  await dialog.getByLabel('Gallery photo credit').fill('CI photographer');
  await cards.nth(1).getByLabel('Caption',{exact:true}).fill('Second image');
  await cards.nth(1).getByRole('button',{name:'Move up'}).click();
  await dialog.getByLabel('Columns',{exact:true}).selectOption('3');
  await dialog.getByRole('button',{name:'Insert gallery',exact:true}).click();
  await dialog.waitFor({state:'hidden'});
  const content=await page.evaluate(()=>tinymce.get('jform_articletext').getContent());
  assert.ok(content.includes('Existing article text'));
  const match=content.match(/\{operaplusgallery id="([a-f0-9]{24})" columns="3"\}/);
  assert.ok(match,content);
  const folder=join(process.env.JOOMLA_ROOT,'images','galleries','article-'+article,'gallery-'+match[1]);
  const data=JSON.parse(readFileSync(join(folder,'gallery.json'),'utf8'));
  assert.equal(data.credit,'CI photographer');
  assert.equal(data.images.length,2);
  assert.equal(data.images[0].caption,'Second image');
  for(const photo of data.images) for(const suffix of ['.webp','-thumb.webp']){
    assert.equal(readFileSync(join(folder,photo.id+suffix)).subarray(8,12).toString(),'WEBP');
  }
  const endpoint=base+'?option=com_ajax&group=editors-xtd&plugin=operaplusgallery&format=json';
  const denied=await page.request.post(endpoint,{form:{action:'read',article:String(article),gallery:match[1]}});
  assert.equal((await denied.json()).success,false,'Missing CSRF accepted');
  await page.getByRole('button',{name:'Save',exact:true}).click();
  await page.waitForLoadState('networkidle');
  await page.reload();
  await page.waitForFunction(()=>tinymce.get('jform_articletext')?.initialized);
  assert.ok((await page.evaluate(()=>tinymce.get('jform_articletext').getContent())).includes(match[1]));
  console.log('Gallery editor: draft guard, real uploads, failure feedback, WebP files, reorder, shortcode, credits and CSRF passed.');
} catch(e){
  await page.screenshot({path:'dist/editor-runtime/gallery-editor-failure.png',fullPage:true}).catch(()=>{});
  console.error((await page.locator('body').innerText()).slice(0,2500));throw e;
} finally {await browser.close();}
