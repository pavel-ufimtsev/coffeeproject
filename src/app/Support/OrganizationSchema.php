<?php

namespace App\Support;

/**
 * schema.org data about the company for the About and Contacts pages:
 * who runs the shop, since when, and how to reach it.
 */
class OrganizationSchema
{
    public const FOUNDED = '2012';

    /**
     * @return array<string, mixed>
     */
    public static function organization(): array
    {
        return [
            '@type' => 'Organization',
            '@id' => url('/#organization'),
            'name' => config('shop.name'),
            'legalName' => config('shop.legal_full_name'),
            'url' => url('/'),
            'logo' => asset('images/shop/logo.png'),
            'image' => asset('images/about/cups.webp'),
            'description' => 'Интернет-магазин свежеобжаренного кофе из Екатеринбурга с доставкой по России.',
            'foundingDate' => self::FOUNDED,
            'foundingLocation' => ['@type' => 'Place', 'name' => 'Екатеринбург'],
            'founder' => ['@type' => 'Person', 'name' => 'Александра Уфимцева', 'jobTitle' => 'Сооснователь, коммерческий директор'],
            'taxID' => config('shop.inn'),
            'identifier' => ['@type' => 'PropertyValue', 'propertyID' => 'ОГРН', 'value' => config('shop.ogrn')],
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => 'ул. Вайнера, д. 36, офис 28',
                'addressLocality' => 'Екатеринбург',
                'postalCode' => '620014',
                'addressCountry' => 'RU',
            ],
            'email' => config('shop.email'),
            'telephone' => preg_replace('/[^\d+]/', '', config('shop.phone')),
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'contactType' => 'customer service',
                'email' => config('shop.email'),
                'telephone' => preg_replace('/[^\d+]/', '', config('shop.phone')),
                'areaServed' => 'RU',
                'availableLanguage' => ['ru', 'en'],
            ],
            'areaServed' => ['@type' => 'Country', 'name' => 'Россия'],
            'sameAs' => [config('shop.vk')],
        ];
    }

    /**
     * A page about the company, e.g. AboutPage or ContactPage.
     *
     * @return array<string, mixed>
     */
    public static function page(string $type, string $name, string $url): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => $type,
            'name' => $name,
            'url' => $url,
            'inLanguage' => 'ru',
            'mainEntity' => self::organization(),
        ];
    }
}
