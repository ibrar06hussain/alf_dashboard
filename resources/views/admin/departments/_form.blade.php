<div class="card alf-panel mb-3">
    <div class="card-header">English content</div>
    <div class="card-body">
        <div class="mb-3">
            <label class="form-label">Name (English)</label>
            <input type="text" name="name" class="form-control" value="{{ old('name', $department->name ?? '') }}" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Slug</label>
            <input type="text" name="slug" class="form-control" value="{{ old('slug', $department->slug ?? '') }}" placeholder="auto from name">
        </div>
        <div class="mb-3">
            <label class="form-label">Vision (English)</label>
            <textarea name="vision" class="form-control" rows="3">{{ old('vision', $department->vision ?? '') }}</textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Mission (English)</label>
            <textarea name="mission" class="form-control" rows="3">{{ old('mission', $department->mission ?? '') }}</textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Objectives (English)</label>
            <textarea name="objectives" class="form-control" rows="4">{{ old('objectives', $department->objectives ?? '') }}</textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Leadership info (English)</label>
            <textarea name="leadership_info" class="form-control" rows="3">{{ old('leadership_info', $department->leadership_info ?? '') }}</textarea>
        </div>
    </div>
</div>

<div class="card alf-panel mb-3">
    <div class="card-header">Urdu content (اردو)</div>
    <div class="card-body" dir="rtl" lang="ur">
        <div class="mb-3">
            <label class="form-label">Name (Urdu)</label>
            <input type="text" name="name_ur" class="form-control" value="{{ old('name_ur', $department->name_ur ?? '') }}">
        </div>
        <div class="mb-3">
            <label class="form-label">Vision (Urdu)</label>
            <textarea name="vision_ur" class="form-control" rows="3">{{ old('vision_ur', $department->vision_ur ?? '') }}</textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Mission (Urdu)</label>
            <textarea name="mission_ur" class="form-control" rows="3">{{ old('mission_ur', $department->mission_ur ?? '') }}</textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Objectives (Urdu)</label>
            <textarea name="objectives_ur" class="form-control" rows="4">{{ old('objectives_ur', $department->objectives_ur ?? '') }}</textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Leadership info (Urdu)</label>
            <textarea name="leadership_info_ur" class="form-control" rows="3">{{ old('leadership_info_ur', $department->leadership_info_ur ?? '') }}</textarea>
        </div>
        <p class="small text-muted mb-0" dir="ltr">Shown when visitors switch to Urdu. Leave blank to fall back to English.</p>
    </div>
</div>

<div class="mb-3">
    <label class="form-label">Icon class</label>
    <input type="text" name="icon" class="form-control" value="{{ old('icon', $department->icon ?? '') }}" placeholder="fa-solid fa-leaf">
</div>
<div class="mb-3">
    <label class="form-label">Cover image</label>
    @include('admin.partials.image-size-hint', ['slot' => 'department_cover'])
    <input type="file" name="cover_image" class="form-control" accept="image/*">
    @if(!empty($department?->cover_image))
        <div class="mt-2 border rounded overflow-hidden" style="max-width:420px;">
            <img src="{{ $department->coverUrl() }}" alt="" class="w-100" style="max-height:180px;object-fit:cover;display:block;">
        </div>
        <small class="text-muted">Current uploaded cover</small>
    @endif
    <small class="text-muted d-block mt-1">Used on department cards and page banners.</small>
</div>
<div class="mb-3">
    <label class="form-label">Display order</label>
    <input type="number" name="display_order" class="form-control" value="{{ old('display_order', $department->display_order ?? 0) }}" min="0">
</div>
<div class="form-check mb-3">
    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active"
        {{ old('is_active', $department->is_active ?? true) ? 'checked' : '' }}>
    <label class="form-check-label" for="is_active">Active</label>
</div>
