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
      await page.evaluate(dark => {document.body.classList.toggle('dark',dark);document.body.classList.toggle('theme-light',!dark);},dark);
      const bounds = await page.evaluate(() => ({
        overflow: document.documentElement.scrollWidth > innerWidth,
        cards: [...document.querySelectorAll('.op-cta,.op-atlas-entity')].map(card => ({
          // Decorative watermark intentionally extends beyond the card and is clipped.
          // Only content and interactive elements must stay within its bounds.
          overflow:[...card.querySelectorAll('.op-cta__body,.op-cta__heading,.op-cta__text,.op-cta__image,.op-cta__button,.op-atlas-copy,.op-atlas-heading,.op-atlas-subtitle,.op-atlas-image,.op-atlas-link,.op-atlas-credit')].some(el=>{
            const a=el.getBoundingClientRect(), b=card.getBoundingClientRect();
            return a.width>0 && (a.left<b.left-1 || a.right>b.right+1 || el.scrollWidth>el.clientWidth+1);
          }),
          background:getComputedStyle(card).backgroundColor,
        }))
      }));
      assert.ok(!bounds.overflow, 'Page overflow at '+width);
      assert.ok(bounds.cards.every(c=>!c.overflow), 'Card overflow at '+width);
      assert.equal(await page.locator('[data-case="atlas-no-image"] img').count(),0);
      assert.equal(await page.locator('[data-case="atlas-no-link"] .op-atlas-link').count(),0);
      assert.equal(await page.locator('[data-case="atlas-empty"] .op-atlas-entity').count(),0);
      assert.equal(await page.locator('[data-case="cta-light"] .op-cta__watermark').count(),1);
      for (const kind of ['public-domain','cc0']) {
        const card=page.locator('[data-case="atlas-'+kind+'"]');
        assert.equal(await card.locator('img').count(),1);
        assert.equal(await card.locator('.op-atlas-credit').count(),0);
        assert.equal(await card.locator('.op-atlas-entity').evaluate(el=>el.lastElementChild.className),'op-atlas-link');
      }
      for(const kind of ['portrait','no-link','share-alike','fallback']){
        const card=page.locator('[data-case="atlas-'+kind+'"]');
        const details=card.locator('details.op-atlas-credit');
        assert.equal(await details.count(),1);
        assert.equal(await details.evaluate(el=>el.open),false);
        assert.equal(await details.locator('.op-atlas-credit-content').first().isVisible(),false);
        await details.locator('summary').focus();
        await page.keyboard.press('Enter');
        assert.equal(await details.evaluate(el=>el.open),true);
        assert.equal(await details.locator('a[href*="creativecommons.org"]').count(),1);
        assert.equal(await details.locator('a[href*="commons.wikimedia.org"]').count(),1);
        assert.ok((await details.innerText()).includes('Autore fixture'));
        await page.keyboard.press('Enter');
        assert.equal(await details.evaluate(el=>el.open),false);
      }
      const buttons=await page.locator('.op-cta__button,.op-atlas-link').evaluateAll(nodes=>nodes.map(a=>({text:a.textContent.trim(),color:getComputedStyle(a).color,background:getComputedStyle(a).backgroundColor,fill:getComputedStyle(a).webkitTextFillColor,height:a.getBoundingClientRect().height,font:parseFloat(getComputedStyle(a).fontSize),dark:!!a.closest('.op-cta--dark')})));
      for(const b of buttons){assert.ok(b.text && b.height>=44 && b.font===14, 'Button label/size regression');assert.equal(b.color,b.dark?'rgb(99, 43, 58)':'rgb(255, 255, 255)');assert.notEqual(b.color,b.background);assert.equal(b.fill,b.color);}
      const heading=await page.locator('[data-case="cta-light"] .op-cta__heading').evaluate(el=>parseFloat(getComputedStyle(el).fontSize));
      assert.ok(heading>=21&&heading<=30,'Oversized sidebar heading');
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
