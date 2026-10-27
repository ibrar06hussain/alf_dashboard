<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\ChallanTemplate;
use App\Models\District;
use App\Models\Scheme;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ChallanTemplateController extends Controller
{
    public function index(): View
    {
        $templates = ChallanTemplate::with('schemes')->latest()->paginate(15);
        $totalSchemes = Scheme::count();

        return view('admin.challan-templates.index', compact('templates', 'totalSchemes'));
    }

    public function create(): View
    {
        $template = new ChallanTemplate([
            'department_name' => 'Agriculture LiveStock and Fishries Department Gilgit Baltistan',
            'sub_title' => 'Government of Gilgit-Baltistan',
            'challan_title' => 'FEE DEPOSIT CHALLAN',
            'bank_name' => 'National Bank Of Pakistan',
            'account_title' => 'Agriculture LiveStock and Fishries Department Gilgit Baltistan',
            'account_no' => '4256260305',
            'account_head' => 'Application Processing Fee(C03870)',
            'validity_days' => 10,
            'directions' => 'This challan is valid for 10 days after the challan is generated. This fee doesnot include transport charges from district to village.',
            'fee_mode' => 'seed_configured',
            'govt_share_pct' => 50.00,
            'farmer_share_pct' => 50.00,
            'show_breakdown' => true,
            'is_active' => true,
            'is_default' => false,
        ]);

        $schemes = Scheme::orderBy('title')->get();
        $assignedSchemeIds = [];

        return view('admin.challan-templates.form', compact('template', 'schemes', 'assignedSchemeIds'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if ($request->boolean('is_default')) {
            ChallanTemplate::where('is_default', true)->update(['is_default' => false]);
            $data['is_default'] = true;
        } else {
            $data['is_default'] = false;
        }

        $data['show_breakdown'] = $request->boolean('show_breakdown', true);
        $data['is_active'] = $request->boolean('is_active', true);

        $template = ChallanTemplate::create($data);

        // Sync assigned schemes
        $assignedSchemes = $request->input('schemes', []);
        Scheme::whereIn('id', $assignedSchemes)->update(['challan_template_id' => $template->id]);

        return redirect()->route('admin.challan-templates.index')
            ->with('success', "Challan template '{$template->name}' created and assigned successfully.");
    }

    public function edit(ChallanTemplate $challan_template): View
    {
        $template = $challan_template;
        $schemes = Scheme::orderBy('title')->get();
        $assignedSchemeIds = $template->schemes()->pluck('id')->toArray();

        return view('admin.challan-templates.form', compact('template', 'schemes', 'assignedSchemeIds'));
    }

    public function update(Request $request, ChallanTemplate $challan_template): RedirectResponse
    {
        $data = $this->validated($request, $challan_template->id);

        if ($request->boolean('is_default')) {
            ChallanTemplate::where('id', '!=', $challan_template->id)->update(['is_default' => false]);
            $data['is_default'] = true;
        } else {
            $data['is_default'] = false;
        }

        $data['show_breakdown'] = $request->boolean('show_breakdown', true);
        $data['is_active'] = $request->boolean('is_active', true);

        $challan_template->update($data);

        // Sync assigned schemes: unlink deselected ones, link newly selected ones
        $selectedSchemes = $request->input('schemes', []);
        Scheme::where('challan_template_id', $challan_template->id)
            ->whereNotIn('id', $selectedSchemes)
            ->update(['challan_template_id' => null]);

        if (! empty($selectedSchemes)) {
            Scheme::whereIn('id', $selectedSchemes)->update(['challan_template_id' => $challan_template->id]);
        }

        return redirect()->route('admin.challan-templates.index')
            ->with('success', "Challan template '{$challan_template->name}' updated successfully.");
    }

    public function destroy(ChallanTemplate $challan_template): RedirectResponse
    {
        if ($challan_template->is_default) {
            return back()->with('error', 'The default challan template cannot be deleted. Please set another template as default first.');
        }

        Scheme::where('challan_template_id', $challan_template->id)->update(['challan_template_id' => null]);
        $name = $challan_template->name;
        $challan_template->delete();

        return redirect()->route('admin.challan-templates.index')
            ->with('success', "Challan template '{$name}' deleted successfully.");
    }

    public function preview(ChallanTemplate $challan_template): Response
    {
        $district = District::first() ?? new District(['name' => 'Gilgit', 'code' => 'GLT']);
        $applicant = new Applicant([
            'full_name' => 'Kamal Uddin',
            'cnic' => '71501-8161135-5',
            'phone' => '0345-8161135',
            'tehsil' => 'Gilgit',
        ]);

        $scheme = $challan_template->schemes()->first() ?? Scheme::first() ?? new Scheme([
            'title' => 'Certified Wheat Seed Subsidy Scheme',
            'requires_challan' => true,
        ]);

        $sampleApp = new Application([
            'id' => 87,
            'application_no' => 'ALF-20261027-00087',
            'challan_no' => 'CHL-2026-00087',
            'challan_amount' => 13000.00,
            'demanded_quantity' => 4.0,
            'approved_quantity' => 4.0,
            'allocated_quantity' => 4,
            'challan_issued_at' => now(),
            'challan_due_date' => now()->addDays($challan_template->validity_days ?: 10)->toDateString(),
        ]);
        $sampleApp->setRelation('scheme', $scheme);
        $sampleApp->setRelation('applicant', $applicant);
        $sampleApp->setRelation('district', $district);

        $breakdown = [
            'template' => $challan_template,
            'cost_of_1_bag' => 6500.0,
            'approved_bags' => 4.0,
            'total_bill' => 26000,
            'govt_share_pct' => (float) $challan_template->govt_share_pct,
            'farmer_share_pct' => (float) $challan_template->farmer_share_pct,
            'govt_share' => (int) ceil(26000 * ($challan_template->govt_share_pct / 100)),
            'final_bill' => (int) ceil(26000 * ($challan_template->farmer_share_pct / 100)),
            'amount_digits' => 'Rs. ' . number_format((int) ceil(26000 * ($challan_template->farmer_share_pct / 100))),
            'amount_words' => \App\Support\NumberToWords::toCurrencyWords((int) ceil(26000 * ($challan_template->farmer_share_pct / 100))),
        ];

        $pdf = Pdf::loadView('public.applications.challan-pdf', [
            'application' => $sampleApp,
            'scheme' => $scheme,
            'applicant' => $applicant,
            'district' => $district,
            'template' => $challan_template,
            'breakdown' => $breakdown,
        ])->setPaper('a4', 'landscape')
          ->setOption([
              'isHtml5ParserEnabled' => true,
              'isRemoteEnabled' => false,
              'dpi' => 96,
              'defaultFont' => 'DejaVu Sans',
          ]);

        return $pdf->stream("Preview-{$challan_template->name}.pdf");
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'department_name' => ['required', 'string', 'max:255'],
            'sub_title' => ['nullable', 'string', 'max:255'],
            'challan_title' => ['required', 'string', 'max:255'],
            'bank_name' => ['required', 'string', 'max:255'],
            'account_title' => ['required', 'string', 'max:255'],
            'account_no' => ['required', 'string', 'max:100'],
            'account_head' => ['required', 'string', 'max:255'],
            'validity_days' => ['required', 'integer', 'min:1', 'max:365'],
            'directions' => ['required', 'string', 'max:2000'],
            'fee_mode' => ['required', 'in:seed_configured,fixed_unit,fixed_total,free'],
            'govt_share_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'farmer_share_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'schemes' => ['nullable', 'array'],
            'schemes.*' => ['exists:schemes,id'],
        ]);
    }
}
