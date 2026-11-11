<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\District;
use App\Models\Form;
use App\Models\FormField;
use App\Models\PsidConfig;
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
        $user = auth()->user();
        $query = Scheme::with(['department', 'challanTemplate'])->withCount(['applications', 'interests'])->latest();

        // Scope by authorized departments and districts if user is not global
        if ($user && ! $user->isGlobalScope()) {
            $userDepts = $user->authorizedDepartmentIds();
            $userDistricts = $user->authorizedDistrictIds();

            if (! empty($userDepts)) {
                $query->whereIn('department_id', $userDepts);
            }

            if (! empty($userDistricts)) {
                $query->where(function ($sq) use ($userDistricts) {
                    $sq->whereIn('scope', ['gb', 'province_wide'])
                        ->orWhereHas('districts', fn ($dq) => $dq->whereIn('districts.id', $userDistricts));
                });
            }
        }

        if ($request->filled('department_id')) {
            $deptId = $request->integer('department_id');
            if (! $user || $user->isGlobalScope() || in_array($deptId, $user->authorizedDepartmentIds(), true)) {
                $query->where('department_id', $deptId);
            }
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
        $departments = Department::when($user && ! $user->isGlobalScope() && ! empty($user->authorizedDepartmentIds()), function ($q) use ($user) {
            $q->whereIn('id', $user->authorizedDepartmentIds());
        })->orderBy('display_order')->get();

        $base = Scheme::query();
        if ($user && ! $user->isGlobalScope()) {
            $userDepts = $user->authorizedDepartmentIds();
            $userDistricts = $user->authorizedDistrictIds();

            if (! empty($userDepts)) {
                $base->whereIn('department_id', $userDepts);
            }

            if (! empty($userDistricts)) {
                $base->where(function ($sq) use ($userDistricts) {
                    $sq->whereIn('scope', ['gb', 'province_wide'])
                        ->orWhereHas('districts', fn ($dq) => $dq->whereIn('districts.id', $userDistricts));
                });
            }
        }

        if ($request->filled('department_id')) {
            $deptId = $request->integer('department_id');
            if (! $user || $user->isGlobalScope() || in_array($deptId, $user->authorizedDepartmentIds(), true)) {
                $base->where('department_id', $deptId);
            }
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
        $challanTemplates = \App\Models\ChallanTemplate::where('is_active', true)->orderBy('name')->get();
        $globalPsidConfig = PsidConfig::getActive();
        $defaultType = in_array($request->string('type')->toString(), ['running', 'proposed'], true)
            ? $request->string('type')->toString()
            : 'running';

        return view('admin.schemes.create', compact('departments', 'districts', 'challanTemplates', 'globalPsidConfig', 'defaultType'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = ! empty($data['slug']) ? $data['slug'] : Str::slug($data['title']);
        $data['is_featured'] = $request->boolean('is_featured');
        $data['requires_challan'] = $request->boolean('requires_challan');
        $data['display_order'] = (int) ($data['display_order'] ?? 0);

        $schemePayload = collect($data)->except([
            'use_custom_psid', 'psid_one_link_prefix', 'psid_department_code',
            'psid_department_service_no', 'psid_district_code', 'psid_rco', 'psid_head',
        ])->all();

        if ($request->hasFile('featured_image')) {
            $schemePayload['featured_image'] = $request->file('featured_image')->store('schemes', 'public');
        }

        $scheme = DB::transaction(function () use ($schemePayload, $request) {
            $scheme = Scheme::create($schemePayload);
            $this->syncDistrictQuotas($scheme, $request);
            $this->syncPsidConfig($scheme, $request);

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
        $scheme->load(['department', 'challanTemplate', 'schemeDistricts.district', 'forms.fields', 'forms.documents']);

        $quotaUsage = [];
        foreach ($scheme->schemeDistricts as $sd) {
            $quotaUsage[$sd->district_id] = $quotas->status($scheme, $sd->district_id);
        }

        return view('admin.schemes.show', compact('scheme', 'quotaUsage'));
    }

    public function edit(Scheme $scheme): View
    {
        $scheme->load(['schemeDistricts', 'challanTemplate', 'psidConfig']);
        $departments = Department::orderBy('display_order')->get();
        $districts = District::where('is_active', true)->orderBy('name')->get();
        $challanTemplates = \App\Models\ChallanTemplate::where('is_active', true)->orderBy('name')->get();
        $globalPsidConfig = PsidConfig::getActive();
        $quotas = $scheme->schemeDistricts->pluck('quota', 'district_id');
        $defaultType = $scheme->type;

        return view('admin.schemes.edit', compact('scheme', 'departments', 'districts', 'challanTemplates', 'globalPsidConfig', 'quotas', 'defaultType'));
    }

    public function update(Request $request, Scheme $scheme): RedirectResponse
    {
        $data = $this->validated($request, $scheme->id);
        $data['slug'] = ! empty($data['slug']) ? $data['slug'] : Str::slug($data['title']);
        $data['is_featured'] = $request->boolean('is_featured');
        $data['requires_challan'] = $request->boolean('requires_challan');
        $data['display_order'] = (int) ($data['display_order'] ?? 0);

        $schemePayload = collect($data)->except([
            'use_custom_psid', 'psid_one_link_prefix', 'psid_department_code',
            'psid_department_service_no', 'psid_district_code', 'psid_rco', 'psid_head',
        ])->all();

        if ($request->hasFile('featured_image')) {
            if ($scheme->featured_image) {
                Storage::disk('public')->delete($scheme->featured_image);
            }
            $schemePayload['featured_image'] = $request->file('featured_image')->store('schemes', 'public');
        }

        DB::transaction(function () use ($scheme, $schemePayload, $request) {
            $scheme->update($schemePayload);
            $this->syncDistrictQuotas($scheme, $request);
            $this->syncPsidConfig($scheme, $request);
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
        if ($request->filled('psid_department_code')) {
            $request->merge([
                'psid_department_code' => str_pad(trim((string) $request->input('psid_department_code')), 2, '0', STR_PAD_LEFT),
            ]);
        }

        if ($request->filled('psid_department_service_no')) {
            $request->merge([
                'psid_department_service_no' => str_pad(trim((string) $request->input('psid_department_service_no')), 2, '0', STR_PAD_LEFT),
            ]);
        }

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
            'requires_challan' => ['nullable'],
            'challan_fee_per_unit' => ['nullable', 'numeric', 'min:0'],
            'challan_template_id' => ['nullable', 'exists:challan_templates,id'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'featured_image' => ['nullable', 'image', 'max:5120'],
            'district_quotas' => ['nullable', 'array'],
            'district_quotas.*' => ['nullable', 'integer', 'min:0'],
            'use_custom_psid' => ['nullable'],
            'psid_one_link_prefix' => ['required_if:use_custom_psid,1', 'nullable', 'string', 'size:6', 'regex:/^\d{6}$/'],
            'psid_department_code' => ['required_if:use_custom_psid,1', 'nullable', 'string', 'size:2', 'regex:/^\d{2}$/'],
            'psid_department_service_no' => ['required_if:use_custom_psid,1', 'nullable', 'string', 'size:2', 'regex:/^\d{2}$/'],
            'psid_district_code' => ['nullable', 'string', 'size:2', 'regex:/^\d{2}$/'],
            'psid_rco' => ['required_if:use_custom_psid,1', 'nullable', 'string', 'max:20'],
            'psid_head' => ['required_if:use_custom_psid,1', 'nullable', 'string', 'max:20'],
        ]);
    }

    private function syncPsidConfig(Scheme $scheme, Request $request): void
    {
        if (! $request->boolean('use_custom_psid')) {
            if ($request->has('psid_settings_present')) {
                PsidConfig::where('scheme_id', $scheme->id)->delete();
            }

            return;
        }

        $global = PsidConfig::getActive();

        PsidConfig::updateOrCreate(
            ['scheme_id' => $scheme->id],
            [
                'one_link_prefix' => $request->input('psid_one_link_prefix') ?: ($global->one_link_prefix ?? '101530'),
                'department_code' => str_pad((string) ($request->input('psid_department_code') ?: ($global->department_code ?? '09')), 2, '0', STR_PAD_LEFT),
                'department_service_no' => str_pad((string) ($request->input('psid_department_service_no') ?: ($global->department_service_no ?? '01')), 2, '0', STR_PAD_LEFT),
                'district_code' => $request->input('psid_district_code') ?: ($global->district_code ?? '01'),
                'rco' => $request->input('psid_rco') ?: ($global->rco ?? 'GL-1618'),
                'head' => $request->input('psid_head') ?: ($global->head ?? 'C03870'),
                'is_active' => true,
            ]
        );
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
