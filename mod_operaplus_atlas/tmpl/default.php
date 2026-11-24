<?php
defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
$e = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="op-atlas-module">
<?php if (!$entities) : ?>
    <p><?php echo Text::_('MOD_OPERAPLUS_ATLAS_EMPTY'); ?></p>
<?php endif; ?>
<?php foreach ($entities as $entity) :
    $image = $entity['image'] ?? $entity['fallback_image'] ?? null; ?>
    <section class="op-atlas-entity">
        <p class="op-atlas-kicker">Opera+ Atlas</p>
        <?php if ($image) : ?>
            <figure class="op-atlas-image"><img loading="lazy" decoding="async" src="<?php echo $e($image['url']); ?>" alt="<?php echo $e($image['alt']); ?>">
            <figcaption><a href="<?php echo $e($image['source_url']); ?>"><?php echo $e($image['credit']); ?></a> · <a href="<?php echo $e($image['license_url']); ?>"><?php echo $e($image['license']); ?></a></figcaption></figure>
        <?php endif; ?>
        <h3><?php echo $e($entity['title']); ?></h3>
        <?php if ($entity['subtitle']) : ?><p><?php echo $e($entity['subtitle']); ?></p><?php endif; ?>
        <?php if ($entity['url']) : ?><a class="op-atlas-link" href="<?php echo $e($entity['url']); ?>"><?php echo Text::_('MOD_OPERAPLUS_ATLAS_DISCOVER'); ?></a><?php endif; ?>
    </section>
<?php endforeach; ?>
</div>
