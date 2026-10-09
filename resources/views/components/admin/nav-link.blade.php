<li>
    <a href="{{ $item['url'] }}" @class(['pa-sidebar__link', 'is-active' => $item['active']]) @if ($item['active']) aria-current="page" @endif>
        <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
        <span>{{ $item['label'] }}</span>
        @if (! empty($item['badge']))
            <span class="pa-sidebar__count">{{ $item['badge'] }}<span class="visually-hidden"> {{ $item['badge_label'] ?? '' }}</span></span>
        @endif
    </a>
</li>
