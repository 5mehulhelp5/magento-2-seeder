<?php

declare(strict_types=1);

return [
    'type' => 'cms',
    'data' => [
        [
            'cms_type' => 'page',
            'identifier' => 'seed-about-us',
            'title' => 'About Us',
            'content' => '<h1>About Us</h1><p>We are a demo store created by the seeder.</p>',
        ],
        [
            'cms_type' => 'block',
            'identifier' => 'seed-promo-banner',
            'title' => 'Promo Banner',
            'content' => '<div class="promo">Free shipping on orders over $50!</div>',
        ],
    ],
];
