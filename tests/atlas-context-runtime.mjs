import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {copyFileSync} from 'node:fs';
import {chromium} from 'playwright';
const db=sql=>execFileSync('mysql',['--protocol=TCP','-h','127.0.0.1','-u','joomla_test','-N','-B','joomla_test','-e',sql],{encoding:'utf8'}).trim();
const article=Number(db("SELECT id FROM ci_content WHERE title='CI Atlas field test'"));
const module=Number(db("SELECT id FROM ci_modules WHERE title='CI Atlas contextual'"));
assert.ok(article>0&&module>0,'Missing real persisted fixtures');
const base='http://127.0.0.1:8080/index.php';
const browser=await chromium.launch({headless:true});
const page=await browser.newPage();
const cards=page.locator('.op-atlas-entity');
const headings=page.locator('.op-atlas-heading');
const visit=async(id=article)=>{
  copyFileSync(process.env.ATLAS_FIXTURE,process.env.ATLAS_CACHE);
  return page.goto(id?base+'?option=com_content&view=article&id='+id:base+'?option=com_content&view=featured',{waitUntil:'domcontentloaded'});
};
const primary=(value)=>db("UPDATE ci_fields_values v JOIN ci_fields f ON f.id=v.field_id SET v.value='"+value+"' WHERE f.name='atlas-primary' AND v.item_id='"+article+"'");
const config=(values)=>db("UPDATE ci_modules SET params='"+JSON.stringify({mode:'context',field_name:'atlas-entities',primary_field:'atlas-primary',show_all:0,hide_empty:1,cache:0,...values})+"' WHERE id="+module);
try {
  db("UPDATE ci_content SET state=1,access=1,publish_up=NULL,publish_down=NULL WHERE id="+article);
  const first=await visit();assert.ok(first.ok(),'Public article did not render');
  assert.equal(await cards.count(),1);
  assert.equal(await headings.innerText(),'CI composer','Persisted primary must win');
  assert.equal(await cards.locator('.op-atlas-link').count(),0,'Composer without route must not get a link');
  primary('opera:ci-opera');await visit();
  assert.equal(await headings.innerText(),'CI opera');
  assert.equal(await cards.locator('a.op-atlas-link').getAttribute('href'),'https://opera.plus/atlas/opere/ci-opera/');
  config({show_all:1});await visit();
  assert.equal(await cards.count(),2);assert.equal((await headings.allTextContents())[0],'CI opera');
  config({});primary('theatre:ci-theatre');await visit();
  assert.equal(await cards.count(),1);assert.notEqual(await headings.innerText(),'CI theatre','Unassociated primary must not win');
  // Registered-only association field must never leak to guests.
  db("UPDATE ci_fields SET access=2 WHERE name='atlas-entities'");await visit();assert.equal(await cards.count(),0);
  db("UPDATE ci_fields SET access=1 WHERE name='atlas-entities'");
  db("UPDATE ci_content SET access=2 WHERE id="+article);await visit();assert.equal(await cards.count(),0);
  db("UPDATE ci_content SET access=1,state=0 WHERE id="+article);await visit();assert.equal(await cards.count(),0);
  db("UPDATE ci_content SET state=1,publish_up=DATE_ADD(NOW(),INTERVAL 1 DAY) WHERE id="+article);await visit();assert.equal(await cards.count(),0);
  db("UPDATE ci_content SET publish_up=NULL,publish_down=DATE_SUB(NOW(),INTERVAL 1 DAY) WHERE id="+article);await visit();assert.equal(await cards.count(),0);
  db("UPDATE ci_content SET publish_down=NULL WHERE id="+article);
  // Use the existing unrelated article (without Atlas values), regardless of its title.
  const unrelated=Number(db("SELECT id FROM ci_content WHERE id<>"+article+" AND state=1 ORDER BY id LIMIT 1"));
  assert.ok(unrelated>0,'Missing unrelated article');await visit(unrelated);assert.equal(await cards.count(),0);
  await visit(0);assert.equal(await cards.count(),0);
  config({mode:'manual',references:'opera:ci-opera'});await visit(0);assert.equal(await headings.innerText(),'CI opera');
  config({mode:'manual',references:'opera:missing'});await visit(0);assert.equal(await cards.count(),0);
  config({mode:'manual',references:'opera:missing',hide_empty:0});await visit(0);assert.equal(await page.locator('.op-atlas-empty').count(),1);
  console.log('Native guest frontend: primary, show all, unrelated article, access, publication window, manual and empty states passed.');
} catch(e) {
  console.error('Frontend URL:',page.url(),(await page.locator('body').innerText()).slice(0,1800));
  await page.screenshot({path:'dist/editor-runtime/atlas-context-failure.png',fullPage:true}).catch(()=>{});
  throw e;
} finally {await browser.close();}
