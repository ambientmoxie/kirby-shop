<?php
$newsletterError = kirby()->session()->get('newsletter_error');
kirby()->session()->remove('newsletter_error');
?>

<section class="newsletter">
    <h4 class="newsletter__surtitle">Join our mailing list for 15% off your next order</h4>
    <h2 class="newsletter__catchphrase">Release notes. New features. No spam, ever.</h2>

    <form class="newsletter__form" method="post">
        <input type="hidden" name="_form" value="newsletter">
        <input type="text" name="website" class="newsletter__honeypot" tabindex="-1" autocomplete="off">
        <input
            type="email"
            name="email"
            class="newsletter__input"
            placeholder="your-email@email.com"
            required>
        <button type="submit" class="newsletter__submit">Submit</button>
    </form>

    <?php if ($newsletterError): ?>
        <p class="newsletter__error"><?= esc($newsletterError) ?></p>
    <?php endif ?>

</section>