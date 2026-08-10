@extends('layouts.admin')

@section('title', 'Homepage Carousel')

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'Homepage Carousel',
    'subtitle' => 'Hero slides shown on the public homepage. Upload images and set CTAs.',
    'icon' => 'fa-solid fa-images',
    'actions' => [
        ['label' => 'Add Slide', 'url' => route('admin.carousel.create'), 'class' => 'btn btn-primary'],
    ],
])

@include('admin.partials.stats', ['stats' => $pageStats])

<div class="card alf-panel mb-3">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table alf-table">
                <thead>
                <tr>
                    <th>Order</th>
                    <th>Preview</th>
                    <th>Title</th>
                    <th>Buttons</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($slides as $slide)
                    <tr>
                        <td>{{ $slide->display_order }}</td>
                        <td>
                            <img src="{{ $slide->imageUrl() }}" alt="" class="alf-table-thumb" width="88" height="56" style="width:88px;height:56px;">
                        </td>
                        <td>
                            <strong>{{ $slide->title }}</strong>
                            <div class="small text-muted">{{ \Illuminate\Support\Str::limit($slide->subtitle, 80) }}</div>
                        </td>
                        <td class="small">
                            @if($slide->button_label) {{ $slide->button_label }} @endif
                            @if($slide->button2_label)<br>{{ $slide->button2_label }} @endif
                        </td>
                        <td>
                            <span class="alf-badge alf-badge--{{ $slide->is_active ? 'green' : 'slate' }}">
                                {{ $slide->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td>
                            <div class="alf-actions">
                                <a href="{{ route('admin.carousel.edit', $slide) }}" class="btn btn-sm btn-primary">Edit</a>
                                <form action="{{ route('admin.carousel.destroy', $slide) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete slide?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="alf-empty">No carousel slides yet. Add the first homepage hero slide.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $slides->links() }}</div>
    </div>
</div>
@endsection
