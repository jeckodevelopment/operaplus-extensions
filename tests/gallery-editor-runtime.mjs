import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import { readFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';
const browser = await chromium.launch();
const page = await browser.newPage({viewport:{width:1440,height:1000}});
const base = 'http://127.0.0.1:8080/administrator/index.php';
const png=execFileSync('php',['-r','$i=imagecreatetruecolor(2000,1000);imagefilledrectangle($i,0,0,2000,1000,imagecolorallocate($i,80,30,50));imagepng($i);']);
const portrait=execFileSync('php',['-r','$i=imagecreatetruecolor(800,1600);imagefilledrectangle($i,0,0,800,1600,imagecolorallocate($i,30,80,50));imagepng($i);']);
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
  await page.waitForFunction(()=>tinymce.get('jform_articletext')?.initialized);
  await page.evaluate(()=>{const ed=tinymce.get('jform_articletext');ed.setContent('<p>Existing article text</p>');ed.save();});
  assert.ok((await page.evaluate(()=>tinymce.get('jform_articletext').getContent())).includes('Existing article text'),'Fixture before initial save');
  await page.getByRole('button',{name:'Save',exact:true}).click();
  await page.waitForURL(url=>url.searchParams.get('layout')==='edit'&&Number(url.searchParams.get('id'))>0,{timeout:30000});
  const article=Number(new URL(page.url()).searchParams.get('id'));
  await page.waitForFunction(()=>tinymce.get('jform_articletext')?.initialized);
  assert.ok((await page.evaluate(()=>tinymce.get('jform_articletext').getContent())).includes('Existing article text'),'Fixture after initial save');
  await page.evaluate(()=>{const ed=tinymce.get('jform_articletext');ed.focus();ed.selection.select(ed.getBody(),true);ed.selection.collapse(false);});
  await open();
  const limitNote=dialog.locator('.op-gallery-upload-limit');
  await limitNote.waitFor();
  assert.match(await limitNote.innerText(),/Maximum file size per photo: [\d.,]+ MiB/);
  await dialog.locator('input[type=file]').setInputFiles([
    {name:'oversized.png',mimeType:'image/png',buffer:Buffer.alloc(20*1024*1024+1)},
    {name:'first.png',mimeType:'image/png',buffer:png},
    {name:'broken.png',mimeType:'image/png',buffer:Buffer.from('not an image')},
    {name:'second.png',mimeType:'image/png',buffer:portrait},
  ]);
  await dialog.getByText('second.png — WebP ready',{exact:true}).waitFor({timeout:60000});
  await dialog.getByText('broken.png — Upload failed',{exact:false}).waitFor();
  await dialog.getByText('oversized.png — Upload failed: The photo exceeds the allowed upload size.',{exact:true}).waitFor();
  const cards=dialog.locator('.op-gallery-photo');
  assert.equal(await cards.count(),2);
  assert.equal(await cards.first().locator('img').evaluate(el=>getComputedStyle(el).backgroundColor),'rgba(0, 0, 0, 0)');
  await dialog.getByLabel('Gallery photo credit').fill('CI photographer');
  await cards.nth(1).getByLabel('Caption',{exact:true}).fill('Second image');
  await cards.nth(1).getByRole('button',{name:'Move up'}).click();
  await dialog.getByLabel('Columns',{exact:true}).selectOption('3');
  await dialog.getByRole('button',{name:'Insert gallery',exact:true}).click();
  await dialog.waitFor({state:'hidden'});
  const content=await page.evaluate(()=>tinymce.get('jform_articletext').getContent());
  assert.ok(content.includes('Existing article text'),content);
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
  const token = await page.evaluate(()=>Joomla.getOptions('csrf.token'));
  async function api(action,args={}){
    const res=await page.request.post(endpoint,{form:{[token]:'1',article:String(article),gallery:match[1],action,...args}});
    return res.json();
  }
  assert.equal((await api('read')).success,true);
  assert.equal((await api('discard')).success,false,'Committed gallery deleted');
  assert.equal((await api('read',{article:'999999999'})).success,false,'Nonexistent article accepted');
  const temp=(await api('create')).data[0].gallery;
  const tempFolder=join(process.env.JOOMLA_ROOT,'images','galleries','article-'+article,'gallery-'+temp);
  assert.ok(JSON.parse(readFileSync(join(tempFolder,'gallery.json'),'utf8')).stage_until);
  assert.equal((await api('discard',{gallery:temp})).success,true);
  assert.equal(existsSync(tempFolder),false);
  await page.getByRole('button',{name:'Save',exact:true}).click();
  await page.waitForLoadState('networkidle');
  await page.reload();
  await page.waitForFunction(()=>tinymce.get('jform_articletext')?.initialized);
  assert.ok((await page.evaluate(()=>tinymce.get('jform_articletext').getContent())).includes(match[1]));
  // Existing gallery editing uses a copy: cancel leaves the old revision untouched.
  await open();
  await dialog.getByLabel('Existing galleries',{exact:true}).selectOption('1');
  await dialog.getByRole('button',{name:'Apply gallery changes'}).waitFor();
  await dialog.locator('.op-gallery-photo').first().getByLabel('Caption',{exact:true}).fill('Updated caption');
  await dialog.getByRole('button',{name:'Apply gallery changes'}).click();
  await dialog.waitFor({state:'hidden'});
  const updatedContent=await page.evaluate(()=>tinymce.get('jform_articletext').getContent());
  const edited=updatedContent.match(/\{operaplusgallery id="([a-f0-9]{24})" columns="3"\}/);
  assert.ok(edited && edited[1]!==match[1]);
  assert.equal(JSON.parse(readFileSync(join(folder,'gallery.json'),'utf8')).images[0].caption,'Second image');
  await page.getByRole('button',{name:'Save',exact:true}).click();
  await page.waitForLoadState('networkidle');
  const db = sql => execFileSync('mysql',['--protocol=TCP','-h','127.0.0.1','-u','joomla_test','-N','-B','joomla_test','-e',sql],{encoding:'utf8'});
  db('UPDATE ci_content SET state=1, access=1, publish_up=NULL, publish_down=NULL WHERE id='+article);
  const guest = await browser.newPage({viewport:{width:1440,height:1000}});
  await guest.goto('http://127.0.0.1:8080/index.php?option=com_content&view=article&id='+article);
  assert.equal(await guest.locator('script[src*="plg_content_operaplus_gallery"]').count(),1);
  const grid=guest.locator('.op-photo-gallery');
  await grid.waitFor();
  assert.equal(await grid.locator('.op-photo-link').first().evaluate(el=>getComputedStyle(el).backgroundColor),'rgba(0, 0, 0, 0)');
  assert.equal(await grid.locator('a.op-photo-link').count(),2);
  assert.ok((await grid.innerText()).includes('CI photographer'));
  assert.ok((await grid.innerText()).includes('Updated caption'));
  const first=grid.locator('a.op-photo-link').first();
  const href=await first.getAttribute('href');
  await first.click();
  const lightbox=guest.locator('dialog.op-photo-lightbox[open]');
  await lightbox.waitFor();
  assert.equal(await lightbox.locator('img').getAttribute('src'),href);
  await guest.keyboard.press('ArrowRight');
  assert.notEqual(await lightbox.locator('img').getAttribute('src'),href);
  await guest.keyboard.press('Escape');
  await lightbox.waitFor({state:'hidden'});
  assert.equal(await first.evaluate(el=>document.activeElement===el),true);
  async function checkLayout(){
    const result=await grid.locator('.op-photo-grid').evaluate(el=>{
      const bounds=el.getBoundingClientRect();
      const links=[...el.querySelectorAll('.op-photo-link')].map(link=>{
        const box=link.getBoundingClientRect(), img=link.querySelector('img'), ib=img.getBoundingClientRect();
        return {x:box.x,right:box.right,y:box.y,width:box.width,height:box.height,imgHeight:ib.height,imgWidth:ib.width,fit:getComputedStyle(img).objectFit};
      });
      return {left:bounds.left,right:bounds.right,links};
    });
    const [a,b]=result.links;
    assert.ok(Math.abs(a.height-b.height)<1 && Math.abs(a.width-b.width)<1,'Unequal thumbnail boxes');
    assert.ok(Math.abs(a.height-a.width*0.75)<1,'Expected 4:3 frame');
    assert.ok(Math.abs(a.y-b.y)<1,'Expected same row');
    assert.ok(Math.abs(a.x-result.left-(result.right-b.right))<1,'Gallery row not centered');
    for(const x of result.links){assert.equal(x.fit,'cover');assert.ok(Math.abs(x.imgHeight-x.height)<1&&Math.abs(x.imgWidth-x.width)<1,'Photo does not fill thumbnail');}
  }
  await checkLayout();
  await guest.setViewportSize({width:390,height:844});
  await checkLayout();
  await guest.goto('http://127.0.0.1:8080/index.php');
  assert.equal(await guest.locator('script[src*="plg_content_operaplus_gallery"]').count(),0,'Gallery assets loaded on unrelated page');
  await guest.close();
  console.log('Gallery editor and frontend: draft guard, real uploads, failure feedback, WebP files, reorder, shortcode, credits and CSRF passed.');
} catch(e){
  await page.screenshot({path:'dist/editor-runtime/gallery-editor-failure.png',fullPage:true}).catch(()=>{});
  console.error((await page.locator('body').innerText()).slice(0,2500));throw e;
} finally {await browser.close();}
