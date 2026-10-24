<div class="table-responsive">
    <table class="table table-hover align-middle mb-0" id="beneficiaries-table">
        <thead class="table-light small">
            <tr>
                <th>App No</th>
                <th>Applicant Name</th>
                <th>Father / Husband</th>
                <th>CNIC</th>
                <th>Phone</th>
                <th>District</th>
                <th>Tehsil</th>
                <th>Village</th>
                <th class="text-end">Land (Kanals)</th>
                <th class="text-end">Last Yr Cultiv</th>
                <th class="text-end">Req Bags</th>
                <th class="text-end">Appr Bags</th>
                <th class="text-center">Survey Status</th>
                <th class="text-center">Decision</th>
                <th>Reviewing Officer</th>
                <th class="text-center">Action</th>
            </tr>
        </thead>
        <tbody class="small" id="beneficiaries-table-body">
            @forelse($beneficiaries as $ben)
                <tr>
                    <td>
                        <a href="{{ route('admin.applications.show', $ben->id) }}" class="fw-bold text-decoration-none text-primary">
                            {{ $ben->application_no }}
                        </a>
                    </td>
                    <td class="fw-semibold text-dark">{{ $ben->applicant_name }}</td>
                    <td>{{ $ben->father_name }}</td>
                    <td><code>{{ $ben->cnic }}</code></td>
                    <td><code>{{ $ben->phone }}</code></td>
                    <td>{{ $ben->district_name }}</td>
                    <td>{{ $ben->tehsil }}</td>
                    <td>{{ $ben->village }}</td>
                    <td class="text-end fw-semibold">
                        {{ $ben->land_kanals !== null ? number_format($ben->land_kanals, 1) : '—' }}
                        @if($ben->land_acres !== null)
                            <span class="text-muted" style="font-size: 0.72rem;">({{ $ben->land_acres }} ac)</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @if($ben->cultivated_kanals_last_year !== null)
                            {{ number_format($ben->cultivated_kanals_last_year, 1) }} k
                        @else
                            <span class="text-muted">Not yet captured</span>
                        @endif
                    </td>
                    <td class="text-end fw-bold">{{ $ben->requested_bags }}</td>
                    <td class="text-end fw-bold {{ $ben->approved_bags > 0 ? 'text-primary' : 'text-muted' }}">
                        {{ $ben->approved_bags }}
                        @if($ben->approved_bags > 0 && $ben->rate_per_bag !== null)
                            <div class="text-muted fw-normal" style="font-size: 0.70rem;" title="Delivered Rate: PKR {{ number_format($ben->rate_per_bag, 0) }} / bag (Transport: PKR {{ number_format($ben->transport_cost, 0) }})">
                                PKR {{ number_format($ben->approved_bags * $ben->rate_per_bag, 0) }}
                            </div>
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="badge {{ str_contains($ben->survey_status, 'Verified') ? 'bg-success' : 'bg-warning text-dark' }}">
                            {{ $ben->survey_status }}
                        </span>
                    </td>
                    <td class="text-center">
                        @php
                            $statusBadge = match($ben->status) {
                                'pending' => 'bg-warning text-dark',
                                'district_verified' => 'bg-info text-white',
                                'directorate_approved' => 'bg-primary',
                                'disbursed' => 'bg-success',
                                'rejected' => 'bg-danger',
                                default => 'bg-secondary',
                            };
                        @endphp
                        <span class="badge {{ $statusBadge }}">{{ $ben->status_label }}</span>
                    </td>
                    <td class="text-muted">{{ $ben->reviewing_officer }}</td>
                    <td class="text-center">
                        <a href="{{ route('admin.applications.show', $ben->id) }}" class="btn btn-outline-primary btn-sm py-0 px-2" title="Review Application">
                            Review
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="16" class="text-center text-muted py-4">
                        <i class="fa-solid fa-folder-open fs-3 d-block mb-2"></i>
                        No applications found matching the selected filters.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="card-footer bg-light d-flex flex-wrap justify-content-between align-items-center py-2 px-3 gap-2" id="grid-pagination-container">
    <div class="small text-muted" id="grid-pagination-info">
        @if($beneficiaries->total() > 0)
            @if($beneficiaries->lastPage() > 1)
                Showing <strong>{{ $beneficiaries->firstItem() }}</strong> to <strong>{{ $beneficiaries->lastItem() }}</strong> of <strong>{{ number_format($beneficiaries->total()) }}</strong> records
            @else
                Showing all <strong>{{ number_format($beneficiaries->total()) }}</strong> records in one page
            @endif
        @else
            No matching records found
        @endif
    </div>
    <div class="wheat-grid-pagination-links">
        @if($beneficiaries->hasPages())
            {{ $beneficiaries->links() }}
        @endif
    </div>
</div>
