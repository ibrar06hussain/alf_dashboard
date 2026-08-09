<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\District;
use App\Models\Scheme;
use App\Models\SchemeInterest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchemeController extends Controller
{
    public function index(Request $request): View
    {
        $query = Scheme::query()
            ->with(['department', 'districts'])
            ->where('status', 'active');

        if ($request->filled('department')) {
            $query->whereHas('department', fn ($q) => $q->where('slug', $request->string('department')));
        }

        if ($request->filled('type') && in_array($request->string('type')->toString(), ['running', 'proposed'], true)) {
            $query->where('type', $request->string('type'));
        }

        if ($request->filled('district')) {
            $districtId = (int) $request->input('district');
            $query->where(function ($q) use ($districtId) {
                $q->where('scope', 'gb')
                    ->orWhereHas('districts', fn ($dq) => $dq->where('districts.id', $districtId));
            });
        }

        if ($request->string('type')->toString() === 'proposed') {
            $query->orderBy('display_order')->orderByDesc('id');
        } else {
            $query->latest();
        }

        $schemes = $query->paginate(12)->withQueryString();

        $departments = Department::where('is_active', true)->orderBy('display_order')->get();
        $districts = District::where('is_active', true)->orderBy('name')->get();

        return view('public.schemes.index', compact('schemes', 'departments', 'districts'));
    }

    public function show(string $slug): View
    {
        $scheme = Scheme::query()
            ->with(['department', 'districts', 'publishedForm.fields', 'publishedForm.documents'])
            ->where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();

        $districts = District::where('is_active', true)->orderBy('name')->get();

        return view('public.schemes.show', compact('scheme', 'districts'));
    }

    public function expressInterest(Request $request, string $slug): RedirectResponse
    {
        $scheme = Scheme::query()
            ->where('slug', $slug)
            ->where('status', 'active')
            ->where('type', 'proposed')
            ->firstOrFail();

        $request->merge([
            'cnic' => preg_replace('/\D+/', '', (string) $request->input('cnic')),
            'phone' => preg_replace('/\D+/', '', (string) $request->input('phone')),
        ]);

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'cnic' => ['required', 'regex:/^\d{13}$/'],
            'phone' => ['required', 'string', 'max:20'],
            'district_id' => ['nullable', 'exists:districts,id'],
            'email' => ['nullable', 'email', 'max:150'],
            'message' => ['nullable', 'string', 'max:1000'],
        ], [
            'cnic.regex' => 'CNIC must be exactly 13 digits without dashes.',
        ]);

        $data['scheme_id'] = $scheme->id;

        SchemeInterest::updateOrCreate(
            ['scheme_id' => $scheme->id, 'cnic' => $data['cnic']],
            $data
        );

        return back()->with('success', 'Thank you. Your interest has been recorded. You will be notified when this scheme opens for applications.');
    }
}
