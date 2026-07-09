<?php snippet('header') ?>

<?php
$payment = isStripeEnabled()
    ? 'You will be redirected to Stripe for payment.'
    : 'The vendor will contact you soon to finalize payment.';
?>

<div class="checkout">

    <h2 class="checkout__title">You are about to place an order of <span data-cart-subtotal><?= formatEUR(cartSubtotal()) ?></span> €. <?= $payment ?></h2>

    <form class="checkout__form" action="<?= $page->url() ?>" method="POST">

        <input type="text" name="website" class="checkout__honeypot" tabindex="-1" autocomplete="off">
        <input type="hidden" name="form_start" id="form-start">

        <div class="checkout__row">
            <div class="checkout__field">
                <input class="checkout__input" type="text" id="name" name="name" placeholder="First name" aria-label="First name" required autocomplete="given-name" value="<?= esc($values['name'] ?? '') ?>">
            </div>
            <div class="checkout__field">
                <input class="checkout__input" type="text" id="surname" name="surname" placeholder="Last name" aria-label="Last name" required autocomplete="family-name" value="<?= esc($values['surname'] ?? '') ?>">
            </div>
        </div>

        <div class="checkout__field">
            <input class="checkout__input" type="email" id="email" name="email" placeholder="Email" aria-label="Email" required autocomplete="email" value="<?= esc($values['email'] ?? '') ?>">
        </div>

        <div class="checkout__field">
            <input class="checkout__input" type="text" id="address" name="address" placeholder="Address" aria-label="Address" required autocomplete="street-address" value="<?= esc($values['address'] ?? '') ?>">
        </div>

        <div class="checkout__row checkout__row--3">
            <div class="checkout__field">
                <input class="checkout__input" type="text" id="zipcode" name="zipcode" placeholder="Zip code" aria-label="Zip code" required autocomplete="postal-code" value="<?= esc($values['zipcode'] ?? '') ?>">
            </div>
            <div class="checkout__field">
                <input class="checkout__input" type="text" id="city" name="city" placeholder="City" aria-label="City" required autocomplete="address-level2" value="<?= esc($values['city'] ?? '') ?>">
            </div>
            <div class="checkout__field">
                <input class="checkout__input" type="text" id="country" name="country" placeholder="Country" aria-label="Country" required autocomplete="country-name" value="<?= esc($values['country'] ?? '') ?>">
            </div>
        </div>

        <div class="checkout__field">
            <textarea class="checkout__input checkout__input--textarea" id="message" name="message" rows="3" placeholder="Additional information" aria-label="Additional information"><?= esc($values['message'] ?? '') ?></textarea>
        </div>

        <?php if ($error): ?>
            <p class="checkout__error"><?= esc($error) ?></p>
        <?php endif ?>

        <button class="checkout__submit" type="submit">Place order</button>

    </form>

</div>

<?php snippet('footer') ?>
