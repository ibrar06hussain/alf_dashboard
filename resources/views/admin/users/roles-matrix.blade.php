@extends('layouts.admin')

@section('title', 'Role & Permission Matrix')

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'Role & Permission Governance Matrix',
    'subtitle' => 'Detailed mapping of system permissions across organizational roles.',
    'icon' => 'fa-solid fa-shield-halved',
    'actions' => [
        ['label' => 'Back to Users', 'url' => route('admin.users.index'), 'class' => 'btn btn-outline-secondary'],
    ],
])

<div class="card alf-panel mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Security & Authority Governance Matrix</span>
        <span class="badge bg-secondary">{{ $roles->count() }} Roles · {{ $permissions->count() }} Permissions</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table alf-table align-middle table-bordered mb-0">
                <thead class="table-light">
                <tr>
                    <th style="min-width: 250px;">Permission Name</th>
                    @foreach($roles as $role)
                        <th class="text-center" style="min-width: 130px;">
                            <span class="d-block fw-bold text-dark">{{ $role->name }}</span>
                            @if(in_array($role->name, ['Super Admin', 'Secretary', 'Division Director']))
                                <span class="badge bg-warning-subtle text-warning py-0 px-1" style="font-size: 10px;">No Beneficiary Approval</span>
                            @elseif(in_array($role->name, ['District Deputy Director', 'District Officer']))
                                <span class="badge bg-success-subtle text-success py-0 px-1" style="font-size: 10px;">Full District Approval</span>
                            @endif
                        </th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @foreach($permissions as $permission)
                    <tr>
                        <td>
                            <code class="text-primary fw-bold">{{ $permission->name }}</code>
                        </td>
                        @foreach($roles as $role)
                            @php
                                $hasPerm = $role->hasPermissionTo($permission->name);
                            @endphp
                            <td class="text-center">
                                @if($hasPerm)
                                    <span class="text-success fs-5"><i class="fa-solid fa-circle-check"></i></span>
                                @else
                                    <span class="text-muted opacity-25 fs-6"><i class="fa-solid fa-minus"></i></span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
