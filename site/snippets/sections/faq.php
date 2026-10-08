<?php
// Placeholder content until the FAQ is editable in the panel
$faq = [
    [
        'question' => 'Lorem ipsum dolor sit amet?',
        'answer'   => 'Consectetur adipiscing elit. Nam auctor gravida aliquam. Nullam volutpat nulla massa, id vehicula lectus elementum eget. Cras elementum, magna vel ultrices facilisis, sapien nibh tempor lorem, at dictum ligula purus in ante.',
    ],
    [
        'question' => 'Sed ut perspiciatis unde omnis iste natus?',
        'answer'   => 'Error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo.',
    ],
    [
        'question' => 'Ut enim ad minima veniam?',
        'answer'   => 'Quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur. Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse.',
    ],
    [
        'question' => 'Nemo enim ipsam voluptatem quia voluptas?',
        'answer'   => 'Sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt.',
    ]
];
?>
<section class="faq">

    <h2 class="faq__title">FAQ</h2>

    <ul class="faq__dropdown dropdown">
        <?php foreach ($faq as $item): ?>
            <li class="dropdown-item">

                <div class="dropdown-item__head">
                    <h3 class="dropdown-item__title"><?= $item['question'] ?></h3>
                    <button type="button" class="dropdown-item__toggle" aria-label="Toggle answer">
                        <?= asset('assets/images/caret.svg')->read() ?>
                    </button>
                </div>

                <div class="dropdown-item__body">
                    <div class="dropdown-item__body-inner">
                        <p><?= $item['answer'] ?></p>
                    </div>
                </div>

            </li>
        <?php endforeach ?>
    </ul>

</section>