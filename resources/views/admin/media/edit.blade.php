@extends('layouts.admin')

@section('title', 'Site Media')

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'Site Media',
    'subtitle' => 'Upload footer, page banners, about image, favicon and other shared visuals. Any image size is accepted — the public site crops or stretches to the recommended frame.',
    'icon' => 'fa-solid fa-photo-film',
])

@include('admin.partials.stats', ['stats' => $pageStats])

<form method="POST" action="{{ route('admin.media.update') }}" enctype="multipart/form-data">
    @csrf @method('PUT')
    <div class="row g-3">
        @foreach(\App\Models\SiteSetting::mediaDefinitions() as $def)
            @php
                $setting = $settings[$def['key']] ?? null;
                $slot = $def['slot'] ?? \App\Support\ImageSlots::forSetting($def['key']);
                $slotMeta = \App\Support\ImageSlots::get($slot);
            @endphp
            <div class="col-md-6 col-xl-4">
                <div class="card alf-panel h-100">
                    <div class="card-header">{{ $def['label'] }}</div>
                    <div class="card-body">
                        <p class="small text-muted">{{ $def['help_text'] }}</p>
                        @include('admin.partials.image-size-hint', ['slot' => $slot])
                        <div class="mb-3 rounded overflow-hidden border alf-admin-preview"
                             style="height:140px;background:#f6f8f2;">
                            <img src="{{ \App\Models\SiteSetting::imageUrl($def['key'], $def['fallback']) }}"
                                 alt="{{ $def['label'] }}"
                                 class="w-100 h-100"
                                 style="object-fit:cover;"
                                 data-alf-fit="img"
                                 data-alf-w="{{ $slotMeta['width'] }}"
                                 data-alf-h="{{ $slotMeta['height'] }}">
                        </div>
                        <div class="mb-2">
                            <input type="file" name="media_{{ $def['key'] }}" class="form-control" accept="image/*,.ico">
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="clear_{{ $def['key'] }}" value="1" id="clear_{{ $def['key'] }}">
                            <label class="form-check-label" for="clear_{{ $def['key'] }}">Reset to default</label>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <div class="alf-form-actions">
        <button class="btn btn-primary">Save Site Media</button>
    </div>
</form>
@endsection
