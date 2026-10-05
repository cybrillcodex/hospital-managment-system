@props(['icon' => 'info', 'tone' => 'crimson', 'hint' => null, 'solid' => false])

<div {{ $attributes->merge(['class' => 'flex items-start gap-3']) }}>
    <x-icon-box :tone="$tone" size="sm">
        <x-icon :name="$icon" size="sm" :solid="$solid" />
    </x-icon-box>
    <div class="min-w-0">
        <h2 class="text-base font-semibold leading-tight">{{ $slot }}</h2>
        @if($hint)
            <p class="mt-0.5 text-xs leading-relaxed text-muted">{{ $hint }}</p>
        @endif
    </div>
</div>
