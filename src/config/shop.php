<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Store name
    |--------------------------------------------------------------------------
    |
    | The brand as customers see it. APP_NAME stays a technical identifier
    | (cookie and cache prefixes) and is spelled without the space.
    |
    */

    'name' => env('SHOP_NAME', 'Coffee Project'),

    /*
    |--------------------------------------------------------------------------
    | Store contacts
    |--------------------------------------------------------------------------
    |
    | Shown in the storefront footer. Taken over from the old coffeeproject.ru.
    |
    */

    'phone' => env('SHOP_PHONE', '+7 909 701-90-07'),

    'email' => env('SHOP_EMAIL', 'shop@coffeeproject.ru'),

    'vk' => env('SHOP_VK', 'https://vk.com/coffee_project'),

    // Where messages from the contact form go. Replies go straight to the sender.
    'contact_email' => env('SHOP_CONTACT_EMAIL', 'coffeeproject.ekb@gmail.com'),

    /*
    |--------------------------------------------------------------------------
    | Company details
    |--------------------------------------------------------------------------
    |
    | The seller and personal data operator named in the footer, the terms of
    | use and the privacy policy. From the old site's "Реквизиты" page.
    |
    */

    'legal_name' => env('SHOP_LEGAL_NAME', 'ООО «Кофе Проджект Ростерс»'),

    'legal_full_name' => env('SHOP_LEGAL_FULL_NAME', 'Общество с ограниченной ответственностью «Кофе Проджект Ростерс»'),

    'ogrn' => env('SHOP_OGRN', '1176658015093'),

    'inn' => env('SHOP_INN', '6671067389'),

    'kpp' => env('SHOP_KPP', '667101001'),

    'legal_address' => env('SHOP_LEGAL_ADDRESS', '620014, г. Екатеринбург, ул. Вайнера, д. 36, офис 28'),

    /*
    |--------------------------------------------------------------------------
    | Checkout
    |--------------------------------------------------------------------------
    |
    | Payment methods offered at checkout, and the city the courier serves.
    | Courier prices are set in the admin (Delivery), not here.
    |
    */

    'payments' => [
        'cash' => (bool) env('CHECKOUT_CASH_ENABLED', false),
        // Also needs the T-Bank terminal of the current TBANK_MODE (config/services.php).
        'tbank' => (bool) env('CHECKOUT_TBANK_ENABLED', false),
    ],

    'courier_city' => 'Екатеринбург',

];
