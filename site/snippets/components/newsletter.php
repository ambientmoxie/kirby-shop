<?php $error = kirby()->session()->pull('newsletter_error') ?>

<section class="newsletter">

    <form class="newsletter__form" method="post">
        <input type="hidden" name="_form" value="newsletter">

        <?php // Honeypot: hidden from people, bots fill it and site.php drops the request ?>
        <input type="text" name="website" class="newsletter__honeypot" tabindex="-1" autocomplete="off" aria-hidden="true">

        <label class="newsletter__label" for="newsletter-email">Newsletter</label>

        <input
            type="email"
            id="newsletter-email"
            name="email"
            class="newsletter__input"
            placeholder="your-email@email.com"
            autocomplete="email"
            required>

        <button type="submit" class="newsletter__submit">OK</button>
    </form>

    <?php if ($error): ?>
        <p class="newsletter__error" role="alert"><?= esc($error) ?></p>
    <?php endif ?>

</section>
