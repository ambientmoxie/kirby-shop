<section class="faq">

    <h2 class="faq__title">FAQ</h2>

    <ul class="faq__dropdown dropdown">
        <?php for ($i = 1; $i <= 7; $i++): ?>
            <li class="dropdown-item">

                <div class="dropdown-item__head">
                    <h3 class="dropdown-item__title">Lorem ipsum dolor sit amet?</h3>
                    <button type="button" class="dropdown-item__toggle" aria-label="Toggle answer">
                        <?= asset('assets/images/caret.svg')->read() ?>
                    </button>
                </div>

                <div class="dropdown-item__body">
                    <div class="dropdown-item__body-inner">
                        <p>Consectetur adipiscing elit. Nam auctor gravida aliquam. Nullam volutpat nulla massa, id vehicula lectus elementum eget. Cras elementum, magna vel ultrices facilisis, sapien nibh tempor lorem, at dictum ligula purus in ante.</p>
                    </div>
                </div>

            </li>
        <?php endfor ?>
    </ul>

</section>
