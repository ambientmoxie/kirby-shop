<?php $error = kirby()->session()->pull('newsletter_error') ?>

<section class="newsletter">

    <h2 class="newsletter__title">Sign up to our newsletter</h2>

    <div class="newsletter__content">

        <p class="newsletter__text">Lorem ipsum dolor sit amet consectetur adipisicing elit.</p>

        <form class="newsletter__form" method="post">
            <input type="hidden" name="_form" value="newsletter">

            <?php // Honeypot: hidden from people, bots fill it and site.php drops the request ?>
            <input type="text" name="website" class="newsletter__honeypot" tabindex="-1" autocomplete="off" aria-hidden="true">

            <input
                type="email"
                name="email"
                class="newsletter__input"
                placeholder="your-email@email.com"
                aria-label="Email address"
                autocomplete="email"
                required>

            <button type="submit" class="newsletter__submit">Submit</button>
        </form>

        <?php if ($error): ?>
            <p class="newsletter__error" role="alert"><?= esc($error) ?></p>
        <?php endif ?>

        <p class="newsletter__legal">By submitting, you agree to the Terms and Conditions and Privacy Policy.</p>

    </div>

</section>