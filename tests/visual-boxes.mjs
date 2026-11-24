import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
const browser = await chromium.launch({headless:true});
mkdirSync('dist/visual', {recursive:true});
try {
  const page = await browser.newPage({viewport:{width:1200,height:900}});
  await page.goto(pathToFileURL(resolve('dist/visual/boxes.html')).href);
  for (const width of [1200, 768, 375]) {
    await page.setViewportSize({width,height:900});
    for (const dark of [false,true]) {
      await page.evaluate(dark => document.body.classList.toggle('dark',dark),dark);
      const bounds = await page.evaluate(() => ({
        overflow: document.documentElement.scrollWidth > innerWidth,
        cards: [...document.querySelectorAll('.op-cta,.op-atlas-entity')].map(card => ({
          overflow:card.scrollWidth > card.clientWidth + 1,
          background:getComputedStyle(card).backgroundColor,
        }))
      }));
      assert.ok(!bounds.overflow, 'Page overflow at '+width);
      assert.ok(bounds.cards.every(c=>!c.overflow), 'Card overflow at '+width);
      assert.equal(await page.locator('[data-case="atlas-no-image"] img').count(),0);
      assert.equal(await page.locator('[data-case="atlas-no-link"] .op-atlas-link').count(),0);
      assert.equal(await page.locator('[data-case="atlas-empty"] .op-atlas-entity').count(),0);
      assert.equal(await page.locator('[data-case="cta-light"] .op-cta__watermark').count(),1);
      const layout = await page.locator('[data-case="atlas-portrait"] .op-atlas-entity').evaluate(el => {
        const copy=el.querySelector('.op-atlas-copy').getBoundingClientRect();
        const image=el.querySelector('.op-atlas-image').getBoundingClientRect();
        const link=el.querySelector('.op-atlas-link').getBoundingClientRect();
        return {right:image.left>=copy.right-1, below:link.top>=Math.max(copy.bottom,image.bottom)-1};
      });
      assert.ok(layout.right && layout.below, 'Portrait or CTA misplaced at '+width);
      await page.screenshot({path:'dist/visual/boxes-'+width+(dark?'-dark':'-light')+'.png',fullPage:true});
    }
  }
  console.log('Editorial boxes: desktop, tablet, mobile and dark fixtures passed.');
} finally { await browser.close(); }
