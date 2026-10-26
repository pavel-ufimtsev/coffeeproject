<x-help-page :title="__('Help')" description="Помощь покупателям Coffee Project: доставка и оплата, возврат и обмен, ответы на частые вопросы." :lead="__('Everything about ordering, delivery and returns. If you do not find the answer, write to us.')">
    <ul class="help-cards">
        <li>
            <a class="help-card" href="{{ route('help.delivery') }}">
                <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6.5h11v9H3zM14 9.5h3.5l3 3v3H14"/><circle cx="7" cy="17.5" r="1.75"/><circle cx="17" cy="17.5" r="1.75"/></svg>
                <strong>Доставка и оплата</strong>
                <span>Курьер по Екатеринбургу, пункты выдачи СДЭК по всей России, сроки и цены по городам.</span>
            </a>
        </li>
        <li>
            <a class="help-card" href="{{ route('help.returns') }}">
                <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12a8 8 0 1 0 2.4-5.7L4 8.5"/><path d="M4 4v4.5h4.5"/></svg>
                <strong>Возврат и обмен</strong>
                <span>Кофе не подошёл? В течение 30 дней заменим его или вернём деньги.</span>
            </a>
        </li>
        <li>
            <a class="help-card" href="{{ route('help.faq') }}">
                <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9.5"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6v.6M12 17h.01"/></svg>
                <strong>Вопросы и ответы</strong>
                <span>Оплата, помол, хранение кофе, статус заказа и другие частые вопросы.</span>
            </a>
        </li>
        <li>
            <a class="help-card" href="{{ route('wholesale') }}">
                <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="7" width="17" height="13" rx="2"/><path d="M8.5 7V5a1.5 1.5 0 0 1 1.5-1.5h4A1.5 1.5 0 0 1 15.5 5v2M3.5 12.5h17"/></svg>
                <strong>Для бизнеса</strong>
                <span>Кофе для кофеен, ресторанов и офисов: оптовые цены и регулярные поставки.</span>
            </a>
        </li>
    </ul>
</x-help-page>
