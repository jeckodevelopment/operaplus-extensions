<?php
defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
$e = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="op-atlas-module">
<?php if (!$entities) : ?>
    <p class="op-atlas-empty"><?php echo Text::_('MOD_OPERAPLUS_ATLAS_EMPTY'); ?></p>
<?php endif; ?>
<?php foreach ($entities as $entity) :
    $image = $entity['image'] ?? $entity['fallback_image'] ?? null; ?>
    <section class="op-atlas-entity<?php echo $image ? ' op-atlas-entity--portrait' : ''; ?>">
        <div class="op-atlas-copy">
            <p class="op-atlas-kicker">Opera+ Atlas</p>
            <h3 class="op-atlas-heading"><?php echo $e($entity['title']); ?></h3>
            <?php if ($entity['subtitle']) : ?><p class="op-atlas-subtitle"><?php echo $e($entity['subtitle']); ?></p><?php endif; ?>
        </div>
        <?php if ($image) : ?>
            <figure class="op-atlas-image"><img loading="lazy" decoding="async" src="<?php echo $e($image['url']); ?>" alt="<?php echo $e($image['alt']); ?>"></figure>
        <?php endif; ?>
        <?php if ($entity['url']) : ?><a class="op-atlas-link" href="<?php echo $e($entity['url']); ?>"><?php echo Text::_('MOD_OPERAPLUS_ATLAS_DISCOVER'); ?></a><?php endif; ?>
        <?php if ($image) : ?><p class="op-atlas-credit"><a href="<?php echo $e($image['source_url']); ?>"><?php echo $e($image['credit']); ?></a> · <a href="<?php echo $e($image['license_url']); ?>"><?php echo $e($image['license']); ?></a></p><?php endif; ?>
    </section>
<?php endforeach; ?>
</div>
