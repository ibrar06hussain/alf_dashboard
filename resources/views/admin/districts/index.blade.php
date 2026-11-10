@extends('layouts.admin')

@section('title', 'Districts, Tehsils & Union Councils')

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'Districts, Tehsils & Union Councils',
    'subtitle' => 'Manage Gilgit-Baltistan districts along with their tehsils and union councils used across application forms and quotas.',
    'icon' => 'fa-solid fa-location-dot',
])

@include('admin.partials.stats', ['stats' => $pageStats])

@php
    $expandedDistrictId = (int) (session('expanded_district_id') ?: request('district_id', 0));
    $expandedTehsilId = (int) (session('expanded_tehsil_id') ?: request('tehsil_id', 0));
@endphp

<div class="row g-3">
    {{-- Left Sidebar: Quick Create Forms --}}
    <div class="col-lg-4">
        {{-- 1. Add District --}}
        <div class="card alf-panel mb-3">
            <div class="card-header">
                <i class="fa-solid fa-map text-primary me-1"></i> Add District
            </div>
            <div class="card-body alf-form-grid">
                <form method="POST" action="{{ route('admin.districts.store') }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label">Name (English) <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ !old('district_id') && !old('tehsil_id') ? old('name') : '' }}" placeholder="e.g. Roundu" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Name (Urdu)</label>
                        <input type="text" name="name_ur" class="form-control" value="{{ old('name_ur') }}" dir="rtl" lang="ur" placeholder="اردو نام">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control" value="{{ old('code') }}" placeholder="e.g. RND" required maxlength="20">
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" name="is_active" value="1" id="new_active" checked>
                        <label class="form-check-label" for="new_active">Active</label>
                    </div>
                    <div class="alf-form-actions">
                        <button class="btn btn-primary w-100">
                            <i class="fa-solid fa-plus me-1"></i> Create District
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- 2. Add Tehsil against District --}}
        <div class="card alf-panel mb-3">
            <div class="card-header">
                <i class="fa-solid fa-map-pin text-warning me-1"></i> Add Tehsil
            </div>
            <div class="card-body alf-form-grid">
                <form method="POST" action="{{ route('admin.tehsils.store') }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label">District <span class="text-danger">*</span></label>
                        <select name="district_id" id="sidebar_tehsil_district" class="form-select" required>
                            <option value="">Select District...</option>
                            @foreach($allDistricts as $d)
                                <option value="{{ $d->id }}" @selected((int) old('district_id', $expandedDistrictId) === (int) $d->id)>
                                    {{ $d->name }} ({{ $d->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tehsil Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ old('district_id') && !old('tehsil_id') ? old('name') : '' }}" placeholder="e.g. Danyore" required maxlength="80">
                    </div>
                    <div class="alf-form-actions">
                        <button class="btn btn-primary w-100">
                            <i class="fa-solid fa-plus me-1"></i> Create Tehsil
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- 3. Add Union Council against District / Tehsil --}}
        <div class="card alf-panel mb-3">
            <div class="card-header">
                <i class="fa-solid fa-layer-group text-info me-1"></i> Add Union Council
            </div>
            <div class="card-body alf-form-grid">
                <form method="POST" action="{{ route('admin.union-councils.store') }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label">District <span class="text-danger">*</span></label>
                        <select id="sidebar_uc_district" class="form-select" required>
                            <option value="">Select District...</option>
                            @foreach($allDistricts as $d)
                                <option value="{{ $d->id }}" @selected($expandedDistrictId === (int) $d->id)>
                                    {{ $d->name }} ({{ $d->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Tehsil <span class="text-danger">*</span></label>
                        <select name="tehsil_id" id="sidebar_uc_tehsil" class="form-select" required>
                            <option value="">Select Tehsil...</option>
                            @foreach($allDistricts as $d)
                                @foreach($d->tehsils as $t)
                                    <option value="{{ $t->id }}"
                                        data-district-id="{{ $d->id }}"
                                        @selected((int) old('tehsil_id', $expandedTehsilId) === (int) $t->id)>
                                        {{ $t->name }} ({{ $d->name }})
                                    </option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Union Council Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ old('tehsil_id') ? old('name') : '' }}" placeholder="e.g. UC Baseen" required maxlength="100">
                    </div>
                    <div class="alf-form-actions">
                        <button class="btn btn-primary w-100">
                            <i class="fa-solid fa-plus me-1"></i> Create Union Council
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Right Column: Districts Hierarchy & Inline Tehsil / UC Management --}}
    <div class="col-lg-8">
        <div class="card alf-panel mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>All Districts, Tehsils &amp; Union Councils</span>
                <div class="d-flex align-items-center gap-2">
                    <div class="input-group input-group-sm" style="width: 240px;">
                        <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" id="locationSearchInput" class="form-control" placeholder="Search district, tehsil, or UC...">
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnExpandAllDistricts" title="Expand all districts">
                        <i class="fa-solid fa-angles-down"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnCollapseAllDistricts" title="Collapse all districts">
                        <i class="fa-solid fa-angles-up"></i>
                    </button>
                </div>
            </div>

            <div class="card-body">
                @forelse($districts as $district)
                    @php
                        $isDistrictExpanded = ($expandedDistrictId === (int) $district->id);
                        $districtUcCount = $district->tehsils->sum('union_councils_count');
                        $districtSearchKeywords = strtolower(
                            $district->name . ' ' . $district->code . ' ' .
                            $district->tehsils->pluck('name')->implode(' ') . ' ' .
                            $district->tehsils->flatMap->unionCouncils->pluck('name')->implode(' ')
                        );
                    @endphp
                    <div class="card border mb-3 shadow-none district-card"
                         data-district-id="{{ $district->id }}"
                         data-search="{{ $districtSearchKeywords }}">
                        {{-- District Row Header & Inline Update Form --}}
                        <div class="card-header bg-white py-2 px-3">
                            <form method="POST" action="{{ route('admin.districts.update', $district) }}" class="row g-2 align-items-center">
                                @csrf
                                @method('PUT')
                                <div class="col-md-3 col-sm-6">
                                    <label class="form-label small text-muted mb-0 d-md-none">Name</label>
                                    <input type="text" name="name" class="form-control form-control-sm fw-bold" value="{{ old('name_'.$district->id, $district->name) }}" required title="District Name">
                                </div>
                                <div class="col-md-2 col-sm-6">
                                    <label class="form-label small text-muted mb-0 d-md-none">Urdu</label>
                                    <input type="text" name="name_ur" class="form-control form-control-sm" value="{{ old('name_ur_'.$district->id, $district->name_ur) }}" dir="rtl" lang="ur" placeholder="اردو" title="Urdu Name">
                                </div>
                                <div class="col-md-2 col-4">
                                    <input type="text" name="code" class="form-control form-control-sm font-monospace" value="{{ old('code_'.$district->id, $district->code) }}" required maxlength="20" title="District Code">
                                </div>
                                <div class="col-md-2 col-4">
                                    <div class="form-check mb-0">
                                        <input type="checkbox" class="form-check-input" name="is_active" value="1" id="active_{{ $district->id }}"
                                            {{ $district->is_active ? 'checked' : '' }}>
                                        <label class="form-check-label small" for="active_{{ $district->id }}">Active</label>
                                    </div>
                                    <div class="text-muted" style="font-size: 0.72rem;">{{ $district->applications_count }} apps</div>
                                </div>
                                <div class="col-md-3 col-4 d-flex justify-content-end align-items-center gap-1 flex-wrap">
                                    <button type="submit" class="btn btn-sm btn-primary" title="Save District">
                                        <i class="fa-solid fa-floppy-disk"></i> Save
                                    </button>
                                    <button type="button"
                                            class="btn btn-sm {{ $isDistrictExpanded ? 'btn-success' : 'btn-outline-success' }} btn-toggle-tehsils"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#tehsils-panel-{{ $district->id }}"
                                            aria-expanded="{{ $isDistrictExpanded ? 'true' : 'false' }}"
                                            title="Manage Tehsils & Union Councils">
                                        <i class="fa-solid fa-sitemap me-1"></i>
                                        {{ $district->tehsils_count }}T / {{ $districtUcCount }}UC
                                    </button>
                                </div>
                            </form>
                        </div>

                        {{-- Collapsible Tehsils & Union Councils Container --}}
                        <div id="tehsils-panel-{{ $district->id }}" class="collapse district-collapse {{ $isDistrictExpanded ? 'show' : '' }}">
                            <div class="card-body bg-light p-3 border-top">
                                {{-- Add Tehsil inline to this District --}}
                                <div class="bg-white p-2 rounded border mb-3">
                                    <form method="POST" action="{{ route('admin.districts.tehsils.store', $district) }}" class="d-flex align-items-center gap-2">
                                        @csrf
                                        <span class="badge bg-warning-subtle text-dark border border-warning-subtle text-nowrap">
                                            <i class="fa-solid fa-map-pin me-1"></i> {{ $district->name }}
                                        </span>
                                        <input type="text" name="name" class="form-control form-control-sm" placeholder="New Tehsil name in {{ $district->name }}..." required maxlength="80">
                                        <button type="submit" class="btn btn-sm btn-primary text-nowrap">
                                            <i class="fa-solid fa-plus me-1"></i> Add Tehsil
                                        </button>
                                    </form>
                                </div>

                                {{-- Tehsils List --}}
                                @forelse($district->tehsils as $tehsil)
                                    @php
                                        $isTehsilExpanded = ($expandedTehsilId === (int) $tehsil->id);
                                        $tehsilSearchKeywords = strtolower($tehsil->name . ' ' . $tehsil->unionCouncils->pluck('name')->implode(' '));
                                    @endphp
                                    <div class="card border mb-2 shadow-none tehsil-card"
                                         data-tehsil-id="{{ $tehsil->id }}"
                                         data-search="{{ $tehsilSearchKeywords }}">
                                        <div class="card-header bg-white py-2 px-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                            {{-- Edit Tehsil Inline Form --}}
                                            <form method="POST" action="{{ route('admin.tehsils.update', $tehsil) }}" class="d-flex align-items-center gap-1 flex-grow-1" style="min-width: 240px;">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="district_id" value="{{ $district->id }}">
                                                <span class="text-warning px-1"><i class="fa-solid fa-map-pin"></i></span>
                                                <input type="text" name="name" class="form-control form-control-sm fw-semibold" value="{{ $tehsil->name }}" required maxlength="80" aria-label="Tehsil Name">
                                                <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap" title="Save Tehsil">
                                                    <i class="fa-solid fa-check"></i> Save
                                                </button>
                                            </form>

                                            {{-- Tehsil Actions: Toggle UCs & Delete Tehsil --}}
                                            <div class="d-flex align-items-center gap-1">
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-info btn-toggle-ucs"
                                                        data-bs-toggle="collapse"
                                                        data-bs-target="#uc-panel-{{ $tehsil->id }}"
                                                        aria-expanded="{{ $isTehsilExpanded ? 'true' : 'false' }}">
                                                    <i class="fa-solid fa-layer-group me-1"></i>
                                                    Union Councils ({{ $tehsil->union_councils_count }})
                                                </button>

                                                <form method="POST" action="{{ route('admin.tehsils.destroy', $tehsil) }}"
                                                      onsubmit="return confirm('Delete Tehsil &quot;{{ addslashes($tehsil->name) }}&quot;?{{ $tehsil->union_councils_count > 0 ? ' This will also delete its ' . $tehsil->union_councils_count . ' Union Council(s).' : '' }}');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Tehsil">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>

                                        {{-- Union Councils Panel --}}
                                        <div id="uc-panel-{{ $tehsil->id }}" class="collapse uc-collapse {{ $isTehsilExpanded ? 'show' : '' }}">
                                            <div class="card-body bg-light-subtle p-2 border-top">
                                                {{-- Add UC inline to this Tehsil --}}
                                                <form method="POST" action="{{ route('admin.tehsils.union-councils.store', $tehsil) }}" class="d-flex align-items-center gap-2 mb-2">
                                                    @csrf
                                                    <input type="text" name="name" class="form-control form-control-sm" placeholder="New Union Council name in {{ $tehsil->name }}..." required maxlength="100">
                                                    <button type="submit" class="btn btn-sm btn-info text-white text-nowrap">
                                                        <i class="fa-solid fa-plus me-1"></i> Add UC
                                                    </button>
                                                </form>

                                                {{-- Existing Union Councils Grid --}}
                                                @if($tehsil->unionCouncils->isNotEmpty())
                                                    <div class="row g-2">
                                                        @foreach($tehsil->unionCouncils as $uc)
                                                            <div class="col-md-6 uc-item" data-search="{{ strtolower($uc->name) }}">
                                                                <div class="d-flex align-items-center gap-1 bg-white border rounded p-1">
                                                                    <form method="POST" action="{{ route('admin.union-councils.update', $uc) }}" class="d-flex align-items-center gap-1 flex-grow-1 mb-0">
                                                                        @csrf
                                                                        @method('PUT')
                                                                        <input type="hidden" name="tehsil_id" value="{{ $tehsil->id }}">
                                                                        <input type="text" name="name" class="form-control form-control-sm border-0 bg-transparent px-1" value="{{ $uc->name }}" required maxlength="100" aria-label="Union Council Name">
                                                                        <button type="submit" class="btn btn-xs btn-outline-primary py-0 px-2" title="Save Union Council">
                                                                            <i class="fa-solid fa-check"></i>
                                                                        </button>
                                                                    </form>
                                                                    <form method="POST" action="{{ route('admin.union-councils.destroy', $uc) }}" class="mb-0"
                                                                          onsubmit="return confirm('Delete Union Council &quot;{{ addslashes($uc->name) }}&quot;?');">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button type="submit" class="btn btn-xs btn-outline-danger py-0 px-2" title="Delete Union Council">
                                                                            <i class="fa-solid fa-trash"></i>
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <div class="small text-muted fst-italic px-1">No union councils recorded for {{ $tehsil->name }} yet.</div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="small text-muted fst-italic p-2 bg-white border rounded">
                                        No tehsils added under {{ $district->name }} yet. Use the form above to add one.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="alf-empty">No districts found.</div>
                @endforelse

                <div class="mt-3">{{ $districts->links() }}</div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Cascading District -> Tehsil selector in Sidebar Quick-Add UC Form
    const ucDistrictSelect = document.getElementById('sidebar_uc_district');
    const ucTehsilSelect = document.getElementById('sidebar_uc_tehsil');

    if (ucDistrictSelect && ucTehsilSelect) {
        const allTehsilOptions = Array.from(ucTehsilSelect.querySelectorAll('option[data-district-id]'));

        function filterSidebarTehsils() {
            const distId = ucDistrictSelect.value;
            const currentVal = ucTehsilSelect.value;
            ucTehsilSelect.innerHTML = '<option value="">Select Tehsil...</option>';

            allTehsilOptions.forEach(opt => {
                if (!distId || opt.getAttribute('data-district-id') === distId) {
                    const cloned = opt.cloneNode(true);
                    if (cloned.value === currentVal) {
                        cloned.selected = true;
                    }
                    ucTehsilSelect.appendChild(cloned);
                }
            });
        }

        ucDistrictSelect.addEventListener('change', filterSidebarTehsils);
        if (ucDistrictSelect.value) {
            filterSidebarTehsils();
        }
    }

    // 2. Expand All / Collapse All Districts
    document.getElementById('btnExpandAllDistricts')?.addEventListener('click', function () {
        document.querySelectorAll('.district-collapse').forEach(el => {
            el.classList.add('show');
        });
    });

    document.getElementById('btnCollapseAllDistricts')?.addEventListener('click', function () {
        document.querySelectorAll('.district-collapse, .uc-collapse').forEach(el => {
            el.classList.remove('show');
        });
    });

    // 3. Live Search across Districts, Tehsils, and Union Councils
    const searchInput = document.getElementById('locationSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            const districtCards = document.querySelectorAll('.district-card');

            districtCards.forEach(dCard => {
                if (!q) {
                    dCard.style.display = '';
                    dCard.querySelectorAll('.tehsil-card, .uc-item').forEach(el => el.style.display = '');
                    return;
                }

                const dText = dCard.getAttribute('data-search') || '';
                const matchesDistrict = dText.includes(q);
                dCard.style.display = matchesDistrict ? '' : 'none';

                if (matchesDistrict) {
                    const tehsilCards = dCard.querySelectorAll('.tehsil-card');
                    let anyTehsilMatched = false;

                    tehsilCards.forEach(tCard => {
                        const tText = tCard.getAttribute('data-search') || '';
                        const tMatches = tText.includes(q);
                        if (tMatches) {
                            anyTehsilMatched = true;
                            tCard.style.display = '';
                            const ucPanel = tCard.querySelector('.uc-collapse');
                            if (ucPanel) {
                                // Open UC panel if a UC matched
                                const ucItems = tCard.querySelectorAll('.uc-item');
                                let anyUcMatched = false;
                                ucItems.forEach(uItem => {
                                    const uMatches = (uItem.getAttribute('data-search') || '').includes(q);
                                    if (uMatches) {
                                        anyUcMatched = true;
                                        uItem.style.display = '';
                                    } else {
                                        uItem.style.display = tText.includes(q) && !(uItem.getAttribute('data-search') || '').includes(q) ? 'none' : '';
                                    }
                                });
                                if (anyUcMatched) {
                                    ucPanel.classList.add('show');
                                }
                            }
                        } else {
                            // District name itself matched, keep tehsils visible
                            tCard.style.display = '';
                        }
                    });

                    if (anyTehsilMatched) {
                        const tehsilPanel = dCard.querySelector('.district-collapse');
                        if (tehsilPanel) tehsilPanel.classList.add('show');
                    }
                }
            });
        });
    }
});
</script>
@endsection
