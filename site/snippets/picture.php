<?php
if (!$image) return;
$sizes = $sizes ?? '(max-width: 460px) 50vw, (max-width: 768px) 33vw, 20vw';
$class = $class ?? null;
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
        alt="<?= esc($image->alt()->or($image->filename())) ?>"
        width="<?= $image->width() ?>"
        height="<?= $image->height() ?>"
        draggable="false">
</picture>
