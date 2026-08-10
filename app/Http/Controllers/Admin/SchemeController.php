<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\District;
use App\Models\Form;
use App\Models\FormField;
use App\Models\Scheme;
use App\Services\QuotaService;
use App\Support\CoreFormFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SchemeController extends Controller
{
    public function index(Request $request): View
    {
        $query = Scheme::with('department')->withCount(['applications', 'interests'])->latest();

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where('title', 'like', "%{$q}%");
        }

        if ($request->string('type')->toString() === 'proposed') {
            $query->reorder()->orderBy('display_order')->orderByDesc('id');
        }

        $schemes = $query->paginate(20)->withQueryString();
        $departments = Department::orderBy('display_order')->get();

        $base = Scheme::query();
        if ($request->filled('department_id')) {
            $base->where('department_id', $request->integer('department_id'));
        }
        if ($request->filled('type')) {
            $base->where('type', $request->string('type'));
        }
        if ($request->filled('status')) {
            $base->where('status', $request->string('status'));
        }
        if ($request->filled('q')) {
            $base->where('title', 'like', '%'.$request->string('q').'%');
        }

        $pageStats = [
            ['label' => 'Total', 'value' => (clone $base)->count(), 'icon' => 'fa-solid fa-layer-group', 'tone' => 'green'],
            ['label' => 'Active', 'value' => (clone $base)->where('status', 'active')->count(), 'icon' => 'fa-solid fa-circle-check', 'tone' => 'teal'],
            ['label' => 'On Going', 'value' => (clone $base)->where('type', 'running')->count(), 'icon' => 'fa-solid fa-play', 'tone' => 'blue'],
            ['label' => 'Proposed / CM', 'value' => (clone $base)->where('type', 'proposed')->count(), 'icon' => 'fa-solid fa-flag', 'tone' => 'amber'],
        ];

        return view('admin.schemes.index', compact('schemes', 'departments', 'pageStats'));
    }

    public function create(Request $request): View
    {
        $departments = Department::where('is_active', true)->orderBy('display_order')->get();
        $districts = District::where('is_active', true)->orderBy('name')->get();
        $defaultType = in_array($request->string('type')->toString(), ['running', 'proposed'], true)
            ? $request->string('type')->toString()
            : 'running';

        return view('admin.schemes.create', compact('departments', 'districts', 'defaultType'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $data['slug'] ?: Str::slug($data['title']);
        $data['is_featured'] = $request->boolean('is_featured');
        $data['display_order'] = (int) ($data['display_order'] ?? 0);

        if ($request->hasFile('featured_image')) {
            $data['featured_image'] = $request->file('featured_image')->store('schemes', 'public');
        }

        $scheme = DB::transaction(function () use ($data, $request) {
            $scheme = Scheme::create($data);
            $this->syncDistrictQuotas($scheme, $request);

            // Application forms are only needed for on-going schemes.
            if ($scheme->type === 'running') {
                $form = Form::create([
                    'scheme_id' => $scheme->id,
                    'title' => $scheme->title.' Application',
                    'version' => 1,
                    'is_published' => false,
                ]);

                foreach (CoreFormFields::definitions() as $field) {
                    FormField::create(array_merge($field, ['form_id' => $form->id]));
                }
            }

            return $scheme;
        });

        if ($scheme->type === 'proposed') {
            return redirect()
                ->route('admin.schemes.show', $scheme)
                ->with('success', 'Proposed scheme saved. It will appear on the public CM section when status is Active and “Show on CM home” is checked.');
        }

        return redirect()
            ->route('admin.form-builder.edit', $scheme)
            ->with('success', 'Scheme created. Configure the form builder next.');
    }

    public function show(Scheme $scheme, QuotaService $quotas): View
    {
        $scheme->load(['department', 'schemeDistricts.district', 'forms.fields', 'forms.documents']);

        $quotaUsage = [];
        foreach ($scheme->schemeDistricts as $sd) {
            $quotaUsage[$sd->district_id] = $quotas->status($scheme, $sd->district_id);
        }

        return view('admin.schemes.show', compact('scheme', 'quotaUsage'));
    }

    public function edit(Scheme $scheme): View
    {
        $scheme->load('schemeDistricts');
        $departments = Department::orderBy('display_order')->get();
        $districts = District::where('is_active', true)->orderBy('name')->get();
        $quotas = $scheme->schemeDistricts->pluck('quota', 'district_id');
        $defaultType = $scheme->type;

        return view('admin.schemes.edit', compact('scheme', 'departments', 'districts', 'quotas', 'defaultType'));
    }

    public function update(Request $request, Scheme $scheme): RedirectResponse
    {
        $data = $this->validated($request, $scheme->id);
        $data['slug'] = $data['slug'] ?: Str::slug($data['title']);
        $data['is_featured'] = $request->boolean('is_featured');
        $data['display_order'] = (int) ($data['display_order'] ?? 0);

        if ($request->hasFile('featured_image')) {
            if ($scheme->featured_image) {
                Storage::disk('public')->delete($scheme->featured_image);
            }
            $data['featured_image'] = $request->file('featured_image')->store('schemes', 'public');
        }

        DB::transaction(function () use ($scheme, $data, $request) {
            $scheme->update($data);
            $this->syncDistrictQuotas($scheme, $request);
        });

        $redirect = $scheme->type === 'proposed'
            ? route('admin.schemes.index', ['type' => 'proposed'])
            : route('admin.schemes.index');

        return redirect($redirect)->with('success', 'Scheme updated.');
    }

    public function destroy(Scheme $scheme): RedirectResponse
    {
        $type = $scheme->type;
        if ($scheme->featured_image) {
            Storage::disk('public')->delete($scheme->featured_image);
        }
        $scheme->delete();

        $redirect = $type === 'proposed'
            ? route('admin.schemes.index', ['type' => 'proposed'])
            : route('admin.schemes.index');

        return redirect($redirect)->with('success', 'Scheme deleted.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'title' => ['required', 'string', 'max:255'],
            'title_ur' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:schemes,slug,'.($ignoreId ?? 'NULL')],
            'type' => ['required', 'in:running,proposed'],
            'scope' => ['required', 'in:gb,district'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'last_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'in:draft,active,closed'],
            'description' => ['nullable', 'string'],
            'description_ur' => ['nullable', 'string'],
            'eligibility_criteria' => ['nullable', 'string'],
            'eligibility_criteria_ur' => ['nullable', 'string'],
            'terms_conditions' => ['nullable', 'string'],
            'terms_conditions_ur' => ['nullable', 'string'],
            'is_featured' => ['nullable'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'featured_image' => ['nullable', 'image', 'max:5120'],
            'district_quotas' => ['nullable', 'array'],
            'district_quotas.*' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function syncDistrictQuotas(Scheme $scheme, Request $request): void
    {
        if ($scheme->scope !== 'district') {
            $scheme->districts()->detach();

            return;
        }

        $quotas = $request->input('district_quotas', []);
        $sync = [];

        foreach ($quotas as $districtId => $quota) {
            if ($quota === null || $quota === '') {
                continue;
            }
            $sync[(int) $districtId] = ['quota' => (int) $quota];
        }

        $scheme->districts()->sync($sync);
    }
}
