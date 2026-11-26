import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
const browser=await chromium.launch();
const page=await browser.newPage({viewport:{width:1440,height:1000}});
const base='http://127.0.0.1:8080/administrator/index.php';
const id=Number(process.env.CI_MENU_ID);
assert.ok(id>0);
const db=sql=>execFileSync('mysql',['--protocol=TCP','-h','127.0.0.1','-u','joomla_test','-N','-B','joomla_test','-e',sql],{encoding:'utf8'}).trim();
try {
  await page.goto(base);
  await page.locator('#mod-login-username').fill(process.env.JOOMLA_TEST_USER);
  await page.locator('#mod-login-password').fill(process.env.JOOMLA_TEST_PASSWORD);
  await page.getByRole('button',{name:'Log in',exact:true}).click();
  await page.getByText('Home Dashboard',{exact:true}).first().waitFor({state:'visible',timeout:30000});
  await page.goto(base+'?option=com_menus&task=item.edit&id='+id+'&client_id=0');
  await page.getByRole('tab',{name:'Editorial heading',exact:true}).click();
  const subtitle=page.locator('#jform_params_op_masthead_subtitle');
  await subtitle.fill('CI editorial subtitle');
  await page.locator('#jform_params_op_masthead_height').selectOption('normal');
  await page.locator('[name="jform[params][op_masthead_image]"]').waitFor({state:'attached'});
  await page.getByRole('button',{name:'Save',exact:true}).click();
  await page.waitForURL(url=>url.searchParams.get('layout')==='edit'&&Number(url.searchParams.get('id'))===id,{timeout:30000});
  await page.reload();
  await page.getByRole('tab',{name:'Editorial heading',exact:true}).click();
  assert.equal(await subtitle.inputValue(),'CI editorial subtitle');
  assert.equal(await page.locator('#jform_params_op_masthead_height').inputValue(),'normal');
  const saved=JSON.parse(db('SELECT params FROM ci_menu WHERE id='+id));
  assert.equal(saved.op_masthead_subtitle,'CI editorial subtitle');
  assert.equal(saved.op_masthead_height,'normal');
  console.log('Native site menu editor: plugin tab, save and reload passed.');
} catch(e){
  await page.screenshot({path:'dist/editor-runtime/masthead-failure.png',fullPage:true}).catch(()=>{});
  console.error(page.url(),(await page.locator('body').innerText()).slice(0,4000));throw e;
} finally {await browser.close();}
