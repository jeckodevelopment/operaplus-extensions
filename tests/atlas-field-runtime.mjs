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
  await page.locator('#mod-login-username').fill(process.env.JOOMLA_TEST_USER);
  await page.locator('#mod-login-password').fill(process.env.JOOMLA_TEST_PASSWORD);
  await page.getByRole('button',{name:'Log in',exact:true}).click();
  await page.locator('#mod-login-username').waitFor({state:'detached',timeout:30000});
  await page.getByText('Home Dashboard',{exact:true}).first().waitFor({state:'visible'});
  await page.waitForLoadState('networkidle');
  await page.goto(base+'/administrator/index.php?option=com_content&filter=');
  await page.waitForLoadState('networkidle');
  await page.goto(base+'/administrator/index.php?option=com_content&task=article.add');
  await page.locator('#jform_title').fill('CI Atlas field test');
  await page.waitForFunction(()=>window.tinymce?.get('jform_articletext')?.initialized, null, {timeout:30000});
  const multiple=page.locator('select[name="jform[com_fields][atlas-entities][]"]');
  const primary=page.locator('select[name="jform[com_fields][atlas-primary]"]');
  await multiple.waitFor({state:'attached'});
  await primary.waitFor({state:'attached'});
  assert.equal(await multiple.evaluate(el=>el.multiple),true);
  assert.equal(await primary.evaluate(el=>el.multiple),false);
  const choose=async (field,values)=>field.evaluate((el,values)=>{
    for(const option of el.options) option.selected=values.includes(option.value);
    el.dispatchEvent(new Event('change',{bubbles:true}));
  },values);
  const values=async field=>field.evaluate(el=>Array.from(el.selectedOptions).map(o=>o.value));
  const assertOptions=async field=>{
    const options=await field.evaluate(el=>Array.from(el.options).map(o=>o.value));
    for(const ref of ['opera:ci-opera','composer:ci-composer','theatre:ci-theatre']) assert.ok(options.includes(ref),'Missing option '+ref);
  };
  await assertOptions(multiple);
  await assertOptions(primary);
  await page.locator('#jform_catid').evaluate(select=>{
    const option=Array.from(select.options).find(o=>Number(o.value)>0&&!o.disabled);
    if(!option) throw new Error('No available content category');
    select.value=option.value;
    select.dispatchEvent(new Event('change',{bubbles:true}));
  });
  await page.waitForLoadState('networkidle');
  await choose(multiple,['opera:ci-opera','composer:ci-composer']);
  await choose(primary,['composer:ci-composer']);
  await page.evaluate(()=>window.tinymce.get('jform_articletext').setContent('<p>Atlas integration test</p>'));
  await page.getByRole('button',{name:'Save',exact:true}).click();
  await page.waitForURL(url=>url.searchParams.get('layout')==='edit'&&Number(url.searchParams.get('id'))>0,{timeout:30000});
  const url=page.url();
  await page.reload();
  await multiple.waitFor({state:'attached'});
  assert.deepEqual((await values(multiple)).sort(),['composer:ci-composer','opera:ci-opera']);
  assert.deepEqual(await values(primary),['composer:ci-composer']);
  // Simulate a catalogue refresh that removes a previously selected entity.
  const {writeFileSync}=await import('node:fs');
  const {join}=await import('node:path');
  const cache=join(process.env.JOOMLA_ROOT,'cache','operaplus-atlas-v1.json');
  writeFileSync(cache,JSON.stringify({schema_version:1,source_revision:'b'.repeat(40),entities:[{type:'opera',id:'ci-opera',title:'CI opera',url:'https://opera.plus/atlas/opere/ci-opera/'}]}));
  await page.goto(url);
  await multiple.waitFor({state:'attached'});
  assert.deepEqual((await values(multiple)).sort(),['composer:ci-composer','opera:ci-opera']);
  assert.deepEqual(await values(primary),['composer:ci-composer']);
  await page.getByRole('button',{name:'Save',exact:true}).click();
  await page.getByText('Article saved.',{exact:true}).waitFor({timeout:30000});
  await page.reload();
  await multiple.waitFor({state:'attached'});
  assert.deepEqual((await values(multiple)).sort(),['composer:ci-composer','opera:ci-opera']);
  assert.deepEqual(await values(primary),['composer:ci-composer']);
  console.log('Atlas field multiple/single selection, save, reload and unavailable reference preservation passed.');
} catch(e) {
  await page.screenshot({path:'dist/editor-runtime/atlas-field-failure.png',fullPage:true}).catch(()=>{});
  console.error('Page URL:',page.url(),'Browser errors:',errors);
  console.error((await page.locator('body').innerText()).slice(0,2500));
  throw e;
} finally {await browser.close();}
