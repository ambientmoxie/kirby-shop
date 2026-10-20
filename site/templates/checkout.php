<?php snippet('layout/head') ?>
<?php
$payment = isStripeEnabled()
    ? 'You will be redirected to Stripe for payment.'
    : 'The vendor will contact you soon to finalize payment.';

// kstore.js keeps [data-cart-subtotal] in sync when the cart changes
snippet('layout/header', [
    'intro' => 'You are about to place an order of <span data-cart-subtotal>' . formatPrice(cartSubtotal()) . '</span>. ' . $payment,
]);
?>

<div class="checkout">

    <form class="checkout__form" action="<?= $page->url() ?>" method="POST">

        <input type="text" name="website" class="checkout__honeypot" tabindex="-1" autocomplete="off" aria-hidden="true">
        <input type="hidden" name="form_start" id="form-start">

        <div class="checkout__row">
            <input class="checkout__input" type="text" id="name" name="name" placeholder="First name" aria-label="First name" required autocomplete="given-name" value="<?= esc($values['name'] ?? '') ?>">
            <input class="checkout__input" type="text" id="surname" name="surname" placeholder="Last name" aria-label="Last name" required autocomplete="family-name" value="<?= esc($values['surname'] ?? '') ?>">
        </div>

        <input class="checkout__input" type="email" id="email" name="email" placeholder="Email" aria-label="Email" required autocomplete="email" value="<?= esc($values['email'] ?? '') ?>">

        <input class="checkout__input" type="text" id="address" name="address" placeholder="Address" aria-label="Address" required autocomplete="street-address" value="<?= esc($values['address'] ?? '') ?>">

        <div class="checkout__row checkout__row--3">
            <input class="checkout__input" type="text" id="zipcode" name="zipcode" placeholder="Zip code" aria-label="Zip code" required autocomplete="postal-code" value="<?= esc($values['zipcode'] ?? '') ?>">
            <input class="checkout__input" type="text" id="city" name="city" placeholder="City" aria-label="City" required autocomplete="address-level2" value="<?= esc($values['city'] ?? '') ?>">
            <input class="checkout__input" type="text" id="country" name="country" placeholder="Country" aria-label="Country" required autocomplete="country-name" value="<?= esc($values['country'] ?? '') ?>">
        </div>

        <textarea class="checkout__input checkout__input--textarea" id="message" name="message" rows="3" placeholder="Additional information" aria-label="Additional information"><?= esc($values['message'] ?? '') ?></textarea>

        <?php if ($error): ?>
            <p class="checkout__error" role="alert"><?= esc($error) ?></p>
        <?php endif ?>

        <button class="checkout__submit" type="submit">Place order</button>

    </form>

</div>

<?php snippet('layout/footer') ?>
<?php snippet('layout/foot') ?>