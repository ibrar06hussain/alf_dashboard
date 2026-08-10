<div class="mb-3">
    <label class="form-label">Department</label>
    <select name="department_id" class="form-control" required>
        <option value="">Select</option>
        @foreach($departments as $department)
            <option value="{{ $department->id }}" @selected(old('department_id', $stat->department_id ?? $selectedDepartmentId ?? '') == $department->id)>
                {{ $department->name }}
            </option>
        @endforeach
    </select>
</div>
<div class="mb-3">
    <label class="form-label">Label (English)</label>
    <input type="text" name="stat_label" class="form-control" value="{{ old('stat_label', $stat->stat_label ?? '') }}" required>
</div>
<div class="mb-3">
    <label class="form-label">Label (Urdu)</label>
    <input type="text" name="stat_label_ur" class="form-control" value="{{ old('stat_label_ur', $stat->stat_label_ur ?? '') }}" dir="rtl" lang="ur">
</div>
<div class="mb-3">
    <label class="form-label">Value</label>
    <input type="text" name="stat_value" class="form-control" value="{{ old('stat_value', $stat->stat_value ?? '0') }}" placeholder="Used for manual source">
    <small class="text-muted">For auto source, value is computed automatically.</small>
</div>
<div class="mb-3">
    <label class="form-label">Icon</label>
    <input type="text" name="icon" class="form-control" value="{{ old('icon', $stat->icon ?? '') }}" placeholder="fa-solid fa-users">
</div>
<div class="mb-3">
    <label class="form-label">Source</label>
    <select name="source" class="form-control" required id="stat-source">
        <option value="manual" @selected(old('source', $stat->source ?? 'manual') === 'manual')>Manual</option>
        <option value="auto" @selected(old('source', $stat->source ?? '') === 'auto')>Auto (from applications)</option>
    </select>
</div>
<div class="mb-3" id="auto-metric-box">
    <label class="form-label">Auto metric</label>
    <select name="auto_metric" class="form-control">
        <option value="">Select metric</option>
        @foreach(($metrics ?? \App\Services\StatsAggregationService::METRICS) as $key => $label)
            <option value="{{ $key }}" @selected(old('auto_metric', $stat->auto_metric ?? '') === $key)>{{ $label }} ({{ $key }})</option>
        @endforeach
    </select>
</div>
<div class="mb-3">
    <label class="form-label">Display order</label>
    <input type="number" name="display_order" class="form-control" value="{{ old('display_order', $stat->display_order ?? 0) }}" min="0">
</div>
<div class="form-check mb-3">
    <input type="checkbox" class="form-check-input" name="is_active" value="1" id="is_active"
        {{ old('is_active', $stat->is_active ?? true) ? 'checked' : '' }}>
    <label class="form-check-label" for="is_active">Active</label>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const source = document.getElementById('stat-source');
    const box = document.getElementById('auto-metric-box');
    if (!source || !box) return;
    const toggle = () => { box.style.display = source.value === 'auto' ? '' : 'none'; };
    source.addEventListener('change', toggle);
    toggle();
});
</script>
