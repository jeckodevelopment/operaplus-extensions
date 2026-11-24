<?php
defined('_JEXEC') or die;
$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$icon = static function (string $name): string {
    return match ($name) {
        'envelope' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="1"/><path d="m2 5 10 8L22 5"/></svg>',
        'book' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" aria-hidden="true"><path d="M12 5v16M2 3c5-1 8 0 10 2 2-2 5-3 10-2v16c-5-1-8 0-10 2-2-2-5-3-10-2z"/></svg>',
        default => '',
    };
};
?>
<div class="op-cta op-cta--<?php echo $e($config['variant']); ?>">
    <?php if ($config['watermark'] && $config['icon'] !== 'none') : ?><span class="op-cta__watermark" aria-hidden="true"><?php echo $icon($config['icon']); ?></span><?php endif; ?>
    <div class="op-cta__body">
        <?php if ($config['icon'] !== 'none') : ?><span class="op-cta__icon"><?php echo $icon($config['icon']); ?></span><?php endif; ?>
        <?php if ($config['heading'] !== '') : ?><h2 class="op-cta__heading"><?php echo $e($config['heading']); ?></h2><?php endif; ?>
        <?php if ($config['description'] !== '') : ?><p class="op-cta__text"><?php echo nl2br($e($config['description'])); ?></p><?php endif; ?>
    </div>
    <?php if ($config['image'] !== '') : ?><img class="op-cta__image" loading="lazy" decoding="async" src="<?php echo $e($config['image']); ?>" alt="<?php echo $e($config['image_alt']); ?>"><?php endif; ?>
    <?php if ($config['button_text'] !== '' && $config['button_url'] !== '') : ?><a class="op-cta__button" href="<?php echo $e($config['button_url']); ?>"><?php echo $e($config['button_text']); ?></a><?php endif; ?>
</div>
