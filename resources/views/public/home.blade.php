@extends('layouts.public')

@section('title', 'ALF Gilgit-Baltistan')
@section('meta_description', 'Official portal of the Secretariat of Agriculture, Livestock & Dairy Development, and Fisheries, Government of Gilgit-Baltistan.')

@section('content')
{{-- Hero --}}
<div class="hero-area">
    <div class="welcome-slides swiper">
        <div class="single-welcome-slides bg-img bg-overlay jarallax" style="background-image: url('{{ asset('frontend/img/bg-img/1.jpg') }}');">
            <div class="container h-100">
                <div class="row h-100 align-items-center">
                    <div class="col-12 col-lg-10">
                        <div class="welcome-content">
                            <h2 data-aos="fade-up" data-aos-delay="100">ALF Gilgit-Baltistan</h2>
                            <p data-aos="fade-up" data-aos-delay="250">
                                Secretariat of Agriculture, Livestock &amp; Dairy Development, and Fisheries —
                                Government of Gilgit-Baltistan. Apply online for schemes, track applications, and access department services in one place.
                            </p>
                            <div data-aos="fade-up" data-aos-delay="400">
                                <a href="{{ route('schemes.index', ['type' => 'running']) }}" class="btn famie-btn mt-4 me-2">Apply for Schemes</a>
                                <a href="#cm-proposed-schemes" class="btn famie-btn mt-4 me-2" style="background:#fff;color:#0f1112;">CM Proposed Schemes</a>
                                <a href="{{ route('applications.track') }}" class="btn famie-btn mt-4">Track Application</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="single-welcome-slides bg-img bg-overlay jarallax" style="background-image: url('{{ asset('frontend/img/bg-img/7.jpg') }}');">
            <div class="container h-100">
                <div class="row h-100 align-items-center">
                    <div class="col-12 col-lg-10">
                        <div class="welcome-content">
                            <h2 data-aos="fade-down" data-aos-delay="100">Transparent Digital Services</h2>
                            <p data-aos="fade-down" data-aos-delay="250">
                                From seed distribution and dairy farm registration to trout farm support — submit applications with CNIC verification and monitor status in real time.
                            </p>
                            <a href="{{ route('about') }}" class="btn famie-btn mt-4" data-aos="fade-down" data-aos-delay="400">Learn More</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Portal stats — Farmie section style + modern counters --}}
<section id="portal-stats" class="alf-stats-area famie-benefits-area">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="section-heading text-center">
                    <p>At a Glance</p>
                    <h2><span>Portal</span> Statistics</h2>
                </div>
            </div>
        </div>

        <div class="row g-4 justify-content-center">
            <div class="col-6 col-md-4 col-xl">
                <div class="alf-stat-tile text-center">
                    <div class="icon-wrap"><i class="fa-solid fa-building-columns" aria-hidden="true"></i></div>
                    <div class="stat-value" data-count="{{ (int) $stats['departments'] }}">0</div>
                    <p class="stat-label">Departments</p>
                    <span class="stat-accent" aria-hidden="true"></span>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <div class="alf-stat-tile text-center">
                    <div class="icon-wrap"><i class="fa-solid fa-seedling" aria-hidden="true"></i></div>
                    <div class="stat-value" data-count="{{ (int) $stats['running'] }}">0</div>
                    <p class="stat-label">On Going Schemes</p>
                    <span class="stat-accent" aria-hidden="true"></span>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <div class="alf-stat-tile text-center">
                    <div class="icon-wrap"><i class="fa-solid fa-flag" aria-hidden="true"></i></div>
                    <div class="stat-value" data-count="{{ (int) ($stats['proposed'] ?? 0) }}">0</div>
                    <p class="stat-label">Proposed for CM</p>
                    <span class="stat-accent" aria-hidden="true"></span>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <div class="alf-stat-tile text-center">
                    <div class="icon-wrap"><i class="fa-solid fa-users" aria-hidden="true"></i></div>
                    <div class="stat-value" data-count="{{ (int) $stats['applications'] }}">0</div>
                    <p class="stat-label">Applications</p>
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
                    <p>Our Mandate</p>
                    <h2><span>Departments</span></h2>
                </div>
            </div>
        </div>
        <div class="row g-4">
            @foreach($departments as $i => $department)
                <div class="col-12 col-md-4">
                    <div class="alf-dept-card">
                        <h4>{{ $department->name }}</h4>
                        <p class="text-muted mb-0">{{ \Illuminate\Support\Str::limit($department->vision, 140) }}</p>
                        <div class="alf-metric-grid">
                            <div class="alf-metric-chip">
                                <span class="val">{{ $department->active_schemes_count }}</span>
                                <span class="lbl">Active schemes</span>
                            </div>
                            @foreach($department->stats->sortBy([['source', 'asc'], ['display_order', 'asc']])->take(3) as $stat)
                                <div class="alf-metric-chip">
                                    <span class="val">{{ $stat->stat_value }}</span>
                                    <span class="lbl">{{ \Illuminate\Support\Str::limit($stat->stat_label, 28) }}</span>
                                </div>
                            @endforeach
                        </div>
                        <a href="{{ route('departments.show', $department->slug) }}" class="btn famie-btn">View Department</a>
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
                    <p>For Honourable Chief Minister</p>
                    <h2><span>Proposed</span> &amp; Upcoming Schemes</h2>
                </div>
                <p class="text-muted mb-0 mt-3" style="max-width: 640px;">
                    Flagship proposals under consideration for Gilgit-Baltistan — register your interest so the Secretariat can assess citizen demand before launch.
                </p>
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                <a href="{{ route('schemes.index', ['type' => 'proposed']) }}" class="btn famie-btn">All Proposed Schemes</a>
            </div>
        </div>

        <div class="row g-4">
            @forelse($proposedSchemes as $i => $scheme)
                <div class="col-12 col-md-6 col-lg-4">
                    <article class="alf-proposed-card h-100">
                        <div class="alf-proposed-media" style="background-image:url('{{ $scheme->imageUrl(($i % 5) + 8) }}');">
                            <span class="alf-proposed-tag">Proposed / Upcoming</span>
                        </div>
                        <div class="alf-proposed-body">
                            <p class="alf-proposed-dept mb-1">{{ $scheme->department?->name }}</p>
                            <h5 class="mb-2">{{ $scheme->title }}</h5>
                            @if($scheme->budget)
                                <p class="alf-proposed-budget mb-2">
                                    Proposed budget: <strong>PKR {{ number_format($scheme->budget / 1000000, 1) }} M</strong>
                                </p>
                            @endif
                            <p class="small text-muted mb-3">{{ \Illuminate\Support\Str::limit(strip_tags($scheme->description), 110) }}</p>
                            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
                                <span class="small text-muted">
                                    <i class="fa-regular fa-bell me-1" aria-hidden="true"></i>
                                    {{ $scheme->interests_count }} interest{{ $scheme->interests_count === 1 ? '' : 's' }}
                                </span>
                                <div class="d-flex gap-2">
                                    <a href="{{ route('schemes.show', $scheme->slug) }}" class="btn famie-btn" style="min-width:auto;height:42px;line-height:40px;padding:0 18px;font-size:13px;">Details</a>
                                    <a href="{{ route('schemes.show', $scheme->slug) }}#express-interest" class="btn famie-btn" style="min-width:auto;height:42px;line-height:40px;padding:0 18px;font-size:13px;background:#0f1112;">Express Interest</a>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            @empty
                <div class="col-12">
                    <div class="alf-proposed-empty text-center">
                        <p class="mb-0 text-muted">Proposed schemes for CM consideration will appear here once published by the departments.</p>
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
                    <p>Citizen Opportunities</p>
                    <h2><span>On Going</span> Schemes</h2>
                </div>
            </div>
        </div>
        <div class="row">
            @forelse($featuredSchemes as $i => $scheme)
                <div class="col-12 col-md-6 col-lg-4 mb-4">
                    <div class="alf-dept-card overflow-hidden p-0">
                        <div class="bg-img" style="height:160px;background-image:url('{{ asset('frontend/img/bg-img/'.(($i % 6) + 4).'.jpg') }}');background-size:cover;background-position:center;"></div>
                        <div class="p-4">
                            <span class="scheme-badge-running mb-2 d-inline-block">On Going</span>
                            <h5 class="mb-2">{{ $scheme->title }}</h5>
                            <p class="small text-muted mb-2">{{ $scheme->department?->name }}</p>
                            <p class="small">{{ \Illuminate\Support\Str::limit(strip_tags($scheme->description), 100) }}</p>
                            <a href="{{ route('schemes.show', $scheme->slug) }}" class="btn famie-btn" style="min-width:auto;height:42px;line-height:40px;padding:0 18px;font-size:13px;">Details</a>
                            @if($scheme->isOpenForApplications())
                                <a href="{{ route('schemes.apply', $scheme->slug) }}" class="btn famie-btn" style="min-width:auto;height:42px;line-height:40px;padding:0 18px;font-size:13px;background:#0f1112;">Apply</a>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-center pb-5">
                    <p class="text-muted">On going schemes will appear here once published.</p>
                </div>
            @endforelse
        </div>
        <div class="row">
            <div class="col-12 text-center pb-5">
                <a href="{{ route('schemes.index', ['type' => 'running']) }}" class="btn famie-btn">View All On Going Schemes</a>
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
