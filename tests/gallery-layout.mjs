import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { readFileSync } from 'node:fs';

const css=readFileSync('plg_content_operaplus_gallery/media/css/gallery.css','utf8');
const browser=await chromium.launch();
const page=await browser.newPage({viewport:{width:1200,height:900}});
const image=(w,h)=>'data:image/svg+xml,'+encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="'+w+'" height="'+h+'"><rect width="100%" height="100%" fill="tomato"/></svg>');
try {
  for(const width of [900,600,360]){
    for(const columns of [2,3,4]){
      for(const count of [1,2,3,5,7]){
        const figures=Array.from({length:count},(_,i)=>'<figure class="op-photo"><a class="op-photo-link" href="#"><img src="'+image(i%2?120:1200,i%2?900:240)+'" width="480" height="360" alt=""></a><figcaption>'+('Caption '.repeat(i+1))+'</figcaption></figure>').join('');
        await page.setContent('<style>body{margin:0}img{height:auto;max-width:100%}article{width:'+width+'px;margin:auto}figure{margin:1em}</style><style>'+css+'</style><article><section class="op-photo-gallery"><div class="op-photo-grid op-photo-cols-'+columns+'">'+figures+'</div></section></article>');
        const layout=await page.locator('.op-photo-grid').evaluate(el=>{
          const rect=el.getBoundingClientRect();
          const rows={};
          for(const figure of el.querySelectorAll('.op-photo')){
            const link=figure.querySelector('a'), img=link.querySelector('img'), b=link.getBoundingClientRect(), ib=img.getBoundingClientRect();
            (rows[Math.round(b.top)]??=[]).push({left:b.left,right:b.right,w:b.width,h:b.height,iw:ib.width,ih:ib.height,fit:getComputedStyle(img).objectFit});
          }
          return {left:rect.left,right:rect.right,rows:Object.values(rows)};
        });
        const max=width<=480?2:width<=672?Math.min(columns,3):columns;
        const expectedWidth=(width-(max-1)*12)/max;
        assert.equal(layout.rows.length,Math.ceil(count/max));
        for(const row of layout.rows){
          assert.ok(Math.abs(row[0].left-layout.left-(layout.right-row.at(-1).right))<1,'Row not horizontally centered');
          for(const b of row){
            assert.ok(Math.abs(b.w-expectedWidth)<1&&Math.abs(b.h-b.w*.75)<1,'Incorrect uniform frame');
            assert.ok(Math.abs(b.iw-b.w)<1&&Math.abs(b.ih-b.h)<1,'Image does not fill frame');
            assert.equal(b.fit,'cover');
          }
        }
      }
    }
  }
  console.log('Gallery layout: 45 desktop/tablet/mobile combinations, equal 4:3 thumbnails and centered partial rows passed.');
} finally {await browser.close();}
