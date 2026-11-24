import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { mkdirSync,readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const version = readFileSync('VERSION','utf8').trim();
const zip = resolve('dist/pkg_operaplus-'+version+'.zip');
const base = 'http://127.0.0.1:8080';
mkdirSync('dist/web-upload',{recursive:true});
const browser = await chromium.launch();
const page = await browser.newPage({viewport:{width:1440,height:1000}});
try {
  await page.goto(base+'/administrator/index.php');
  await page.locator('#mod-login-username').fill(process.env.JOOMLA_TEST_USER);
  await page.locator('#mod-login-password').fill(process.env.JOOMLA_TEST_PASSWORD);
  await page.getByRole('button',{name:'Log in',exact:true}).click();
  await page.getByText('Home Dashboard',{exact:true}).first().waitFor({state:'visible',timeout:30000});
  await page.waitForLoadState('networkidle');
  await page.goto(base+'/administrator/index.php?option=com_installer&view=install');
  await page.getByRole('tab',{name:'Upload Package File'}).click();
  const picker=page.waitForEvent('filechooser');
  await page.locator('#select-file-button').click();
  const chooser=await picker;
  // File picker uses the regular multipart form, not drag-and-drop AJAX.
  await chooser.setFiles(zip);
  await page.getByText('Installation of the package was successful.',{exact:true}).waitFor({state:'visible',timeout:60000});
  await page.waitForLoadState('domcontentloaded');
  await page.screenshot({path:'dist/web-upload/result.png',fullPage:true});
  console.log('Native Joomla web upload accepted '+zip);
} catch(error) {
  await page.screenshot({path:'dist/web-upload/failure.png',fullPage:true}).catch(()=>{});
  console.error('Page:',page.url(),(await page.locator('body').innerText()).slice(0,3000));
  throw error;
} finally {
  await browser.close();
}
