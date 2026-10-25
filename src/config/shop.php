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

    'legal_name' => env('SHOP_LEGAL_NAME', 'ООО «Кофе Проджект Роастерс»'),

];
