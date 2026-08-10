<div class="row">
    <div class="col-md-8">
        <div class="card alf-panel mb-3">
            <div class="card-header">English content</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Title (English)</label>
                    <input type="text" name="title" class="form-control" value="{{ old('title', $scheme->title ?? '') }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Slug</label>
                    <input type="text" name="slug" class="form-control" value="{{ old('slug', $scheme->slug ?? '') }}" placeholder="auto from title">
                </div>
                <div class="mb-3">
                    <label class="form-label">Description (English)</label>
                    <textarea name="description" class="form-control" rows="4">{{ old('description', $scheme->description ?? '') }}</textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Eligibility criteria (English)</label>
                    <textarea name="eligibility_criteria" class="form-control" rows="3">{{ old('eligibility_criteria', $scheme->eligibility_criteria ?? '') }}</textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Terms &amp; conditions (English)</label>
                    <textarea name="terms_conditions" class="form-control" rows="3">{{ old('terms_conditions', $scheme->terms_conditions ?? '') }}</textarea>
                </div>
            </div>
        </div>

        <div class="card alf-panel mb-3">
            <div class="card-header">Urdu content (اردو)</div>
            <div class="card-body" dir="rtl" lang="ur">
                <div class="mb-3">
                    <label class="form-label">Title (Urdu)</label>
                    <input type="text" name="title_ur" class="form-control" value="{{ old('title_ur', $scheme->title_ur ?? '') }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Description (Urdu)</label>
                    <textarea name="description_ur" class="form-control" rows="4">{{ old('description_ur', $scheme->description_ur ?? '') }}</textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Eligibility criteria (Urdu)</label>
                    <textarea name="eligibility_criteria_ur" class="form-control" rows="3">{{ old('eligibility_criteria_ur', $scheme->eligibility_criteria_ur ?? '') }}</textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Terms &amp; conditions (Urdu)</label>
                    <textarea name="terms_conditions_ur" class="form-control" rows="3">{{ old('terms_conditions_ur', $scheme->terms_conditions_ur ?? '') }}</textarea>
                </div>
                <p class="small text-muted mb-0" dir="ltr">Shown when visitors switch to Urdu. Leave blank to fall back to English.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="mb-3">
            <label class="form-label">Department</label>
            <select name="department_id" class="form-control" required>
                <option value="">Select</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" @selected(old('department_id', $scheme->department_id ?? '') == $department->id)>
                        {{ $department->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Type</label>
            <select name="type" id="scheme-type" class="form-control" required>
                @foreach(\App\Models\Scheme::typeLabels() as $type => $label)
                    <option value="{{ $type }}" @selected(old('type', $scheme->type ?? ($defaultType ?? 'running')) === $type)>{{ $label }}</option>
                @endforeach
            </select>
            <small class="text-muted">Choose <strong>Proposed</strong> for CM presentation / express-interest schemes (no application form required).</small>
        </div>
        <div class="mb-3">
            <label class="form-label">Scope</label>
            <select name="scope" id="scope" class="form-control" required>
                @foreach(['gb','district'] as $scope)
                    <option value="{{ $scope }}" @selected(old('scope', $scheme->scope ?? 'gb') === $scope)>{{ strtoupper($scope) }}</option>
                @endforeach
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-control" required>
                @foreach(['draft','active','closed'] as $status)
                    <option value="{{ $status }}" @selected(old('status', $scheme->status ?? 'draft') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Budget (PKR)</label>
            <input type="number" step="0.01" name="budget" class="form-control" value="{{ old('budget', $scheme->budget ?? '') }}">
        </div>
        <div class="mb-3">
            <label class="form-label">Display order</label>
            <input type="number" min="0" name="display_order" class="form-control" value="{{ old('display_order', $scheme->display_order ?? 0) }}">
            <small class="text-muted">Lower numbers appear first on the CM home section.</small>
        </div>
        <div class="mb-3">
            <label class="form-label">Cover image</label>
            @include('admin.partials.image-size-hint', ['slot' => 'scheme_cover'])
            <input type="file" name="featured_image" class="form-control" accept="image/*">
            @if(!empty($scheme?->featured_image))
                <div class="mt-2 border rounded overflow-hidden" style="max-width:100%;">
                    <img src="{{ $scheme->imageUrl() }}" alt="" class="w-100" style="max-height:160px;object-fit:cover;display:block;">
                </div>
                <small class="text-muted">Current uploaded cover</small>
            @endif
        </div>
        <div class="mb-3">
            <label class="form-label">Start date</label>
            <input type="date" name="start_date" class="form-control" value="{{ old('start_date', isset($scheme) && $scheme->start_date ? $scheme->start_date->format('Y-m-d') : '') }}">
        </div>
        <div class="mb-3">
            <label class="form-label">Last date</label>
            <input type="date" name="last_date" class="form-control" value="{{ old('last_date', isset($scheme) && $scheme->last_date ? $scheme->last_date->format('Y-m-d') : '') }}">
        </div>
        <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" name="is_featured" value="1" id="is_featured"
                {{ old('is_featured', $scheme->is_featured ?? (($defaultType ?? 'running') === 'proposed')) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_featured" id="is_featured_label">Featured on home</label>
        </div>
    </div>
</div>

<div class="main-card mb-3 card" id="district-quotas" style="{{ old('scope', $scheme->scope ?? 'gb') === 'district' ? '' : 'display:none' }}">
    <div class="card-header">District Quotas</div>
    <div class="card-body">
        <div class="row">
            @foreach($districts as $district)
                @php
                    $quotaValue = old('district_quotas.'.$district->id, $quotas[$district->id] ?? '');
                @endphp
                <div class="col-md-3 mb-3">
                    <label class="form-label">{{ $district->name }}</label>
                    <input type="number" min="0" name="district_quotas[{{ $district->id }}]" class="form-control" value="{{ $quotaValue }}" placeholder="Quota">
                </div>
            @endforeach
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const scope = document.getElementById('scope');
    const box = document.getElementById('district-quotas');
    if (scope && box) {
        const toggle = () => { box.style.display = scope.value === 'district' ? '' : 'none'; };
        scope.addEventListener('change', toggle);
        toggle();
    }

    const typeSelect = document.getElementById('scheme-type');
    const featuredLabel = document.getElementById('is_featured_label');
    const submitBtn = document.getElementById('scheme-submit-btn');
    const syncTypeUi = () => {
        if (!typeSelect) return;
        const proposed = typeSelect.value === 'proposed';
        if (featuredLabel) {
            featuredLabel.textContent = proposed ? 'Show on CM home (Proposed section)' : 'Featured on home (On Going section)';
        }
        if (submitBtn) {
            submitBtn.textContent = proposed ? 'Save Proposed Scheme' : (submitBtn.dataset.runningLabel || 'Save Scheme');
        }
    };
    if (typeSelect) {
        typeSelect.addEventListener('change', syncTypeUi);
        syncTypeUi();
    }
});
</script>
@endpush
