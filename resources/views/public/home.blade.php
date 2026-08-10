@extends('layouts.public')

@section('title', \App\Models\SiteSetting::text('site_name'))
@section('meta_description', \App\Models\SiteSetting::text('footer_about'))

@section('content')
{{-- Hero --}}
<div class="hero-area">
    <div class="welcome-slides swiper">
        @forelse($carouselSlides as $slide)
            <div class="single-welcome-slides alf-media-hero bg-img bg-overlay jarallax"
                 data-alf-fit="bg"
                 data-alf-w="{{ \App\Support\ImageSlots::get('carousel')['width'] }}"
                 data-alf-h="{{ \App\Support\ImageSlots::get('carousel')['height'] }}"
                 style="background-image: url('{{ $slide->imageUrl() }}');">
                <div class="container h-100">
                    <div class="row h-100 align-items-center">
                        <div class="col-12 col-lg-10">
                            <div class="welcome-content">
                                <h2 data-aos="fade-up" data-aos-delay="100">{{ $slide->t("title") }}</h2>
                                @if($slide->t("subtitle"))
                                    <p data-aos="fade-up" data-aos-delay="250">{{ $slide->t("subtitle") }}</p>
                                @endif
                                <div data-aos="fade-up" data-aos-delay="400">
                                    @if($slide->t("button_label") && $slide->button_url)
                                        <a href="{{ url($slide->button_url) }}" class="btn famie-btn mt-4 me-2">{{ $slide->t("button_label") }}</a>
                                    @endif
                                    @if($slide->t("button2_label") && $slide->button2_url)
                                        <a href="{{ url($slide->button2_url) }}" class="btn famie-btn mt-4 me-2" style="background:#fff;color:#0f1112;">{{ $slide->t("button2_label") }}</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="single-welcome-slides alf-media-hero bg-img bg-overlay jarallax"
                 data-alf-fit="bg"
                 data-alf-w="{{ \App\Support\ImageSlots::get('carousel')['width'] }}"
                 data-alf-h="{{ \App\Support\ImageSlots::get('carousel')['height'] }}"
                 style="background-image: url('{{ \App\Support\Media::site('default_breadcrumb', 'frontend/img/bg-img/1.jpg') }}');">
                <div class="container h-100">
                    <div class="row h-100 align-items-center">
                        <div class="col-12 col-lg-10">
                            <div class="welcome-content">
                                <h2>{{ \App\Models\SiteSetting::text('site_name') }}</h2>
                                <p>{{ __('portal.add_carousel_hint') }}</p>
                                <a href="{{ route('schemes.index') }}" class="btn famie-btn mt-4">{{ __('portal.browse_schemes') }}</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforelse
    </div>
</div>

{{-- Portal stats — Farmie section style + modern counters --}}
<section id="portal-stats" class="alf-stats-area famie-benefits-area">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="section-heading text-center">
                    <p>{{ __('portal.at_a_glance') }}</p>
                    <h2><span>{{ __('portal.portal') }}</span> {{ __('portal.statistics') }}</h2>
                </div>
            </div>
        </div>

        <div class="row g-4 justify-content-center">
            <div class="col-6 col-md-4 col-xl">
                <div class="alf-stat-tile text-center">
                    <div class="icon-wrap"><i class="fa-solid fa-building-columns" aria-hidden="true"></i></div>
                    <div class="stat-value" data-count="{{ (int) $stats['departments'] }}">0</div>
                    <p class="stat-label">{{ __('portal.departments') }}</p>
                    <span class="stat-accent" aria-hidden="true"></span>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <div class="alf-stat-tile text-center">
                    <div class="icon-wrap"><i class="fa-solid fa-seedling" aria-hidden="true"></i></div>
                    <div class="stat-value" data-count="{{ (int) $stats['running'] }}">0</div>
                    <p class="stat-label">{{ __('portal.on_going_schemes_short') }}</p>
                    <span class="stat-accent" aria-hidden="true"></span>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <div class="alf-stat-tile text-center">
                    <div class="icon-wrap"><i class="fa-solid fa-flag" aria-hidden="true"></i></div>
                    <div class="stat-value" data-count="{{ (int) ($stats['proposed'] ?? 0) }}">0</div>
                    <p class="stat-label">{{ __('portal.proposed_for_cm_short') }}</p>
                    <span class="stat-accent" aria-hidden="true"></span>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <div class="alf-stat-tile text-center">
                    <div class="icon-wrap"><i class="fa-solid fa-users" aria-hidden="true"></i></div>
                    <div class="stat-value" data-count="{{ (int) $stats['applications'] }}">0</div>
                    <p class="stat-label">{{ __('portal.applications') }}</p>
                    <span class="stat-accent" aria-hidden="true"></span>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Departments --}}
<section class="about-us-area section-padding-100">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="section-heading text-center">
                    <p>{{ __('portal.our_mandate') }}</p>
                    <h2><span>{{ __('portal.departments') }}</span></h2>
                </div>
            </div>
        </div>
        <div class="row g-4">
            @foreach($departments as $i => $department)
                <div class="col-12 col-md-4">
                    <div class="alf-dept-card overflow-hidden p-0">
                        <div class="alf-media-card bg-img"
                             data-alf-fit="bg"
                             data-alf-w="{{ \App\Support\ImageSlots::get('department_card')['width'] }}"
                             data-alf-h="{{ \App\Support\ImageSlots::get('department_card')['height'] }}"
                             style="background-image:url('{{ $department->coverUrl() }}');"></div>
                        <div class="p-4">
                        <h4>{{ $department->t("name") }}</h4>
                        <p class="text-muted mb-0">{{ \Illuminate\Support\Str::limit($department->t("vision"), 140) }}</p>
                        <div class="alf-metric-grid">
                            <div class="alf-metric-chip">
                                <span class="val">{{ $department->active_schemes_count }}</span>
                                <span class="lbl">{{ __('portal.active_schemes') }}</span>
                            </div>
                            @foreach($department->stats->sortBy([['source', 'asc'], ['display_order', 'asc']])->take(3) as $stat)
                                <div class="alf-metric-chip">
                                    <span class="val">{{ $stat->stat_value }}</span>
                                    <span class="lbl">{{ \Illuminate\Support\Str::limit($stat->t("stat_label"), 28) }}</span>
                                </div>
                            @endforeach
                        </div>
                        <a href="{{ route('departments.show', $department->slug) }}" class="btn famie-btn">{{ __('portal.view_department') }}</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Proposed schemes for CM presentation --}}
<section id="cm-proposed-schemes" class="alf-proposed-area section-padding-100">
    <div class="container">
        <div class="row align-items-end mb-4">
            <div class="col-lg-8">
                <div class="section-heading mb-0">
                    <p>{{ __('portal.for_honourable_cm') }}</p>
                    <h2><span>{{ __('portal.proposed') }}</span> {{ __('portal.upcoming_schemes') }}</h2>
                </div>
                <p class="text-muted mb-0 mt-3" style="max-width: 640px;">
                    {{ __('portal.proposed_blurb') }}
                </p>
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                <a href="{{ route('schemes.index', ['type' => 'proposed']) }}" class="btn famie-btn">{{ __('portal.all_proposed_schemes') }}</a>
            </div>
        </div>

        <div class="row g-4">
            @forelse($proposedSchemes as $i => $scheme)
                <div class="col-12 col-md-6 col-lg-4">
                    <article class="alf-proposed-card h-100">
                        <div class="alf-proposed-media alf-media-card"
                             data-alf-fit="bg"
                             data-alf-w="{{ \App\Support\ImageSlots::get('scheme_cover')['width'] }}"
                             data-alf-h="{{ \App\Support\ImageSlots::get('scheme_cover')['height'] }}"
                             style="background-image:url('{{ $scheme->imageUrl(($i % 5) + 8) }}');">
                            <span class="alf-proposed-tag">{{ __('portal.proposed_upcoming_tag') }}</span>
                        </div>
                        <div class="alf-proposed-body">
                            <p class="alf-proposed-dept mb-1">{{ $scheme->department?->t("name") }}</p>
                            <h5 class="mb-2">{{ $scheme->t("title") }}</h5>
                            @if($scheme->budget)
                                <p class="alf-proposed-budget mb-2">
                                    {{ __('portal.proposed_budget') }}: <strong>PKR {{ number_format($scheme->budget / 1000000, 1) }} M</strong>
                                </p>
                            @endif
                            <p class="small text-muted mb-3">{{ \Illuminate\Support\Str::limit(strip_tags($scheme->t("description")), 110) }}</p>
                            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
                                <span class="small text-muted">
                                    <i class="fa-regular fa-bell me-1" aria-hidden="true"></i>
                                    {{ $scheme->interests_count }} {{ $scheme->interests_count === 1 ? __('portal.interest') : __('portal.interests') }}
                                </span>
                                <div class="d-flex gap-2">
                                    <a href="{{ route('schemes.show', $scheme->slug) }}" class="btn famie-btn" style="min-width:auto;height:42px;line-height:40px;padding:0 18px;font-size:13px;">{{ __('portal.details') }}</a>
                                    <a href="{{ route('schemes.show', $scheme->slug) }}#express-interest" class="btn famie-btn" style="min-width:auto;height:42px;line-height:40px;padding:0 18px;font-size:13px;background:#0f1112;">{{ __('portal.express_interest') }}</a>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            @empty
                <div class="col-12">
                    <div class="alf-proposed-empty text-center">
                        <p class="mb-0 text-muted">{{ __('portal.proposed_empty') }}</p>
                    </div>
                </div>
            @endforelse
        </div>
    </div>
</section>

{{-- Featured on going schemes --}}
<section class="news-area section-padding-100-0 bg-gray">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="section-heading text-center">
                    <p>{{ __('portal.citizen_opportunities') }}</p>
                    <h2><span>{{ __('portal.on_going') }}</span> {{ __('portal.schemes') }}</h2>
                </div>
            </div>
        </div>
        <div class="row">
            @forelse($featuredSchemes as $i => $scheme)
                <div class="col-12 col-md-6 col-lg-4 mb-4">
                    <div class="alf-dept-card overflow-hidden p-0">
                        <div class="alf-media-card bg-img"
                             data-alf-fit="bg"
                             data-alf-w="{{ \App\Support\ImageSlots::get('scheme_cover')['width'] }}"
                             data-alf-h="{{ \App\Support\ImageSlots::get('scheme_cover')['height'] }}"
                             style="background-image:url('{{ $scheme->imageUrl(($i % 6) + 4) }}');"></div>
                        <div class="p-4">
                            <span class="scheme-badge-running mb-2 d-inline-block">{{ __('portal.on_going') }}</span>
                            <h5 class="mb-2">{{ $scheme->t("title") }}</h5>
                            <p class="small text-muted mb-2">{{ $scheme->department?->t("name") }}</p>
                            <p class="small">{{ \Illuminate\Support\Str::limit(strip_tags($scheme->t("description")), 100) }}</p>
                            <a href="{{ route('schemes.show', $scheme->slug) }}" class="btn famie-btn" style="min-width:auto;height:42px;line-height:40px;padding:0 18px;font-size:13px;">{{ __('portal.details') }}</a>
                            @if($scheme->isOpenForApplications())
                                <a href="{{ route('schemes.apply', $scheme->slug) }}" class="btn famie-btn" style="min-width:auto;height:42px;line-height:40px;padding:0 18px;font-size:13px;background:#0f1112;">{{ __('portal.apply') }}</a>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-center pb-5">
                    <p class="text-muted">{{ __('portal.no_featured_schemes') }}</p>
                </div>
            @endforelse
        </div>
        <div class="row">
            <div class="col-12 text-center pb-5">
                <a href="{{ route('schemes.index', ['type' => 'running']) }}" class="btn famie-btn">{{ __('portal.view_all_running') }}</a>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
    function animateCount(el) {
        var target = parseInt(el.getAttribute('data-count') || '0', 10);
        if (!target) { el.textContent = '0'; return; }
        var duration = 1100;
        var start = null;
        function step(ts) {
            if (!start) start = ts;
            var p = Math.min((ts - start) / duration, 1);
            var eased = 1 - Math.pow(1 - p, 3);
            el.textContent = Math.round(target * eased).toLocaleString();
            if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }

    var nodes = document.querySelectorAll('#portal-stats .stat-value[data-count]');
    if (!nodes.length) return;

    if ('IntersectionObserver' in window) {
        var seen = false;
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting && !seen) {
                    seen = true;
                    nodes.forEach(animateCount);
                    io.disconnect();
                }
            });
        }, { threshold: 0.35 });
        io.observe(document.getElementById('portal-stats'));
    } else {
        nodes.forEach(animateCount);
    }
})();
</script>
@endpush
