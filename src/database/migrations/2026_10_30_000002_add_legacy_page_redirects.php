<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Old site addresses (from the web archive) that had no new page yet, sent
 * to their closest new page. Kept in the redirects table so they can be
 * changed in the admin, e.g. when the blog's brewing guides arrive.
 */
return new class extends Migration
{
    private const REDIRECTS = [
        '/bonuses' => '/coffee',
        '/brew' => '/coffee',
        '/brew/aero' => '/coffee/aeropress',
        '/brew/cezve' => '/coffee/cezve',
        '/brew/chemex' => '/coffee/chemex',
        '/brew/christmas_raf' => '/coffee',
        '/brew/drip' => '/coffee/drip',
        '/brew/eshop' => '/coffee',
        '/brew/french' => '/coffee/french',
        '/brew/percolator' => '/coffee/geyzer',
        '/brew/siphon' => '/coffee',
        '/brew/v60' => '/coffee/pourover',
        '/brew/vietnam' => '/coffee',
        '/cart.php' => '/cart',
        '/cart/checkpromo.php' => '/cart',
        '/cart/delivery.php' => '/cart',
        '/cart/getlocation.php' => '/cart',
        '/cart/placeorder.php' => '/cart',
        '/cart2' => '/cart',
        '/certificates' => '/about',
        '/cheeese' => '/coffee',
        '/coffee-for-office' => '/wholesale',
        '/coffeeshops' => '/about',
        '/coffeeshops/cpexpo' => '/about',
        '/coffeeshops/cprad' => '/about',
        '/free' => '/coffee',
        '/free_sample' => '/coffee',
        '/friend' => '/coffee',
        '/greenproducers' => '/coffee',
        '/help/delivery/russia' => '/help/delivery',
        '/help/discount' => '/help',
        '/more/delivery' => '/help/delivery',
        '/more/payment' => '/help/delivery',
        '/more/requisites' => '/about',
        '/more/school' => '/about',
        '/more/school/inquiry.php' => '/about',
        '/more/wholesale' => '/wholesale',
        '/more/wholesale-brl' => '/wholesale',
        '/more/wholesale-chb' => '/wholesale',
        '/more/wholesale-ekb' => '/wholesale',
        '/more/wholesale-irk' => '/wholesale',
        '/more/wholesale-izh' => '/wholesale',
        '/more/wholesale-kmr' => '/wholesale',
        '/more/wholesale-krd' => '/wholesale',
        '/more/wholesale-krs' => '/wholesale',
        '/more/wholesale-kzn' => '/wholesale',
        '/more/wholesale-msk' => '/wholesale',
        '/more/wholesale-nng' => '/wholesale',
        '/more/wholesale-nvs' => '/wholesale',
        '/more/wholesale-omk' => '/wholesale',
        '/more/wholesale-prm' => '/wholesale',
        '/more/wholesale-rnd' => '/wholesale',
        '/more/wholesale-ryz' => '/wholesale',
        '/more/wholesale-smr' => '/wholesale',
        '/more/wholesale-spb' => '/wholesale',
        '/more/wholesale-srt' => '/wholesale',
        '/more/wholesale-tgl' => '/wholesale',
        '/more/wholesale-tmn' => '/wholesale',
        '/more/wholesale-ufa' => '/wholesale',
        '/more/wholesale-vld' => '/wholesale',
        '/more/wholesale-vlg' => '/wholesale',
        '/more/wholesale-vrz' => '/wholesale',
        '/new' => '/about',
        '/new/school' => '/about',
        '/new/school/aero' => '/about',
        '/partner' => '/coffee',
        '/press/66ru_1' => '/about',
        '/press/ND_foto' => '/about',
        '/press/brodude' => '/about',
        '/press/channel4_1' => '/about',
        '/press/channel4_2' => '/about',
        '/press/dk_ru_1' => '/about',
        '/press/dk_ru_2' => '/about',
        '/press/itsmycity_1' => '/about',
        '/press/kpru_1' => '/about',
        '/press/momenty_barista' => '/about',
        '/press/newdaynewsru_1' => '/about',
        '/press/ngzt_1' => '/about',
        '/press/otvutro_1' => '/about',
        '/press/resto_1' => '/about',
        '/press/resto_2' => '/about',
        '/press/the_vilage_1' => '/about',
        '/press/uralstudentru_1' => '/about',
        '/reg' => '/register',
        '/roasters' => '/coffee',
        '/sitemap' => '/sitemap.xml',
        '/sitemap_all' => '/sitemap.xml',
        '/store' => '/coffee',
        '/store/SpecCol' => '/coffee',
        '/store/aeropressatelier' => '/coffee',
        '/store/apron' => '/coffee',
        '/store/chemexatelier' => '/coffee',
        '/store/coffeetour-l' => '/coffee',
        '/store/coffeetour-m' => '/coffee',
        '/store/coffeetour-s' => '/coffee',
        '/store/coffeetour-xl' => '/coffee',
        '/store/columbia' => '/coffee',
        '/store/costarica' => '/coffee',
        '/store/ethiopia' => '/coffee',
        '/store/finecup' => '/coffee',
        '/store/gift' => '/coffee',
        '/store/honduras' => '/coffee',
        '/store/honey3' => '/coffee',
        '/store/kamuhuma' => '/coffee',
        '/store/koke' => '/coffee',
        '/store/mexica' => '/coffee',
        '/store/milk' => '/coffee',
        '/store/mogiana' => '/coffee',
        '/store/nicaragua' => '/coffee',
        '/store/pouroveratelier' => '/coffee',
        '/store/salvador' => '/coffee',
        '/store/sidamo' => '/coffee',
        '/store/sweet' => '/coffee',
        '/store/syphonatelier' => '/coffee',
        '/store/tanzania' => '/coffee',
        '/store/valentineatelier' => '/coffee',
        '/store/yunnan' => '/coffee',
        '/story' => '/about',
        '/study' => '/coffee',
        '/study/aeropress' => '/coffee/aeropress',
        '/study/cezve' => '/coffee/cezve',
        '/study/chemex' => '/coffee/chemex',
        '/study/drip' => '/coffee/drip',
        '/study/frenchpress' => '/coffee/french',
        '/study/percolator' => '/coffee/geyzer',
        '/study/siphon' => '/coffee',
        '/study/v60' => '/coffee/pourover',
        '/study/vietnam' => '/coffee',
        '/user/forgot-password' => '/forgot-password',
        '/user/login' => '/login',
    ];

    public function up(): void
    {
        $now = now();

        DB::table('redirects')->insertOrIgnore(collect(self::REDIRECTS)->map(fn (string $to, string $from) => [
            'from_path' => $from,
            'to_path' => $to,
            'status_code' => 301,
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all());

        Cache::forget('redirects.map');
    }

    public function down(): void
    {
        foreach (self::REDIRECTS as $from => $to) {
            DB::table('redirects')->where('from_path', $from)->where('to_path', $to)->where('hits', 0)->delete();
        }

        Cache::forget('redirects.map');
    }
};
