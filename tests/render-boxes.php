<?php
declare(strict_types=1);
namespace Joomla\CMS\Language {
    final class Text {
        public static function _(string $key): string {
            return ['MOD_OPERAPLUS_ATLAS_DISCOVER'=>'Scopri su Atlas','MOD_OPERAPLUS_ATLAS_EMPTY'=>'Nessuna entità associata.','MOD_OPERAPLUS_ATLAS_IMAGE_CREDITS'=>'Crediti immagine','MOD_OPERAPLUS_ATLAS_IMAGE_SOURCE'=>'Fonte'][$key] ?? $key;
        }
    }
}
namespace {
    define('_JEXEC', 1);
    require __DIR__ . '/../mod_operaplus_cta/src/Config.php';
    use OperaPlus\Cta\Config;
    $portrait = ['url'=>'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20100%20120%22%3E%3Ccircle%20cx%3D%2250%22%20cy%3D%2236%22%20r%3D%2223%22%20fill%3D%22%23816a5c%22%2F%3E%3Cpath%20d%3D%22M10%20120V85Q50%2045%2090%2085V120Z%22%20fill%3D%22%23816a5c%22%2F%3E%3C%2Fsvg%3E','alt'=>'Segnaposto di test','source_url'=>'https://commons.wikimedia.org/','license_url'=>'https://creativecommons.org/licenses/by/4.0/','author'=>'Autore fixture','credit'=>'Fixture sintetica','license'=>'CC-BY-4.0'];
    $entity = ['title'=>'Giuseppe Verdi','subtitle'=>'Compositore italiano','url'=>'https://opera.plus/atlas/opere/la-traviata/','image'=>$portrait];
    $base = ['heading'=>'Resta aggiornato con Opera+','description'=>'Notizie, recensioni e approfondimenti dal mondo dell’opera lirica.','button_text'=>'Iscriviti alla newsletter','button_url'=>'/newsletter','icon'=>'envelope','watermark'=>1];
    ?><!doctype html><html lang="it"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Editorial boxes — visual test</title><style>
    *{box-sizing:border-box}body{margin:0;padding:16px;background:#fff;font:16px system-ui;color:#222}body.dark{background:#171316;color:#f3ece1}.fixtures{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr));gap:24px;max-width:1080px;margin:auto}.fixture{min-width:0}.wide{grid-column:1/-1}.narrow{width:240px;max-width:100%}
    <?php readfile(__DIR__ . '/../mod_operaplus_cta/media/css/cta.css'); readfile(__DIR__ . '/../mod_operaplus_atlas/media/css/atlas.css'); ?>
    /* Regression: host sidebar typography and link-color overrides reported in live testing. */
    body.operaplus.theme-light .op-sidebar a:not(.op-button):not(.btn){color:#632b3a!important}body.operaplus.theme-light .op-sidebar :is(h2,h3,p,span){color:#30232a}body.operaplus .op-sidebar h2{font-size:60px}body.operaplus .op-sidebar a{font-size:24px}
    </style></head><body class="operaplus theme-light"><main class="fixtures op-sidebar">
    <?php foreach (['light','dark','horizontal','atlas'] as $variant) : ?>
      <div class="fixture <?php echo $variant === 'horizontal' ? 'wide' : ''; ?>" data-case="cta-<?php echo $variant; ?>">
        <?php $config = Config::from(array_merge($base,['variant'=>$variant]));
        if ($variant === 'atlas') { $config['heading']='Esplora Opera+ Atlas'; $config['image']=$portrait['url']; $config['image_alt']=$portrait['alt']; $config['button_text']='Scopri su Atlas'; $config['icon']='none'; }
        require __DIR__ . '/../mod_operaplus_cta/tmpl/default.php'; ?>
      </div>
    <?php endforeach; ?>
    <?php foreach (['portrait','no-image','no-link','narrow','empty','public-domain','cc0','share-alike','fallback'] as $case) : ?>
      <div class="fixture <?php echo $case === 'narrow' ? 'narrow' : ''; ?>" data-case="atlas-<?php echo $case; ?>">
        <?php $entities = $case === 'empty' ? [] : [array_replace($entity, $case === 'no-image' ? ['image'=>null] : ($case === 'no-link' ? ['url'=>null] : []))]; if ($entities && in_array($case, ['public-domain','cc0','share-alike'], true)) {
            $entities[0]['image']['license'] = match ($case) {'public-domain'=>'Public Domain','cc0'=>'CC0-1.0',default=>'CC-BY-SA-4.0'};
            $entities[0]['image']['license_url'] = match ($case) {'public-domain'=>'https://creativecommons.org/publicdomain/mark/1.0/','cc0'=>'https://creativecommons.org/publicdomain/zero/1.0/',default=>'https://creativecommons.org/licenses/by-sa/4.0/'};
        }
        if ($case === 'fallback') { $entities[0]['fallback_image']=$portrait; $entities[0]['image']=null; }
        require __DIR__ . '/../mod_operaplus_atlas/tmpl/default.php'; ?>
      </div>
    <?php endforeach; ?>
    </main></body></html><?php
}
