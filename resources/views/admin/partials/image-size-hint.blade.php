@php
    $slot = $slot ?? 'breadcrumb';
    $size = \App\Support\ImageSlots::get($slot);
    $label = \App\Support\ImageSlots::sizeLabel($slot);
@endphp
<div class="alf-image-hint">
    <div class="alf-image-hint__title">
        <i class="fa-solid fa-ruler-combined" aria-hidden="true"></i>
        Recommended size: <strong>{{ $label }}</strong>
        <span class="text-muted"> ({{ $size['label'] }})</span>
    </div>
    <div class="alf-image-hint__body">
        Any size is allowed. On the public site, larger images are cropped to this frame;
        smaller images are stretched to fill it so the layout stays consistent.
    </div>
</div>
