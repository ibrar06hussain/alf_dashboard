<div class="mb-3">
    <label class="form-label">Name</label>
    <input type="text" name="name" class="form-control" value="{{ old('name', $department->name ?? '') }}" required>
</div>
<div class="mb-3">
    <label class="form-label">Slug</label>
    <input type="text" name="slug" class="form-control" value="{{ old('slug', $department->slug ?? '') }}" placeholder="auto from name">
</div>
<div class="mb-3">
    <label class="form-label">Icon class</label>
    <input type="text" name="icon" class="form-control" value="{{ old('icon', $department->icon ?? '') }}" placeholder="fa-solid fa-leaf">
</div>
<div class="mb-3">
    <label class="form-label">Display order</label>
    <input type="number" name="display_order" class="form-control" value="{{ old('display_order', $department->display_order ?? 0) }}" min="0">
</div>
<div class="mb-3">
    <label class="form-label">Vision</label>
    <textarea name="vision" class="form-control" rows="3">{{ old('vision', $department->vision ?? '') }}</textarea>
</div>
<div class="mb-3">
    <label class="form-label">Mission</label>
    <textarea name="mission" class="form-control" rows="3">{{ old('mission', $department->mission ?? '') }}</textarea>
</div>
<div class="mb-3">
    <label class="form-label">Objectives</label>
    <textarea name="objectives" class="form-control" rows="4">{{ old('objectives', $department->objectives ?? '') }}</textarea>
</div>
<div class="mb-3">
    <label class="form-label">Leadership info</label>
    <textarea name="leadership_info" class="form-control" rows="3">{{ old('leadership_info', $department->leadership_info ?? '') }}</textarea>
</div>
<div class="form-check mb-3">
    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active"
        {{ old('is_active', $department->is_active ?? true) ? 'checked' : '' }}>
    <label class="form-check-label" for="is_active">Active</label>
</div>
