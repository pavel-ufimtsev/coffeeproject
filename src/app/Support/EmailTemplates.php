<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * The texts of customer emails, edited in the admin (Emails). Each template
 * has a subject, a heading and the text above and below the fixed part
 * (order lines, buttons). Texts may use {placeholders} and light Markdown:
 * **bold**, [links](https://...), blank lines between paragraphs.
 */
class EmailTemplates
{
    public const FIELDS = ['subject', 'heading', 'intro', 'outro'];

    /**
     * Templates in admin order, with the placeholders each understands.
     *
     * @return array<string, array{title: string, description: string, placeholders: list<string>}>
     */
    public static function all(): array
    {
        return [
            'welcome' => [
                'title' => __('Welcome email'),
                'description' => __('Sent once a customer creates an account.'),
                'placeholders' => ['name', 'shop'],
            ],
            'order_placed' => [
                'title' => __('Order confirmation'),
                'description' => __('Sent when an order is placed: cash orders at once, card orders after payment.'),
                'placeholders' => ['name', 'number', 'total', 'shop'],
            ],
        ];
    }

    /**
     * The saved texts, with defaults filling anything never saved.
     *
     * @return array{subject: string, heading: string, intro: string, outro: string}
     */
    public static function get(string $template): array
    {
        $saved = (array) Setting::get("emails.{$template}", []);

        return array_merge(self::defaults($template), array_filter($saved, fn ($value) => $value !== null));
    }

    /**
     * @param  array{subject: string, heading: string, intro: ?string, outro: ?string}  $texts
     */
    public static function put(string $template, array $texts): void
    {
        Setting::put(["emails.{$template}" => [
            ...$texts,
            'intro' => $texts['intro'] ?? '',
            'outro' => $texts['outro'] ?? '',
        ]]);
    }

    /**
     * The template's texts with placeholders filled in: subject as plain
     * text, the rest as HTML ready for the layout.
     *
     * @param  array<string, string>  $values
     * @return array{subject: string, heading: string, intro: HtmlString, outro: HtmlString, intro_text: string, outro_text: string}
     */
    public static function render(string $template, array $values): array
    {
        $texts = self::get($template);
        $values = ['shop' => config('shop.name'), ...$values];
        $fill = fn (string $text) => preg_replace_callback(
            '/\{(\w+)\}/',
            fn ($match) => array_key_exists($match[1], $values) ? $values[$match[1]] : $match[0],
            $text,
        );

        return [
            'subject' => $fill($texts['subject']),
            'heading' => $fill($texts['heading']),
            'intro' => self::html($fill($texts['intro'])),
            'outro' => self::html($fill($texts['outro'])),
            'intro_text' => $fill($texts['intro']),
            'outro_text' => $fill($texts['outro']),
        ];
    }

    private static function html(string $markdown): HtmlString
    {
        return new HtmlString(Str::markdown($markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'renderer' => ['soft_break' => '<br>'],
        ]));
    }

    /**
     * @return array{subject: string, heading: string, intro: string, outro: string}
     */
    public static function defaults(string $template): array
    {
        return match ($template) {
            'welcome' => [
                'subject' => 'Добро пожаловать в {shop}',
                'heading' => 'Здравствуйте, {name}!',
                'intro' => "Спасибо, что зарегистрировались в {shop}. Мы обжариваем кофе в Екатеринбурге под заказ и отправляем его по всей России.\n\nВ личном кабинете видны все ваши заказы, а сохранённые адреса ускоряют оформление следующих.",
                'outro' => 'Если появятся вопросы о кофе, заваривании или доставке, просто ответьте на это письмо.',
            ],
            'order_placed' => [
                'subject' => 'Заказ {number} принят',
                'heading' => 'Спасибо за заказ, {name}!',
                'intro' => 'Мы получили заказ **{number}** и скоро свяжемся с вами, чтобы подтвердить доставку. Кофе обжариваем под заказ, поэтому он приедет к вам свежим.',
                'outro' => 'Вопросы по заказу? Позвоните нам или просто ответьте на это письмо.',
            ],
        };
    }
}
