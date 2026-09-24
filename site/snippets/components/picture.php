<?php
// $image: a Kirby file or asset. Optional: $sizes, $class, $alt
if (!$image) return;
$sizes ??= '100vw';
$class ??= null;
// Files uploaded in the panel have an alt field; static assets need an explicit $alt
$alt ??= $image instanceof Kirby\Cms\File ? $image->alt()->value() : '';
?>

<picture class="picture<?= $class ? ' ' . esc($class) : '' ?>">
    <source
        data-srcset="<?= $image->srcset('webp') ?>"
        sizes="<?= esc($sizes) ?>"
        type="image/webp">
    <img
        class="picture__img lazy"
        data-src="<?= $image->url() ?>"
        data-srcset="<?= $image->srcset() ?>"
        sizes="<?= esc($sizes) ?>"
        alt="<?= esc($alt) ?>"
        width="<?= $image->width() ?>"
        height="<?= $image->height() ?>"
        draggable="false">
</picture>
