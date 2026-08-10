@php
    $slide = $slide ?? null;
@endphp
<div class="row">
    <div class="col-lg-8">
        <div class="card alf-panel mb-3">
            <div class="card-header">English content</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Title (English)</label>
                    <input type="text" name="title" class="form-control" value="{{ old('title', $slide->title ?? '') }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Subtitle (English)</label>
                    <textarea name="subtitle" class="form-control" rows="4">{{ old('subtitle', $slide->subtitle ?? '') }}</textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Primary button label (English)</label>
                        <input type="text" name="button_label" class="form-control" value="{{ old('button_label', $slide->button_label ?? '') }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Primary button URL</label>
                        <input type="text" name="button_url" class="form-control" value="{{ old('button_url', $slide->button_url ?? '') }}" placeholder="/schemes">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Secondary button label (English)</label>
                        <input type="text" name="button2_label" class="form-control" value="{{ old('button2_label', $slide->button2_label ?? '') }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Secondary button URL</label>
                        <input type="text" name="button2_url" class="form-control" value="{{ old('button2_url', $slide->button2_url ?? '') }}" placeholder="/#cm-proposed-schemes">
                    </div>
                </div>
            </div>
        </div>

        <div class="card alf-panel mb-3">
            <div class="card-header">Urdu content (اردو)</div>
            <div class="card-body" dir="rtl" lang="ur">
                <div class="mb-3">
                    <label class="form-label">Title (Urdu)</label>
                    <input type="text" name="title_ur" class="form-control" value="{{ old('title_ur', $slide->title_ur ?? '') }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Subtitle (Urdu)</label>
                    <textarea name="subtitle_ur" class="form-control" rows="4">{{ old('subtitle_ur', $slide->subtitle_ur ?? '') }}</textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Primary button (Urdu)</label>
                        <input type="text" name="button_label_ur" class="form-control" value="{{ old('button_label_ur', $slide->button_label_ur ?? '') }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Secondary button (Urdu)</label>
                        <input type="text" name="button2_label_ur" class="form-control" value="{{ old('button2_label_ur', $slide->button2_label_ur ?? '') }}">
                    </div>
                </div>
                <p class="small text-muted mb-0" dir="ltr">Shown on the public site when visitors switch to Urdu. Leave blank to fall back to English.</p>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="mb-3">
            <label class="form-label">Display order</label>
            <input type="number" min="0" name="display_order" class="form-control" value="{{ old('display_order', $slide->display_order ?? 0) }}">
        </div>
        <div class="mb-3">
            <label class="form-label">Slide image</label>
            @include('admin.partials.image-size-hint', ['slot' => 'carousel'])
            <input type="file" name="image" class="form-control" accept="image/*">
            @if(!empty($slide?->image))
                <img src="{{ $slide->imageUrl() }}" alt="" class="img-thumbnail mt-2 w-100" style="max-height:180px;object-fit:cover;">
            @endif
        </div>
        <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" name="is_active" value="1" id="is_active"
                {{ old('is_active', $slide->is_active ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">Active on homepage</label>
        </div>
    </div>
</div>
