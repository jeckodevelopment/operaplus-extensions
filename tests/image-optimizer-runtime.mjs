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
 await page.goto(base+'?option=com_media');
 const token=await page.evaluate(()=>Joomla.getOptions('csrf.token'));
 assert.ok(token);
 async function upload(name,path='/ci-optimizer',override=false){
   const res=await page.request.post(base+'?option=com_media&task=api.files&format=json&path='+encodeURIComponent('local-images:'+path),{data:{name,content:png,override,[token]:1}});
   const body=await res.json(); assert.ok(res.ok()&&body.success!==false,JSON.stringify(body));
   return Array.isArray(body.data)?body.data[0]:body.data;
 }
 const one=await upload('cover.png');
 assert.match(one.name,/^cover-[a-f0-9]{16}\.webp$/);
 assert.ok(existsSync(join(folder,one.name)));
 assert.ok(!existsSync(join(folder,'cover.png')));
 const bytes=readFileSync(join(folder,one.name));
 assert.equal(bytes.subarray(8,12).toString(),'WEBP');
 const two=await upload('cover.png');
 assert.notEqual(two.name,one.name);
 assert.deepEqual(readFileSync(join(folder,one.name)),bytes);
 const excluded=await upload('excluded.png','/ci-optimizer/excluded');
 assert.equal(excluded.name,'excluded.png');
 assert.ok(existsSync(join(folder,'excluded','excluded.png')));
 const overwrite=await upload('excluded.png','/ci-optimizer/excluded',true);
 assert.equal(overwrite.name,'excluded.png');
 console.log('Native Media Manager API: returned WebP name, real bytes, no original, collision safety and exclusion passed.');
} finally {await browser.close();}
