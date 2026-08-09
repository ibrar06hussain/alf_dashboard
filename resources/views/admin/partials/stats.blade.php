@php
    /** @var array<int, array{label:string,value:int|string,icon?:string,tone?:string}> $stats */
    $stats = $stats ?? [];
@endphp
@if(count($stats))
<div class="alf-stats-row mb-4">
    <div class="row g-3">
        @foreach($stats as $stat)
            @php
                $tone = $stat['tone'] ?? 'green';
                $icon = $stat['icon'] ?? 'fa-solid fa-chart-simple';
            @endphp
            <div class="col-6 col-md-3">
                <div class="alf-stat-card alf-stat-card--{{ $tone }}">
                    <div class="alf-stat-card__icon"><i class="{{ $icon }}" aria-hidden="true"></i></div>
                    <div class="alf-stat-card__body">
                        <div class="alf-stat-card__value">{{ is_numeric($stat['value']) ? number_format((float) $stat['value']) : $stat['value'] }}</div>
                        <div class="alf-stat-card__label">{{ $stat['label'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endif
