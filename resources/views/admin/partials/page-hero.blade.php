@php
    $title = $title ?? 'Page';
    $subtitle = $subtitle ?? null;
    $icon = $icon ?? 'fa-solid fa-layer-group';
    $actions = array_values(array_filter(is_array($actions ?? null) ? $actions : [], fn ($a) => is_array($a) && ! empty($a['url'])));
@endphp
<div class="alf-page-hero mb-4">
    <div class="alf-page-hero__main">
        <div class="alf-page-hero__icon"><i class="{{ $icon }}" aria-hidden="true"></i></div>
        <div>
            <h1 class="alf-page-hero__title">{{ $title }}</h1>
            @if($subtitle)
                <p class="alf-page-hero__subtitle mb-0">{{ $subtitle }}</p>
            @endif
        </div>
    </div>
    @if(count($actions))
        <div class="alf-page-hero__actions">
            @foreach($actions as $action)
                <a href="{{ $action['url'] }}" class="{{ $action['class'] ?? 'btn btn-primary' }}" @if(! empty($action['target'])) target="{{ $action['target'] }}" rel="noopener" @endif>{{ $action['label'] }}</a>
            @endforeach
        </div>
    @endif
</div>
