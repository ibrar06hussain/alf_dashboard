@php
    $selectedRoles = old('roles');
    if ($selectedRoles === null) {
        if (old('role')) {
            $selectedRoles = [old('role')];
        } elseif (isset($userRoles)) {
            $selectedRoles = $userRoles;
        } elseif (isset($user)) {
            $selectedRoles = $user->getRoleNames()->all();
        } else {
            $selectedRoles = [];
        }
    }
    if (!is_array($selectedRoles)) {
        $selectedRoles = [$selectedRoles];
    }
    $primaryRole = $selectedRoles[0] ?? '';

    $selectedDirectPermissions = old('permissions');
    if ($selectedDirectPermissions === null) {
        if (isset($userDirectPermissions)) {
            $selectedDirectPermissions = $userDirectPermissions;
        } elseif (isset($user)) {
            $selectedDirectPermissions = $user->getDirectPermissions()->pluck('name')->all();
        } else {
            $selectedDirectPermissions = [];
        }
    }
    if (!is_array($selectedDirectPermissions)) {
        $selectedDirectPermissions = [$selectedDirectPermissions];
    }

    $selectedDeptIds = old('department_ids', isset($userAuthorizedDepartmentIds) ? $userAuthorizedDepartmentIds : (isset($user) && $user->department_id ? [$user->department_id] : []));
    if (!is_array($selectedDeptIds)) {
        $selectedDeptIds = [$selectedDeptIds];
    }
    $selectedDistIds = old('district_ids', isset($userAuthorizedDistrictIds) ? $userAuthorizedDistrictIds : (isset($user) && $user->district_id ? [$user->district_id] : []));
    if (!is_array($selectedDistIds)) {
        $selectedDistIds = [$selectedDistIds];
    }
    $selectedDivId = old('division_id', isset($userAuthorizedDivisionIds) && count($userAuthorizedDivisionIds) === 1 ? $userAuthorizedDivisionIds[0] : null);

    $roleMetadata = [
        'Super Admin' => ['icon' => 'fa-crown text-danger', 'desc' => 'Technical configuration, system operations, and audit logs.', 'badge' => 'bg-danger-subtle text-danger'],
        'Secretary' => ['icon' => 'fa-landmark text-purple', 'desc' => 'Executive oversight, read-only analytics, and macro reports.', 'badge' => 'bg-secondary-subtle text-dark'],
        'Director Agriculture' => ['icon' => 'fa-wheat-awn text-success', 'desc' => 'Agriculture Directorate head: scheme management, approvals & quotas.', 'badge' => 'bg-success-subtle text-success'],
        'Director Livestock' => ['icon' => 'fa-paw text-warning', 'desc' => 'Livestock Directorate head: scheme management, approvals & quotas.', 'badge' => 'bg-warning-subtle text-dark'],
        'Director Fisheries' => ['icon' => 'fa-fish text-info', 'desc' => 'Fisheries Directorate head: scheme management, approvals & quotas.', 'badge' => 'bg-info-subtle text-info'],
        'Division Director' => ['icon' => 'fa-sitemap text-primary', 'desc' => 'Multi-district leadership overseeing all departments across a division.', 'badge' => 'bg-primary-subtle text-primary'],
        'District Deputy Director' => ['icon' => 'fa-user-tie text-primary', 'desc' => 'District executive: verifies applications, manages stock & centers.', 'badge' => 'bg-primary-subtle text-primary'],
        'District Officer' => ['icon' => 'fa-building-user text-secondary', 'desc' => 'District desk officer: processes applications & verifies citizen data.', 'badge' => 'bg-light text-secondary'],
        'Accounts Officer' => ['icon' => 'fa-receipt text-success', 'desc' => 'Finance & PSID billing officer: verifies challans & GBPay transactions.', 'badge' => 'bg-success-subtle text-success'],
        'Distribution Officer' => ['icon' => 'fa-truck-ramp-box text-info', 'desc' => 'Logistics officer: manages warehouse stock in, receipts & disbursements.', 'badge' => 'bg-info-subtle text-info'],
        'Field Officer' => ['icon' => 'fa-location-dot text-danger', 'desc' => 'On-ground officer: conducts physical land inspections & geo-tagged surveys.', 'badge' => 'bg-danger-subtle text-danger'],
    ];

    $permissionGroups = [
        'Dashboards & Analytics' => [
            'icon' => 'fa-solid fa-chart-line text-primary',
            'desc' => 'Main dashboards and high-level analytical overviews',
            'items' => [
                'pages.dashboard' => ['label' => 'Main Dashboard', 'desc' => 'Overview statistics, quick metrics and pending queue shortcuts.'],
                'pages.cm_dashboard' => ['label' => 'CM Analytics Dashboard', 'desc' => 'Chief Minister executive analytics, district comparisons and export.'],
                'pages.wheat_mis' => ['label' => 'Wheat Seed MIS', 'desc' => 'Dedicated wheat seed MIS dashboard, demand vs supply, GIS mapping and rate setting.'],
                'pages.stats' => ['label' => 'Department Stats', 'desc' => 'Departmental key performance indicators and statistics management.'],
            ],
        ],
        'Applications & Citizen Queue' => [
            'icon' => 'fa-solid fa-folder-open text-info',
            'desc' => 'Application processing, status updates and export tools',
            'items' => [
                'pages.applications' => ['label' => 'Applications Queue Page', 'desc' => 'Access application list, status filtering and detail pages.'],
                'applications.view' => ['label' => 'View Application Details', 'desc' => 'Access applicant submitted details, land records and documents.'],
                'applications.verify_district' => ['label' => 'District Verification', 'desc' => 'Perform district-level desk check and forward to Directorate.'],
                'applications.approve_directorate' => ['label' => 'Directorate Approval', 'desc' => 'Final Directorate approval for subsidy and challan issuance.'],
                'applications.reject' => ['label' => 'Reject Applications', 'desc' => 'Reject ineligible applications with formal justification.'],
                'applications.assign_field_officer' => ['label' => 'Assign Field Officers', 'desc' => 'Dispatch applications to field officers for inspection.'],
                'beneficiaries.export_masked' => ['label' => 'Export Masked List', 'desc' => 'Export beneficiary lists with masked CNIC and phone.'],
                'beneficiaries.export_unmasked' => ['label' => 'Export Full PII', 'desc' => 'Export unmasked citizen details (High clearance only).'],
                'pages.interests' => ['label' => 'Citizen Interests Page', 'desc' => 'View expressions of interest submitted by citizens.'],
            ],
        ],
        'Finance & GBPay (PSID)' => [
            'icon' => 'fa-solid fa-money-check-dollar text-success',
            'desc' => 'Billing, challan payments and financial gateway monitoring',
            'items' => [
                'pages.gbpay' => ['label' => 'GBPay Gateway & PSID (Admin Role Only)', 'desc' => 'Admin-only gateway settings, API credentials, and PSID logs.'],
                'payments.verify' => ['label' => 'Verify Payments', 'desc' => 'Verify manual bank deposit challans or online PSID settlements.'],
            ],
        ],
        'Schemes & Form Configuration' => [
            'icon' => 'fa-solid fa-file-signature text-warning',
            'desc' => 'Citizen scheme creation, dynamic forms and payment challans',
            'items' => [
                'pages.schemes' => ['label' => 'Schemes Management', 'desc' => 'Create and configure citizen subsidy schemes.'],
                'pages.form_builder' => ['label' => 'Dynamic Form Builder', 'desc' => 'Build dynamic application forms and input criteria.'],
                'pages.challan_templates' => ['label' => 'Challan Templates', 'desc' => 'Design bank payment challan templates.'],
            ],
        ],
        'Stock & Inventory Logistics' => [
            'icon' => 'fa-solid fa-boxes-stacked text-primary',
            'desc' => 'Warehouse receipts, transfers, damages, and distribution',
            'items' => [
                'pages.stock' => ['label' => 'Stock Overview Page', 'desc' => 'Inventory levels, receipts, and demand balance.'],
                'stock.manage' => ['label' => 'Manage Inventory', 'desc' => 'Record stock in receipts, damages, and transfers.'],
                'stock.transfer' => ['label' => 'Stock Transfers', 'desc' => 'Transfer stock between district warehouses and collection centers.'],
                'stock.record_damage' => ['label' => 'Log Damages / Wastage', 'desc' => 'Record spoiled or damaged seed bags.'],
                'distribution.allocate_stock' => ['label' => 'Allocate Stock', 'desc' => 'Allocate and approve delivery to approved beneficiaries.'],
                'distribution.confirm_delivery' => ['label' => 'Confirm Delivery', 'desc' => 'Record confirmation of physical goods handover.'],
                'collection_centers.manage' => ['label' => 'Collection Centers', 'desc' => 'Add and manage local distribution points and warehouses.'],
            ],
        ],
        'Field Surveys' => [
            'icon' => 'fa-solid fa-location-crosshairs text-danger',
            'desc' => 'Physical farm inspection, land survey and geo-tagging',
            'items' => [
                'surveys.view_assigned' => ['label' => 'View Assigned Surveys', 'desc' => 'Access assigned field inspection queue.'],
                'surveys.submit_verification' => ['label' => 'Submit Verification', 'desc' => 'Submit on-site GPS coordinates, land photos, and remarks.'],
            ],
        ],
        'Directories & CMS' => [
            'icon' => 'fa-solid fa-globe text-secondary',
            'desc' => 'Farmer cooperatives, SMS gateway and portal content',
            'items' => [
                'pages.organizations' => ['label' => 'Farmer Organizations', 'desc' => 'View and manage registered farmer cooperatives.'],
                'pages.cms' => ['label' => 'Portal CMS Content', 'desc' => 'Manage homepage carousel, media gallery, and site info.'],
                'pages.sms' => ['label' => 'SMS Gateway (Admin Role Only)', 'desc' => 'Admin-only SMS gateway configuration, balances, and template settings.'],
            ],
        ],
        'Administration & Security' => [
            'icon' => 'fa-solid fa-user-shield text-dark',
            'desc' => 'Administrative user accounts, roles and audit logs',
            'items' => [
                'pages.users' => ['label' => 'User Management', 'desc' => 'Manage administrative accounts, role assignments, and jurisdictions.'],
                'pages.roles' => ['label' => 'Roles & Matrix', 'desc' => 'View Spatie role matrix and permission bindings.'],
                'pages.audit_logs' => ['label' => 'Access Audit Logs', 'desc' => 'View immutable audit trail of user logins and actions.'],
                'pages.departments' => ['label' => 'Departments Directory', 'desc' => 'Manage administrative government departments.'],
                'pages.districts' => ['label' => 'Districts Directory', 'desc' => 'Manage district configurations and codes.'],
            ],
        ],
    ];

    $rcoCodeMap = [
        'Gilgit' => 'GL-1618',
        'Ghizer' => 'GZ-1050',
        'Hunza' => 'HN-1006',
        'Nagar' => 'NG-1002',
        'Diamer' => 'DM-1035',
        'Astore' => 'AT-1042',
        'Skardu' => 'SD-1066',
        'Ghanche' => 'GN-1042',
        'Shigar' => 'SS-1008',
        'Kharmang' => 'RG-1002',
    ];

    $divisionRcoMap = [
        'Gilgit Division' => 'GR GL-1617',
        'Diamer Division' => 'DM DM-1015',
        'Skardu Division' => 'SD SD-1811',
    ];
@endphp

<div class="row g-3">
    <!-- User Account Core Information -->
    <div class="col-md-6">
        <label class="form-label fw-bold">Full Name <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
            <input type="text" name="name" class="form-control" value="{{ old('name', $user->name ?? '') }}" placeholder="e.g. Director Agriculture Gilgit Division" required>
        </div>
    </div>

    <div class="col-md-6">
        <label class="form-label fw-bold">Email Address <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
            <input type="email" name="email" class="form-control" value="{{ old('email', $user->email ?? '') }}" placeholder="official@alf.gb.gov.pk" required>
        </div>
    </div>

    <div class="col-md-6">
        <label class="form-label fw-bold">Password {{ isset($user) ? '(leave blank to keep current)' : '' }} <span class="text-danger">{{ isset($user) ? '' : '*' }}</span></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
            <input type="password" name="password" class="form-control" {{ isset($user) ? '' : 'required' }} placeholder="••••••••">
        </div>
    </div>

    <div class="col-md-6">
        <label class="form-label fw-bold">Confirm Password <span class="text-danger">{{ isset($user) ? '' : '*' }}</span></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-lock-check"></i></span>
            <input type="password" name="password_confirmation" class="form-control" {{ isset($user) ? '' : 'required' }} placeholder="••••••••">
        </div>
    </div>

    <!-- Hidden fallback for single role submissions -->
    <input type="hidden" name="role" id="primary_role_fallback" value="{{ $primaryRole }}">

    <!-- Role Selection: Multiple Roles Option -->
    <div class="col-12 mt-4">
        <div class="card border border-primary shadow-sm bg-light-subtle">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-2 flex-wrap gap-2">
                <div>
                    <span class="fw-bold text-dark fs-6">
                        <i class="fa-solid fa-user-shield text-primary me-2"></i> Assigned Role(s)
                    </span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-2" id="selected-roles-count">
                        {{ count($selectedRoles) }} Role(s) Selected
                    </span>
                </div>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary py-0 px-2" id="btn-clear-roles">Clear All</button>
                </div>
            </div>
            <div class="card-body py-3">
                <p class="small text-muted mb-3">
                    <i class="fa-solid fa-circle-info text-primary me-1"></i>
                    Admin can assign <strong>multiple roles</strong> to a single user (e.g. <em>District Deputy Director</em> + <em>Accounts Officer</em>, or <em>Director Agriculture</em> + <em>Wheat Seed MIS Operator</em>). The user will inherit all capabilities of selected roles.
                </p>

                <div class="row g-2">
                    @foreach($roles as $roleItem)
                        @php
                            $rName = is_object($roleItem) ? $roleItem->name : (string) $roleItem;
                            $meta = $roleMetadata[$rName] ?? [
                                'icon' => 'fa-user text-secondary',
                                'desc' => 'Administrative role with standard system permissions.',
                                'badge' => 'bg-secondary-subtle text-dark'
                            ];
                            $isRoleChecked = in_array($rName, $selectedRoles, true);
                        @endphp
                        <div class="col-lg-4 col-md-6">
                            <label class="card h-100 p-2 border cursor-pointer role-card {{ $isRoleChecked ? 'border-primary bg-primary-subtle shadow-sm' : 'border-secondary-subtle bg-white' }}" style="cursor: pointer;">
                                <div class="d-flex align-items-start">
                                    <input type="checkbox" name="roles[]" value="{{ $rName }}"
                                        class="form-check-input role-checkbox me-2 mt-1"
                                        data-role-name="{{ $rName }}"
                                        @checked($isRoleChecked)>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fw-bold text-dark">
                                                <i class="fa-solid {{ $meta['icon'] }} me-1"></i> {{ $rName }}
                                            </span>
                                            @if($isRoleChecked && $rName === $primaryRole)
                                                <span class="badge bg-primary py-0 px-1 small" style="font-size: 0.68rem;">Primary</span>
                                            @endif
                                        </div>
                                        <small class="text-muted d-block mt-1" style="font-size: 0.78rem; line-height: 1.25;">
                                            {{ $meta['desc'] }}
                                        </small>
                                    </div>
                                </div>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Page-Level & Custom Feature Permissions Section -->
    <div class="col-12 mt-4">
        <div class="card border border-info shadow-sm bg-light-subtle">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-2 flex-wrap gap-2">
                <div>
                    <span class="fw-bold text-dark fs-6">
                        <i class="fa-solid fa-list-check text-info me-2"></i> Page-Level &amp; Feature Permission Assignment
                    </span>
                    <span class="badge bg-info-subtle text-info border border-info-subtle ms-2" id="selected-permissions-count">
                        {{ count($selectedDirectPermissions) }} Custom Permission(s) Selected
                    </span>
                </div>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-sm btn-outline-info py-0 px-2" id="btn-select-all-permissions">Select All</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" id="btn-clear-all-permissions">Clear All</button>
                </div>
            </div>
            <div class="card-body py-3">
                <p class="small text-muted mb-3">
                    <i class="fa-solid fa-sliders text-info me-1"></i>
                    Admin can explicitly choose which <strong>pages, modules, or features</strong> to allow to each user. When checked, the corresponding sidebar menu and routes are unlocked for this user regardless of their base role.
                </p>

                <div class="row g-3">
                    @foreach($permissionGroups as $groupTitle => $group)
                        <div class="col-lg-6 col-12">
                            <div class="card h-100 border shadow-none bg-white">
                                <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <i class="{{ $group['icon'] }} me-2"></i>
                                        <strong class="text-dark small">{{ $groupTitle }}</strong>
                                    </div>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-xs btn-link p-0 text-decoration-none btn-toggle-group-perms me-2" data-group="{{ Str::slug($groupTitle) }}">
                                            Select All
                                        </button>
                                        <button type="button" class="btn btn-xs btn-link p-0 text-decoration-none text-muted btn-clear-group-perms" data-group="{{ Str::slug($groupTitle) }}">
                                            Clear
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body p-2">
                                    <div class="d-flex flex-column gap-2" data-group-container="{{ Str::slug($groupTitle) }}">
                                        @foreach($group['items'] as $permKey => $permMeta)
                                            @php
                                                $isPermChecked = in_array($permKey, $selectedDirectPermissions, true);
                                            @endphp
                                            <label class="p-2 border rounded cursor-pointer perm-item {{ $isPermChecked ? 'border-info bg-info-subtle' : 'border-secondary-subtle bg-white' }}" style="cursor: pointer; margin-bottom: 0;">
                                                <div class="d-flex align-items-start">
                                                    <input type="checkbox" name="permissions[]" value="{{ $permKey }}"
                                                        class="form-check-input perm-checkbox me-2 mt-1"
                                                        data-group="{{ Str::slug($groupTitle) }}"
                                                        @checked($isPermChecked)>
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span class="fw-semibold text-dark small">{{ $permMeta['label'] }}</span>
                                                            <code class="text-muted" style="font-size: 0.68rem;">{{ $permKey }}</code>
                                                        </div>
                                                        <div class="text-muted" style="font-size: 0.74rem; line-height: 1.2;">
                                                            {{ $permMeta['desc'] }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Department Scope Section -->
    <div class="col-12 mt-4">
        <div class="card border border-primary-subtle shadow-none bg-light-subtle">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                <span class="fw-bold text-dark">
                    <i class="fa-solid fa-building-columns text-primary me-1"></i> Department Jurisdiction
                </span>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary py-0 px-2" id="btn-select-all-depts">Select All</button>
                    <button type="button" class="btn btn-outline-secondary py-0 px-2" id="btn-clear-depts">Clear</button>
                </div>
            </div>
            <div class="card-body py-3">
                <div class="row g-2">
                    @foreach($departments as $dept)
                        @php
                            $isDeptChecked = in_array((int) $dept->id, array_map('intval', $selectedDeptIds), true);
                            $deptIcon = match($dept->slug) {
                                'agriculture' => 'fa-wheat-awn text-success',
                                'livestock' => 'fa-paw text-warning',
                                'fisheries' => 'fa-fish text-info',
                                default => 'fa-layer-group text-primary',
                            };
                        @endphp
                        <div class="col-md-4">
                            <label class="card h-100 p-2 border cursor-pointer dept-card {{ $isDeptChecked ? 'border-primary bg-primary-subtle' : 'border-secondary-subtle bg-white' }}" style="cursor: pointer;">
                                <div class="d-flex align-items-center">
                                    <input type="checkbox" name="department_ids[]" value="{{ $dept->id }}" 
                                        class="form-check-input dept-checkbox me-2 mt-0" 
                                        data-dept-slug="{{ $dept->slug }}"
                                        @checked($isDeptChecked)>
                                    <div>
                                        <div class="fw-semibold text-dark">
                                            <i class="fa-solid {{ $deptIcon }} me-1"></i> {{ $dept->name }}
                                        </div>
                                        <small class="text-muted d-block">{{ ucfirst($dept->slug) }} Directorate</small>
                                    </div>
                                </div>
                            </label>
                        </div>
                    @endforeach
                </div>
                <div class="small text-muted mt-2">
                    <i class="fa-solid fa-info-circle me-1"></i> Users will only see schemes, applications, and statistics belonging to the selected department(s). If no department is selected for a general Division Director, all departments will be accessible.
                </div>
            </div>
        </div>
    </div>

    <!-- Territorial Scope & Multiple Districts Section -->
    <div class="col-12 mt-3">
        <div class="card border border-success-subtle shadow-none bg-light-subtle">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-2 flex-wrap gap-2">
                <span class="fw-bold text-dark">
                    <i class="fa-solid fa-map-location-dot text-success me-1"></i> Territorial Jurisdiction &amp; District Scope
                </span>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" id="selected-districts-count">
                        {{ count($selectedDistIds) }} Districts Selected
                    </span>
                    <button type="button" class="btn btn-sm btn-outline-success py-0 px-2" id="btn-select-all-districts">
                        Select Province (All 10)
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" id="btn-clear-districts">
                        Clear
                    </button>
                </div>
            </div>

            <div class="card-body py-3">
                <!-- Quick Division Preset Selector -->
                <div class="row align-items-center mb-3 g-2 bg-white p-2 rounded border">
                    <div class="col-md-3">
                        <label class="form-label mb-0 fw-bold small text-muted">
                            <i class="fa-solid fa-bolt text-warning me-1"></i> Quick Division Select:
                        </label>
                    </div>
                    <div class="col-md-9">
                        <select id="division_preset_selector" name="division_id" class="form-select form-select-sm">
                            <option value="">Custom Districts Selection...</option>
                            @foreach($divisions as $div)
                                @php
                                    $divRco = $divisionRcoMap[$div->name] ?? '';
                                    $distCount = $div->districts->count();
                                @endphp
                                <option value="{{ $div->id }}" @selected((int)$selectedDivId === (int)$div->id)>
                                    {{ $div->name }} ({{ $distCount }} Districts{{ $divRco ? ' · ' . $divRco : '' }})
                                </option>
                            @endforeach
                            <option value="all">Entire Province · All 10 Districts (Gilgit-Baltistan)</option>
                        </select>
                    </div>
                </div>

                <!-- Districts Grouped by Division -->
                <div class="row g-3">
                    @foreach($divisions as $division)
                        @php
                            $divDistricts = $division->districts;
                            $divRcoTag = $divisionRcoMap[$division->name] ?? '';
                        @endphp
                        <div class="col-lg-4 col-md-6">
                            <div class="card h-100 border shadow-none division-box" data-division-id="{{ $division->id }}">
                                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="fw-bold text-dark">{{ $division->name }}</span>
                                        @if($divRcoTag)
                                            <span class="badge bg-light text-dark border ms-1 font-monospace small">{{ $divRcoTag }}</span>
                                        @endif
                                    </div>
                                    <button type="button" class="btn btn-xs btn-link p-0 text-decoration-none btn-toggle-div-districts" data-division-id="{{ $division->id }}">
                                        Toggle All
                                    </button>
                                </div>
                                <div class="card-body p-2 bg-light">
                                    <div class="d-flex flex-column gap-2">
                                        @foreach($divDistricts as $dist)
                                            @php
                                                $isDistChecked = in_array((int) $dist->id, array_map('intval', $selectedDistIds), true);
                                                $rcoCode = $rcoCodeMap[$dist->name] ?? $dist->code;
                                            @endphp
                                            <label class="card p-2 mb-0 border cursor-pointer district-item {{ $isDistChecked ? 'border-success bg-white' : 'border-secondary-subtle bg-white' }}" style="cursor: pointer;">
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <div class="d-flex align-items-center">
                                                        <input type="checkbox" name="district_ids[]" value="{{ $dist->id }}" 
                                                            class="form-check-input district-checkbox me-2 mt-0" 
                                                            data-division-id="{{ $division->id }}"
                                                            @checked($isDistChecked)>
                                                        <span class="fw-semibold text-dark">{{ $dist->name }}</span>
                                                    </div>
                                                    @if($rcoCode)
                                                        <span class="badge bg-secondary-subtle text-secondary font-monospace" style="font-size: 0.72rem;">
                                                            {{ $rcoCode }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="small text-muted mt-3">
                    <i class="fa-solid fa-circle-check text-success me-1"></i>
                    <strong>Multi-district Scoping:</strong> A Director will view, manage, and download reports for <strong>all checked districts</strong>. Unchecked districts are securely hidden from review lists, MIS tables, and dashboards.
                </div>
            </div>
        </div>
    </div>

    <!-- Active Status -->
    <div class="col-12 mt-3">
        <div class="form-check form-switch">
            <input type="checkbox" class="form-check-input" name="is_active" value="1" id="is_active"
                {{ old('is_active', $user->is_active ?? true) ? 'checked' : '' }}>
            <label class="form-check-label fw-bold" for="is_active">Account Active &amp; Enabled</label>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const roleFallback = document.getElementById('primary_role_fallback');
    const roleCheckboxes = document.querySelectorAll('.role-checkbox');
    const roleCountBadge = document.getElementById('selected-roles-count');

    const permCheckboxes = document.querySelectorAll('.perm-checkbox');
    const permCountBadge = document.getElementById('selected-permissions-count');

    const divPreset = document.getElementById('division_preset_selector');
    const districtCheckboxes = document.querySelectorAll('.district-checkbox');
    const deptCheckboxes = document.querySelectorAll('.dept-checkbox');
    const selectedDistCountBadge = document.getElementById('selected-districts-count');

    function updateRoleCount() {
        const checked = document.querySelectorAll('.role-checkbox:checked');
        if (roleCountBadge) {
            roleCountBadge.textContent = checked.length + ' Role(s) Selected';
        }
        if (checked.length > 0 && roleFallback) {
            roleFallback.value = checked[0].value;
        } else if (roleFallback) {
            roleFallback.value = '';
        }
    }

    function updatePermissionCount() {
        const checked = document.querySelectorAll('.perm-checkbox:checked').length;
        if (permCountBadge) {
            permCountBadge.textContent = checked + ' Custom Permission(s) Selected';
        }
    }

    function updateDistrictCount() {
        const checked = document.querySelectorAll('.district-checkbox:checked').length;
        if (selectedDistCountBadge) {
            selectedDistCountBadge.textContent = checked + ' District' + (checked === 1 ? '' : 's') + ' Selected';
        }
    }

    // Role checkbox changes
    roleCheckboxes.forEach(cb => {
        cb.addEventListener('change', function () {
            const card = this.closest('.role-card');
            if (card) {
                card.classList.toggle('border-primary', this.checked);
                card.classList.toggle('bg-primary-subtle', this.checked);
                card.classList.toggle('shadow-sm', this.checked);
            }
            updateRoleCount();

            // Auto-check departments if specific director role selected
            if (this.checked) {
                const roleName = this.getAttribute('data-role-name');
                if (roleName === 'Director Agriculture') {
                    deptCheckboxes.forEach(dcb => {
                        if (dcb.getAttribute('data-dept-slug') === 'agriculture') {
                            dcb.checked = true;
                            dcb.closest('.dept-card')?.classList.add('border-primary', 'bg-primary-subtle');
                        }
                    });
                } else if (roleName === 'Director Livestock') {
                    deptCheckboxes.forEach(dcb => {
                        if (dcb.getAttribute('data-dept-slug') === 'livestock') {
                            dcb.checked = true;
                            dcb.closest('.dept-card')?.classList.add('border-primary', 'bg-primary-subtle');
                        }
                    });
                } else if (roleName === 'Director Fisheries') {
                    deptCheckboxes.forEach(dcb => {
                        if (dcb.getAttribute('data-dept-slug') === 'fisheries') {
                            dcb.checked = true;
                            dcb.closest('.dept-card')?.classList.add('border-primary', 'bg-primary-subtle');
                        }
                    });
                } else if (roleName === 'Super Admin' || roleName === 'Secretary') {
                    deptCheckboxes.forEach(dcb => {
                        dcb.checked = true;
                        dcb.closest('.dept-card')?.classList.add('border-primary', 'bg-primary-subtle');
                    });
                    districtCheckboxes.forEach(dcb => {
                        dcb.checked = true;
                        dcb.closest('.district-item')?.classList.add('border-success');
                    });
                    if (divPreset) divPreset.value = 'all';
                    updateDistrictCount();
                }
            }
        });
    });

    document.getElementById('btn-clear-roles')?.addEventListener('click', function () {
        roleCheckboxes.forEach(cb => {
            cb.checked = false;
            const card = cb.closest('.role-card');
            if (card) {
                card.classList.remove('border-primary', 'bg-primary-subtle', 'shadow-sm');
            }
        });
        updateRoleCount();
    });

    // Permission checkbox changes
    permCheckboxes.forEach(cb => {
        cb.addEventListener('change', function () {
            const item = this.closest('.perm-item');
            if (item) {
                item.classList.toggle('border-info', this.checked);
                item.classList.toggle('bg-info-subtle', this.checked);
            }
            updatePermissionCount();
        });
    });

    // Select All / Clear All Permissions
    document.getElementById('btn-select-all-permissions')?.addEventListener('click', function () {
        permCheckboxes.forEach(cb => {
            cb.checked = true;
            const item = cb.closest('.perm-item');
            if (item) {
                item.classList.add('border-info', 'bg-info-subtle');
            }
        });
        updatePermissionCount();
    });

    document.getElementById('btn-clear-all-permissions')?.addEventListener('click', function () {
        permCheckboxes.forEach(cb => {
            cb.checked = false;
            const item = cb.closest('.perm-item');
            if (item) {
                item.classList.remove('border-info', 'bg-info-subtle');
            }
        });
        updatePermissionCount();
    });

    // Group-level permission toggles
    document.querySelectorAll('.btn-toggle-group-perms').forEach(btn => {
        btn.addEventListener('click', function () {
            const group = this.getAttribute('data-group');
            const groupBoxes = document.querySelectorAll('.perm-checkbox[data-group="' + group + '"]');
            groupBoxes.forEach(cb => {
                cb.checked = true;
                const item = cb.closest('.perm-item');
                if (item) {
                    item.classList.add('border-info', 'bg-info-subtle');
                }
            });
            updatePermissionCount();
        });
    });

    document.querySelectorAll('.btn-clear-group-perms').forEach(btn => {
        btn.addEventListener('click', function () {
            const group = this.getAttribute('data-group');
            const groupBoxes = document.querySelectorAll('.perm-checkbox[data-group="' + group + '"]');
            groupBoxes.forEach(cb => {
                cb.checked = false;
                const item = cb.closest('.perm-item');
                if (item) {
                    item.classList.remove('border-info', 'bg-info-subtle');
                }
            });
            updatePermissionCount();
        });
    });

    // Toggle division districts
    document.querySelectorAll('.btn-toggle-div-districts').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const divId = this.getAttribute('data-division-id');
            const checkboxes = document.querySelectorAll('.district-checkbox[data-division-id="' + divId + '"]');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            checkboxes.forEach(cb => {
                cb.checked = !allChecked;
                const card = cb.closest('.district-item');
                if (card) {
                    card.classList.toggle('border-success', !allChecked);
                }
            });
            updateDistrictCount();
        });
    });

    // Preset selector change
    if (divPreset) {
        divPreset.addEventListener('change', function () {
            const val = this.value;
            if (val === 'all') {
                districtCheckboxes.forEach(cb => {
                    cb.checked = true;
                    cb.closest('.district-item')?.classList.add('border-success');
                });
            } else if (val) {
                districtCheckboxes.forEach(cb => {
                    const matches = cb.getAttribute('data-division-id') === val;
                    cb.checked = matches;
                    if (cb.closest('.district-item')) {
                        cb.closest('.district-item').classList.toggle('border-success', matches);
                    }
                });
            }
            updateDistrictCount();
        });
    }

    // Select all / clear districts buttons
    document.getElementById('btn-select-all-districts')?.addEventListener('click', function () {
        districtCheckboxes.forEach(cb => {
            cb.checked = true;
            cb.closest('.district-item')?.classList.add('border-success');
        });
        if (divPreset) divPreset.value = 'all';
        updateDistrictCount();
    });

    document.getElementById('btn-clear-districts')?.addEventListener('click', function () {
        districtCheckboxes.forEach(cb => {
            cb.checked = false;
            cb.closest('.district-item')?.classList.remove('border-success');
        });
        if (divPreset) divPreset.value = '';
        updateDistrictCount();
    });

    // Departments buttons
    document.getElementById('btn-select-all-depts')?.addEventListener('click', function () {
        deptCheckboxes.forEach(cb => {
            cb.checked = true;
            cb.closest('.dept-card')?.classList.add('border-primary', 'bg-primary-subtle');
        });
    });

    document.getElementById('btn-clear-depts')?.addEventListener('click', function () {
        deptCheckboxes.forEach(cb => {
            cb.checked = false;
            cb.closest('.dept-card')?.classList.remove('border-primary', 'bg-primary-subtle');
        });
    });

    // District Card highlight on change
    districtCheckboxes.forEach(cb => {
        cb.addEventListener('change', function () {
            this.closest('.district-item')?.classList.toggle('border-success', this.checked);
            updateDistrictCount();
        });
    });

    deptCheckboxes.forEach(cb => {
        cb.addEventListener('change', function () {
            this.closest('.dept-card')?.classList.toggle('border-primary', this.checked);
            this.closest('.dept-card')?.classList.toggle('bg-primary-subtle', this.checked);
        });
    });

    updateRoleCount();
    updatePermissionCount();
    updateDistrictCount();
});
</script>
