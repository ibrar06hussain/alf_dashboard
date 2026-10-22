@if(isset($newSchemes) && $newSchemes->isNotEmpty())
<!-- New Schemes Announcement Popup Modal -->
<div class="modal fade alf-schemes-modal" id="alfNewSchemesModal" tabindex="-1" aria-labelledby="alfNewSchemesModalTitle" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
    <div class="modal-dialog modal-dialog-centered modal-xl alf-schemes-modal-dialog">
        <div class="modal-content alf-schemes-modal-content border-0 shadow-lg">
            
            {{-- Modal Header Banner --}}
            <div class="alf-schemes-modal-header text-white">
                <div class="alf-header-left">
                    <span class="alf-announcement-chip">
                        <i class="fa-solid fa-sparkles me-1 text-warning"></i>
                        {{ __('portal.popup_announcement') }}
                    </span>
                    <h2 class="alf-modal-headline mb-1" id="alfNewSchemesModalTitle">
                        {{ __('portal.popup_new_schemes_title') }}
                    </h2>
                    <p class="alf-modal-tagline mb-0">
                        {{ __('portal.popup_new_schemes_subtitle') }}
                    </p>
                </div>
                <div class="alf-header-right d-flex align-items-center gap-2">
                    <div class="alf-slide-counter d-none d-sm-inline-flex align-items-center">
                        <span id="alfPopupCurrentIdx" class="fw-bold">1</span>
                        <span class="text-white-50 mx-1">/</span>
                        <span class="text-white-50">{{ $newSchemes->count() }}</span>
                    </div>
                    <button type="button" class="alf-modal-close-btn" data-bs-dismiss="modal" aria-label="{{ __('portal.popup_close') }}" title="{{ __('portal.popup_close') }}">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            {{-- Modal Body: Swiper Slider --}}
            <div class="modal-body p-0 position-relative">
                <div class="swiper alf-schemes-swiper" id="alfPopupSwiper" dir="{{ app()->getLocale() === 'ur' ? 'rtl' : 'ltr' }}">
                    <div class="swiper-wrapper">
                        @foreach($newSchemes as $index => $scheme)
                            <div class="swiper-slide">
                                <div class="alf-popup-slide-inner">
                                    <div class="row g-0 align-items-stretch h-100">
                                        
                                        {{-- Scheme Image Column --}}
                                        <div class="col-12 col-lg-5">
                                            <div class="alf-popup-media-box" style="background-image: url('{{ $scheme->imageUrl(($index % 6) + 4) }}');">
                                                <div class="alf-media-overlay"></div>
                                                
                                                {{-- Top Badges --}}
                                                <div class="alf-media-badges">
                                                    <span class="alf-media-badge-new">
                                                        <i class="fa-solid fa-bolt-lightning me-1"></i> {{ __('portal.popup_badge_new') }}
                                                    </span>
                                                    @if($scheme->department)
                                                        <span class="alf-media-badge-dept">
                                                            <i class="fa-solid fa-building-columns me-1"></i> {{ $scheme->department->t('name') }}
                                                        </span>
                                                    @endif
                                                </div>

                                                {{-- Bottom Live Status --}}
                                                @if($scheme->isOpenForApplications())
                                                    <div class="alf-media-status">
                                                        <span class="alf-live-beacon" aria-hidden="true">
                                                            <span class="alf-beacon-ping"></span>
                                                            <span class="alf-beacon-dot"></span>
                                                        </span>
                                                        <span>{{ __('portal.popup_open_status') }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>

                                        {{-- Scheme Content Column --}}
                                        <div class="col-12 col-lg-7 d-flex flex-column justify-content-between p-4 p-md-5 alf-popup-content-box">
                                            <div>
                                                {{-- Department Eyebrow --}}
                                                @if($scheme->department)
                                                    <div class="alf-scheme-eyebrow mb-2">
                                                        <i class="fa-solid fa-seedling me-1 text-success"></i>
                                                        {{ $scheme->department->t('name') }}
                                                    </div>
                                                @endif

                                                {{-- Title --}}
                                                <h3 class="alf-scheme-title mb-3">
                                                    <a href="{{ route('schemes.show', $scheme->slug) }}">
                                                        {{ $scheme->t('title') }}
                                                    </a>
                                                </h3>

                                                {{-- Feature Meta Pills --}}
                                                <div class="alf-scheme-pills mb-3">
                                                    @if($scheme->last_date)
                                                        <div class="alf-pill-item">
                                                            <i class="fa-regular fa-calendar-days text-success" aria-hidden="true"></i>
                                                            <span><strong>{{ __('portal.popup_deadline') }}:</strong> {{ $scheme->last_date->format('d M, Y') }}</span>
                                                        </div>
                                                    @else
                                                        <div class="alf-pill-item">
                                                            <i class="fa-solid fa-circle-check text-success" aria-hidden="true"></i>
                                                            <span>{{ __('portal.on_going') }}</span>
                                                        </div>
                                                    @endif

                                                    <div class="alf-pill-item">
                                                        <i class="fa-solid fa-location-dot text-primary" aria-hidden="true"></i>
                                                        <span>{{ $scheme->scopeLabel() }}</span>
                                                    </div>

                                                    @if($scheme->budget && $scheme->budget > 0)
                                                        <div class="alf-pill-item">
                                                            <i class="fa-solid fa-sack-dollar text-warning" aria-hidden="true"></i>
                                                            <span>PKR {{ number_format($scheme->budget / 1000000, 1) }}M</span>
                                                        </div>
                                                    @endif
                                                </div>

                                                {{-- Short Description --}}
                                                <p class="alf-scheme-description mb-4">
                                                    {{ \Illuminate\Support\Str::limit(strip_tags($scheme->t('description')), 170) }}
                                                </p>
                                            </div>

                                            {{-- CTAs: Apply Now + Details --}}
                                            <div class="alf-popup-actions pt-3 border-top d-flex align-items-center flex-wrap gap-3">
                                                @if($scheme->isOpenForApplications())
                                                    <a href="{{ route('schemes.apply', $scheme->slug) }}" class="btn alf-btn-apply-main">
                                                        <span class="alf-btn-pulse-wave" aria-hidden="true"></span>
                                                        <i class="fa-solid fa-paper-plane me-2" aria-hidden="true"></i>
                                                        <span>{{ __('portal.apply_now') }}</span>
                                                        <i class="fa-solid fa-arrow-right ms-2 alf-arrow-animated" aria-hidden="true"></i>
                                                    </a>
                                                @else
                                                    <a href="{{ route('schemes.show', $scheme->slug) }}" class="btn alf-btn-apply-main">
                                                        <i class="fa-solid fa-arrow-up-right-from-square me-2" aria-hidden="true"></i>
                                                        <span>{{ __('portal.view_details') }}</span>
                                                    </a>
                                                @endif

                                                <a href="{{ route('schemes.show', $scheme->slug) }}" class="btn alf-btn-details-secondary">
                                                    <i class="fa-solid fa-circle-info me-1" aria-hidden="true"></i>
                                                    {{ __('portal.details') }}
                                                </a>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Swiper Arrow Navigation --}}
                    <div class="swiper-button-prev alf-swiper-nav-prev" title="Previous Scheme">
                        <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                    </div>
                    <div class="swiper-button-next alf-swiper-nav-next" title="Next Scheme">
                        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                    </div>
                </div>
            </div>

            {{-- Modal Footer --}}
            <div class="modal-footer alf-schemes-modal-footer d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="form-check form-switch m-0 d-flex align-items-center">
                    <input class="form-check-input" type="checkbox" role="switch" id="alfDismissPopupCheck">
                    <label class="form-check-label small text-muted ms-2 user-select-none" for="alfDismissPopupCheck">
                        {{ __('portal.popup_dont_show_again') }}
                    </label>
                </div>

                {{-- Pagination Dots in center --}}
                <div class="alf-popup-pagination swiper-pagination"></div>

                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('schemes.index') }}" class="btn btn-sm btn-link text-success fw-bold text-decoration-none">
                        <i class="fa-solid fa-layer-group me-1"></i> {{ __('portal.popup_browse_all') }}
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-secondary px-3" data-bs-dismiss="modal">
                        {{ __('portal.popup_close') }}
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- Floating Quick Trigger Button (Allows user to reopen the new schemes popup anytime) --}}
<button id="alfFloatingSchemesTrigger" class="alf-floating-schemes-btn" type="button" aria-label="{{ __('portal.popup_new_schemes_title') }}" title="{{ __('portal.popup_new_schemes_title') }}">
    <span class="alf-floating-ping" aria-hidden="true"></span>
    <i class="fa-solid fa-bullhorn me-2 text-warning" aria-hidden="true"></i>
    <span class="d-none d-md-inline">{{ __('portal.popup_new_schemes_title') }}</span>
    <span class="badge bg-white text-dark ms-1 rounded-pill">{{ $newSchemes->count() }}</span>
</button>
@endif
