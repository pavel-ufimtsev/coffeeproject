<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Root categories
    |--------------------------------------------------------------------------
    |
    | Slugs of the root categories. They are URL prefixes (/coffee/...) taken
    | over from the old site, so changing them breaks indexed links.
    |
    */

    'roots' => ['coffee', 'chaj', 'accessory'],

    /*
    |--------------------------------------------------------------------------
    | Landing pages
    |--------------------------------------------------------------------------
    |
    | Category, filter and collection pages with fewer active products than
    | this are kept out of search engines (noindex) and the sitemap.
    |
    */

    'min_indexable_products' => 3,

];
