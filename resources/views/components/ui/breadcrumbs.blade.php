@props([
    'items' => [],
    'showHome' => true,
    'homeUrl' => route('home'),
    'homeLabel' => 'Home',
    'separator' => '/',
])

@php
    $normalizedItems = [];
    foreach ($items as $key => $value) {
        if (is_array($value)) {
            $normalizedItems[] = [
                'label' => $value['label'] ?? ($value['name'] ?? ''),
                'url' => $value['url'] ?? ($value['href'] ?? null),
            ];
        } elseif (is_string($key)) {
            $normalizedItems[] = [
                'label' => $key,
                'url' => $value,
            ];
        } else {
            $normalizedItems[] = [
                'label' => (string) $value,
                'url' => null,
            ];
        }
    }
    // Filter out any empty or null segments
    $normalizedItems = array_values(array_filter($normalizedItems, fn($i) => !empty($i['label'])));
@endphp

<nav {{ $attributes->merge(['class' => 'flex items-center gap-2 text-xs text-text-muted select-none font-medium']) }} aria-label="Breadcrumb">
    @if($showHome)
        <a href="{{ $homeUrl }}" class="hover:text-primary transition-colors cursor-pointer">
            {{ $homeLabel }}
        </a>
    @endif

    @foreach($normalizedItems as $index => $item)
        @if($showHome || $index > 0)
            <span class="text-border-subtle select-none">{{ $separator }}</span>
        @endif

        @if(!empty($item['url']) && !$loop->last)
            <a href="{{ $item['url'] }}" class="hover:text-primary transition-colors cursor-pointer">
                {{ $item['label'] }}
            </a>
        @else
            <span class="text-text-main font-semibold truncate">
                {{ $item['label'] }}
            </span>
        @endif
    @endforeach

    {{ $slot }}
</nav>