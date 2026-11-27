import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
const browser = await chromium.launch();
const page = await browser.newPage();
const base = 'http://127.0.0.1:8080/administrator/index.php';
const folder = join(process.env.JOOMLA_ROOT,'images','ci-optimizer');
mkdirSync(join(folder,'excluded'),{recursive:true});
const db = sql => execFileSync('mysql',['--protocol=TCP','-h','127.0.0.1','-u','joomla_test','-N','-B','joomla_test','-e',sql],{encoding:'utf8'});
db("UPDATE ci_extensions SET params=JSON_OBJECT('quality',90,'max_edge',1920,'excluded_folders','images/ci-optimizer/excluded') WHERE element='operaplus_imageoptimizer' AND folder='media-action'");
const png=execFileSync('php',['-r','$i=imagecreatetruecolor(2000,1000);imagefilledrectangle($i,0,0,2000,1000,imagecolorallocate($i,80,30,50));imagepng($i,null,0);'],{maxBuffer:20*1024*1024}).toString('base64');
try {
 await page.goto(base);
 await page.locator('#mod-login-username').fill(process.env.JOOMLA_TEST_USER);
 await page.locator('#mod-login-password').fill(process.env.JOOMLA_TEST_PASSWORD);
 await page.getByRole('button',{name:'Log in',exact:true}).click();
 await page.getByText('Home Dashboard',{exact:true}).first().waitFor({state:'visible',timeout:30000});
 await page.goto(base+'?option=com_media&path='+encodeURIComponent('local-images:/ci-optimizer'));
 assert.equal(db("SELECT enabled FROM ci_extensions WHERE element='operaplus_imagefeedback' AND folder='system'").trim(),'1');
 await page.waitForFunction(()=>window.Joomla?.opImageFeedbackInstalled===true);
 const token=await page.evaluate(()=>Joomla.getOptions('csrf.token'));
 assert.ok(token);
 async function upload(name,path='/ci-optimizer',override=false){
   const res=await page.request.post(base+'?option=com_media&task=api.files&format=json&path='+encodeURIComponent('local-images:'+path),{data:{name,content:png,override,[token]:1}});
   const body=await res.json(); assert.ok(res.ok()&&body.success!==false,JSON.stringify(body));
   const messages=Object.values(body.messages||{}).flat();
   assert.ok(messages.some(m=>m.startsWith('Opera+ Image Optimizer: ')),JSON.stringify(body));
   return {item:Array.isArray(body.data)?body.data[0]:body.data,messages};
 }
 const first=await upload('cover.png');
 const one=first.item;
 assert.ok(first.messages.some(m=>m.includes('Converted to WebP')));
 assert.match(one.name,/^cover-[a-f0-9]{16}\.webp$/);
 assert.ok(existsSync(join(folder,one.name)));
 assert.ok(!existsSync(join(folder,'cover.png')));
 const bytes=readFileSync(join(folder,one.name));
 assert.equal(bytes.subarray(8,12).toString(),'WEBP');
 const two=(await upload('cover.png')).item;
 assert.notEqual(two.name,one.name);
 assert.deepEqual(readFileSync(join(folder,one.name)),bytes);
 const excludedResult=await upload('excluded.png','/ci-optimizer/excluded');
 const excluded=excludedResult.item;
 assert.ok(excludedResult.messages.some(m=>m.includes('folder excluded')));
 assert.equal(excluded.name,'excluded.png');
 assert.ok(existsSync(join(folder,'excluded','excluded.png')));
 const overwrite=(await upload('excluded.png','/ci-optimizer/excluded',true)).item;
 assert.equal(overwrite.name,'excluded.png');
 // A real user upload through the native browser UI, not only direct API calls.
 async function uiUpload(name) {
   const response=page.waitForResponse(res=>res.request().method()==='POST'&&res.url().includes('task=api.files'));
   await page.locator('#com-media input[type=file]').first().setInputFiles({name,mimeType:'image/png',buffer:Buffer.from(png,'base64')});
   const res=await response;
   const body=await res.json();
   assert.ok(res.ok()&&body.success!==false,JSON.stringify(body));
   await page.locator('#op-image-feedback').getByText(name,{exact:false}).waitFor();
   return body;
 }
 const ui=await uiUpload('ui-cover.png');
 assert.match(ui.data.name,/\.webp$/);
 assert.ok((await page.locator('#op-image-feedback').innerText()).includes('Converted to WebP'));
 // Reproduce old Joomla media settings that allow PNG but not WebP.
 const oldParams=db("SELECT params FROM ci_extensions WHERE element='com_media' AND type='component'").trim();
 try {
   db("UPDATE ci_extensions SET params=JSON_SET(COALESCE(NULLIF(params,''),'{}'),'$.image_extensions','jpg,jpeg,png') WHERE element='com_media' AND type='component'");
   await page.reload();
   await page.waitForFunction(()=>window.Joomla?.opImageFeedbackInstalled===true);
   const blocked=await uiUpload('ui-policy.png');
   assert.equal(blocked.data.name,'ui-policy.png');
   assert.ok((await page.locator('#op-image-feedback').innerText()).includes('missing from Media image extensions'));
 } finally {
   db("UPDATE ci_extensions SET params=CONVERT(UNHEX('"+Buffer.from(oldParams).toString('hex')+"') USING utf8mb4) WHERE element='com_media' AND type='component'");
 }
 console.log('Native Media Manager API: returned WebP name, real bytes, no original, collision safety and exclusion passed.');
} finally {await browser.close();}
