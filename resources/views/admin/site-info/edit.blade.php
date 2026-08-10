@extends('layouts.admin')

@section('title', 'Site Information')

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'Site Information',
    'subtitle' => 'Edit welcome message, contact details, footer text, about copy and branding — English and Urdu.',
    'icon' => 'fa-solid fa-address-card',
])

@include('admin.partials.stats', ['stats' => $pageStats])

<form method="POST" action="{{ route('admin.site-info.update') }}">
    @csrf @method('PUT')

    @foreach($definitions as $section => $fields)
        <div class="card alf-panel mb-4">
            <div class="card-header">{{ $section }}</div>
            <div class="card-body">
                <div class="row g-4">
                    @foreach($fields as $def)
                        @php $row = $settings[$def['key']] ?? null; @endphp
                        <div class="col-12 {{ ($def['type'] ?? 'text') === 'textarea' ? '' : 'col-lg-6' }}">
                            <div class="border rounded-3 p-3 h-100" style="background:#fbfcf8;">
                                <label class="form-label fw-semibold mb-1">{{ $def['label'] }}</label>
                                <p class="small text-muted mb-2">{{ $def['help_text'] }}</p>

                                @if(($def['type'] ?? 'text') === 'textarea')
                                    <textarea name="content_{{ $def['key'] }}" class="form-control mb-2" rows="3"
                                              placeholder="{{ $def['fallback'] }}">{{ old('content_'.$def['key'], $row->value ?? '') }}</textarea>
                                @elseif(($def['type'] ?? 'text') === 'email')
                                    <input type="email" name="content_{{ $def['key'] }}" class="form-control mb-2"
                                           value="{{ old('content_'.$def['key'], $row->value ?? '') }}"
                                           placeholder="{{ $def['fallback'] }}">
                                @else
                                    <input type="text" name="content_{{ $def['key'] }}" class="form-control mb-2"
                                           value="{{ old('content_'.$def['key'], $row->value ?? '') }}"
                                           placeholder="{{ $def['fallback'] }}">
                                @endif

                                @if(!empty($def['translatable']))
                                    <label class="form-label small mb-1">Urdu (اردو)</label>
                                    @if(($def['type'] ?? 'text') === 'textarea')
                                        <textarea name="content_{{ $def['key'] }}_ur" class="form-control" rows="3" dir="rtl" lang="ur"
                                                  placeholder="{{ $def['fallback_ur'] }}">{{ old('content_'.$def['key'].'_ur', $row->value_ur ?? '') }}</textarea>
                                    @else
                                        <input type="text" name="content_{{ $def['key'] }}_ur" class="form-control" dir="rtl" lang="ur"
                                               value="{{ old('content_'.$def['key'].'_ur', $row->value_ur ?? '') }}"
                                               placeholder="{{ $def['fallback_ur'] }}">
                                    @endif
                                @endif

                                <div class="small text-muted mt-2">
                                    Default EN: {{ \Illuminate\Support\Str::limit($def['fallback'] ?: '—', 80) }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach

    <div class="alf-form-actions">
        <button class="btn btn-primary">Save Site Information</button>
        <a href="{{ route('admin.media.edit') }}" class="btn btn-outline-secondary">Site Media</a>
    </div>
</form>
@endsection
