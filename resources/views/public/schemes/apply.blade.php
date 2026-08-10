@extends('layouts.public')

@section('title', __('portal.apply').' — '.$scheme->t("title"))

@section('content')
<section class="breadcrumb-area alf-media-banner bg-img bg-overlay jarallax"
         data-alf-fit="bg"
         data-alf-w="{{ \App\Support\ImageSlots::get('breadcrumb')['width'] }}"
         data-alf-h="{{ \App\Support\ImageSlots::get('breadcrumb')['height'] }}"
         style="background-image: url('{{ $scheme->bannerUrl(9) }}');">
    <div class="container h-100">
        <div class="row h-100 align-items-center">
            <div class="col-12">
                <div class="breadcrumb-text text-center">
                    <h2 class="text-white">{{ __('portal.apply_online') }}</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center">
                            <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-white">{{ __('portal.home') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('schemes.show', $scheme->slug) }}" class="text-white">{{ \Illuminate\Support\Str::limit($scheme->t("title"), 30) }}</a></li>
                            <li class="breadcrumb-item active text-white" aria-current="page">{{ __('portal.apply') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-padding-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="mb-4">
                    <h3>{{ $scheme->t("title") }}</h3>
                    <p class="text-muted mb-1">{{ $scheme->department?->t("name") }} · {{ __('portal.form_label') }}: {{ $form->title }} (v{{ $form->version }})</p>
                    @if($scheme->last_date)
                        <p class="small">{{ __('portal.application_deadline') }}: <strong>{{ $scheme->last_date->format('d M Y') }}</strong></p>
                    @endif
                </div>

                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if(session('dev_otp'))
                    <div class="alert alert-warning">
                        <strong>{{ __('portal.dev_otp') }}:</strong> {{ session('dev_otp') }}
                        @if(session('dev_otp_phone'))
                            <span class="text-muted">({{ session('dev_otp_phone') }})</span>
                        @endif
                    </div>
                @endif

                <form id="applyForm" method="post" action="{{ route('schemes.apply.store', $scheme->slug) }}" enctype="multipart/form-data" novalidate>
                    @csrf

                    <div class="border rounded-3 p-4 mb-4 bg-white shadow-sm">
                        <h5 class="mb-3">{{ __('portal.applicant_details') }}</h5>
                        <div class="row g-3">
                            @foreach($form->fields as $field)
                                @php
                                    $name = $field->field_key;
                                    $old = old($name);
                                    $required = $field->is_mandatory;
                                    $options = $field->options_json ?? [];
                                @endphp

                                <div class="col-md-{{ in_array($field->field_type, ['textarea', 'checkbox'], true) ? '12' : '6' }}">
                                    <label class="form-label" for="field_{{ $name }}">
                                        {{ $field->field_label }}
                                        @if($required)<span class="text-danger">*</span>@endif
                                    </label>

                                    @if($field->field_type === 'textarea')
                                        <textarea id="field_{{ $name }}" name="{{ $name }}" rows="3"
                                                  class="form-control @error($name) is-invalid @enderror"
                                                  placeholder="{{ $field->placeholder }}"
                                                  @if($required) required @endif>{{ $old }}</textarea>

                                    @elseif($field->field_type === 'number')
                                        <input type="number" step="any" id="field_{{ $name }}" name="{{ $name }}"
                                               class="form-control @error($name) is-invalid @enderror"
                                               value="{{ $old }}" placeholder="{{ $field->placeholder }}"
                                               @if($required) required @endif>

                                    @elseif($field->field_type === 'cnic')
                                        <input type="text" id="field_{{ $name }}" name="{{ $name }}"
                                               class="form-control js-cnic @error($name) is-invalid @enderror"
                                               value="{{ $old }}" maxlength="15" inputmode="numeric"
                                               placeholder="{{ $field->placeholder ?: __('portal.cnic_hint') }}"
                                               @if($required) required @endif>
                                        <div class="form-text">{{ __('portal.cnic_digits_hint') }}</div>

                                    @elseif($field->field_type === 'phone')
                                        <div class="input-group">
                                            <input type="text" id="field_{{ $name }}" name="{{ $name }}"
                                                   class="form-control js-phone @error($name) is-invalid @enderror"
                                                   value="{{ $old }}" placeholder="{{ $field->placeholder ?: '03XXXXXXXXX' }}"
                                                   @if($required) required @endif>
                                            <button type="button" class="btn btn-outline-secondary" id="btnSendOtp">{{ __('portal.send_otp') }}</button>
                                        </div>
                                        <div class="row g-2 mt-2" id="otpBox">
                                            <div class="col-sm-6">
                                                <input type="text" id="otpCode" class="form-control" maxlength="6" placeholder="{{ __('portal.enter_otp') }}" inputmode="numeric">
                                            </div>
                                            <div class="col-sm-6">
                                                <button type="button" class="btn btn-success w-100" id="btnVerifyOtp">{{ __('portal.verify_otp') }}</button>
                                            </div>
                                        </div>
                                        <div id="otpStatus" class="small mt-2 @if($phoneVerified) text-success @endif">
                                            @if($phoneVerified)
                                                {{ __('portal.phone_verified', ['phone' => $phoneVerified]) }}
                                            @else
                                                {{ __('portal.phone_must_verify') }}
                                            @endif
                                        </div>
                                        <div id="devOtpFlash" class="alert alert-warning mt-2 d-none"></div>

                                    @elseif($field->field_type === 'district')
                                        <select id="field_{{ $name }}" name="{{ $name }}"
                                                class="form-select @error($name) is-invalid @enderror"
                                                @if($required) required @endif>
                                            <option value="">{{ __('portal.select_district') }}</option>
                                            @foreach($districts as $district)
                                                <option value="{{ $district->id }}" @selected((string) $old === (string) $district->id)>
                                                    {{ $district->t("name") }}
                                                </option>
                                            @endforeach
                                        </select>

                                    @elseif($field->field_type === 'select')
                                        <select id="field_{{ $name }}" name="{{ $name }}"
                                                class="form-select @error($name) is-invalid @enderror"
                                                @if($required) required @endif>
                                            <option value="">{{ __('portal.select') }}</option>
                                            @foreach($options as $option)
                                                <option value="{{ $option }}" @selected($old === $option)>{{ $option }}</option>
                                            @endforeach
                                        </select>

                                    @elseif($field->field_type === 'radio')
                                        <div>
                                            @foreach($options as $option)
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="{{ $name }}"
                                                           id="field_{{ $name }}_{{ $loop->index }}" value="{{ $option }}"
                                                           @checked($old === $option) @if($required) required @endif>
                                                    <label class="form-check-label" for="field_{{ $name }}_{{ $loop->index }}">{{ $option }}</label>
                                                </div>
                                            @endforeach
                                        </div>

                                    @elseif($field->field_type === 'checkbox')
                                        <div>
                                            @foreach($options as $option)
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="{{ $name }}[]"
                                                           id="field_{{ $name }}_{{ $loop->index }}" value="{{ $option }}"
                                                           @checked(is_array($old) && in_array($option, $old, true))>
                                                    <label class="form-check-label" for="field_{{ $name }}_{{ $loop->index }}">{{ $option }}</label>
                                                </div>
                                            @endforeach
                                        </div>

                                    @elseif($field->field_type === 'switch')
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                   id="field_{{ $name }}" name="{{ $name }}" value="Yes"
                                                   @checked($old === 'Yes' || $old === '1' || $old === true)>
                                            <label class="form-check-label" for="field_{{ $name }}">{{ $options[0] ?? 'Yes' }}</label>
                                        </div>

                                    @else
                                        <input type="text" id="field_{{ $name }}" name="{{ $name }}"
                                               class="form-control @error($name) is-invalid @enderror"
                                               value="{{ $old }}" placeholder="{{ $field->placeholder }}"
                                               @if($required) required @endif>
                                    @endif

                                    @if($field->help_text)
                                        <div class="form-text">{{ $field->help_text }}</div>
                                    @endif
                                    @error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            @endforeach
                        </div>
                    </div>

                    @if($form->documents->isNotEmpty())
                        <div class="border rounded-3 p-4 mb-4 bg-white shadow-sm">
                            <h5 class="mb-3">{{ __('portal.required_documents') }}</h5>
                            <div class="row g-3">
                                @foreach($form->documents as $document)
                                    @php $inputName = 'documents['.$document->id.']'; @endphp
                                    <div class="col-12">
                                        <label class="form-label">
                                            {{ $document->document_name }}
                                            @if($document->is_mandatory)<span class="text-danger">*</span>@endif
                                        </label>
                                        <div class="dropzone-box border border-2 border-dashed rounded-3 p-4 text-center"
                                             data-input="doc_input_{{ $document->id }}"
                                             data-progress="doc_progress_{{ $document->id }}"
                                             data-filename="doc_filename_{{ $document->id }}">
                                            <p class="mb-2"><i class="fa-solid fa-cloud-arrow-up fa-2x text-muted"></i></p>
                                            <p class="mb-1">{{ __('portal.drag_drop_browse') }} <button type="button" class="btn btn-link p-0 align-baseline js-browse">{{ __('portal.browse') }}</button></p>
                                            <p class="small text-muted mb-0">
                                                {{ __('portal.allowed_files', ['ext' => $document->allowed_extensions, 'size' => $document->max_size_mb]) }}
                                            </p>
                                            <input type="file"
                                                   id="doc_input_{{ $document->id }}"
                                                   name="{{ $inputName }}"
                                                   class="d-none doc-file"
                                                   accept=".{{ str_replace(',', ',.', $document->allowed_extensions) }}"
                                                   @if($document->is_mandatory) required @endif>
                                            <div class="progress mt-3 d-none" id="doc_progress_{{ $document->id }}" style="height: 8px;">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: 0%"></div>
                                            </div>
                                            <div class="small mt-2 text-success" id="doc_filename_{{ $document->id }}"></div>
                                        </div>
                                        @error('documents.'.$document->id)
                                            <div class="text-danger small mt-1">{{ $message }}</div>
                                        @enderror
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="mb-3">
                        <div class="progress d-none" id="formUploadProgress" style="height: 12px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%">0%</div>
                        </div>
                        <div id="formUploadError" class="alert alert-danger mt-3 d-none"></div>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn famie-btn" id="btnSubmitApply">{{ __('portal.submit_application') }}</button>
                        <a href="{{ route('schemes.show', $scheme->slug) }}" class="btn btn-outline-secondary">{{ __('portal.cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
@endsection

@push('styles')
<style>
.dropzone-box { cursor: pointer; background: #fafafa; transition: border-color .2s, background .2s; }
.dropzone-box.dragover { border-color: #198754 !important; background: #edf7f0; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var phoneVerified = @json((bool) $phoneVerified);
    var verifiedPhone = @json($phoneVerified);
    var i18n = @json([
        'otp_phone_changed' => __('portal.otp_phone_changed'),
        'otp_enter_phone' => __('portal.otp_enter_phone'),
        'otp_sent' => __('portal.otp_sent'),
        'otp_send_failed' => __('portal.otp_send_failed'),
        'otp_network_send' => __('portal.otp_network_send'),
        'otp_enter_both' => __('portal.otp_enter_both'),
        'otp_invalid' => __('portal.otp_invalid'),
        'otp_verified' => __('portal.otp_verified'),
        'otp_network_verify' => __('portal.otp_network_verify'),
        'otp_verify_before_submit' => __('portal.otp_verify_before_submit'),
        'cnic_must_13' => __('portal.cnic_must_13'),
        'submission_failed' => __('portal.submission_failed'),
        'dev_otp' => __('portal.dev_otp'),
    ]);

    function digitsOnly(value, max) {
        return String(value || '').replace(/\D/g, '').slice(0, max || 99);
    }

    // CNIC auto-format: keep 13 digits only
    document.querySelectorAll('.js-cnic').forEach(function (input) {
        input.addEventListener('input', function () {
            this.value = digitsOnly(this.value, 13);
        });
    });

    document.querySelectorAll('.js-phone').forEach(function (input) {
        input.addEventListener('input', function () {
            this.value = digitsOnly(this.value, 15);
            if (verifiedPhone && this.value !== verifiedPhone) {
                phoneVerified = false;
                document.getElementById('otpStatus').className = 'small mt-2 text-danger';
                document.getElementById('otpStatus').textContent = i18n.otp_phone_changed;
            }
        });
    });

    var phoneInput = document.querySelector('.js-phone');
    var btnSendOtp = document.getElementById('btnSendOtp');
    var btnVerifyOtp = document.getElementById('btnVerifyOtp');
    var otpStatus = document.getElementById('otpStatus');
    var devOtpFlash = document.getElementById('devOtpFlash');

    if (btnSendOtp) {
        btnSendOtp.addEventListener('click', function () {
            if (!phoneInput || phoneInput.value.length < 11) {
                otpStatus.className = 'small mt-2 text-danger';
                otpStatus.textContent = i18n.otp_enter_phone;
                return;
            }
            btnSendOtp.disabled = true;
            fetch(@json(route('otp.send')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                body: JSON.stringify({ phone: phoneInput.value })
            })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (res) {
                btnSendOtp.disabled = false;
                if (!res.ok) {
                    otpStatus.className = 'small mt-2 text-danger';
                    otpStatus.textContent = (res.d.message || (res.d.errors && res.d.errors.phone && res.d.errors.phone[0]) || i18n.otp_send_failed);
                    return;
                }
                otpStatus.className = 'small mt-2 text-primary';
                otpStatus.textContent = res.d.message || i18n.otp_sent;
                if (res.d.dev_otp) {
                    devOtpFlash.classList.remove('d-none');
                    devOtpFlash.innerHTML = '<strong>' + i18n.dev_otp + ':</strong> ' + res.d.dev_otp;
                }
            })
            .catch(function () {
                btnSendOtp.disabled = false;
                otpStatus.className = 'small mt-2 text-danger';
                otpStatus.textContent = i18n.otp_network_send;
            });
        });
    }

    if (btnVerifyOtp) {
        btnVerifyOtp.addEventListener('click', function () {
            var code = document.getElementById('otpCode').value.trim();
            if (!phoneInput || !code) {
                otpStatus.className = 'small mt-2 text-danger';
                otpStatus.textContent = i18n.otp_enter_both;
                return;
            }
            btnVerifyOtp.disabled = true;
            fetch(@json(route('otp.verify')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                body: JSON.stringify({ phone: phoneInput.value, otp: code })
            })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (res) {
                btnVerifyOtp.disabled = false;
                if (!res.ok || !res.d.success) {
                    phoneVerified = false;
                    otpStatus.className = 'small mt-2 text-danger';
                    otpStatus.textContent = res.d.message || i18n.otp_invalid;
                    return;
                }
                phoneVerified = true;
                verifiedPhone = res.d.phone || digitsOnly(phoneInput.value, 15);
                otpStatus.className = 'small mt-2 text-success';
                otpStatus.textContent = i18n.otp_verified;
            })
            .catch(function () {
                btnVerifyOtp.disabled = false;
                otpStatus.className = 'small mt-2 text-danger';
                otpStatus.textContent = i18n.otp_network_verify;
            });
        });
    }

    // Drag & drop file inputs
    document.querySelectorAll('.dropzone-box').forEach(function (zone) {
        var input = document.getElementById(zone.getAttribute('data-input'));
        var progress = document.getElementById(zone.getAttribute('data-progress'));
        var filename = document.getElementById(zone.getAttribute('data-filename'));
        var browse = zone.querySelector('.js-browse');

        function setFile(file) {
            if (!file || !input) return;
            var dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
            filename.textContent = file.name + ' (' + Math.round(file.size / 1024) + ' KB)';
            progress.classList.remove('d-none');
            var bar = progress.querySelector('.progress-bar');
            bar.style.width = '0%';
            // Visual readiness progress (actual upload progress is on form submit)
            var p = 0;
            var timer = setInterval(function () {
                p += 20;
                bar.style.width = Math.min(p, 100) + '%';
                if (p >= 100) clearInterval(timer);
            }, 40);
        }

        zone.addEventListener('click', function (e) {
            if (e.target.classList.contains('js-browse') || e.target === zone || e.target.closest('.dropzone-box')) {
                if (!e.target.closest('input')) input.click();
            }
        });
        if (browse) {
            browse.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                input.click();
            });
        }
        input.addEventListener('change', function () {
            if (this.files && this.files[0]) setFile(this.files[0]);
        });
        ['dragenter', 'dragover'].forEach(function (evt) {
            zone.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
                zone.classList.add('dragover');
            });
        });
        ['dragleave', 'drop'].forEach(function (evt) {
            zone.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
                zone.classList.remove('dragover');
            });
        });
        zone.addEventListener('drop', function (e) {
            var files = e.dataTransfer.files;
            if (files && files[0]) setFile(files[0]);
        });
    });

    // Submit via XHR with upload progress
    var form = document.getElementById('applyForm');
    var progressWrap = document.getElementById('formUploadProgress');
    var progressBar = progressWrap.querySelector('.progress-bar');
    var formError = document.getElementById('formUploadError');
    var submitBtn = document.getElementById('btnSubmitApply');

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        if (!phoneVerified) {
            formError.classList.remove('d-none');
            formError.textContent = i18n.otp_verify_before_submit;
            return;
        }

        var cnicEl = form.querySelector('.js-cnic');
        if (cnicEl && digitsOnly(cnicEl.value, 13).length !== 13) {
            formError.classList.remove('d-none');
            formError.textContent = i18n.cnic_must_13;
            return;
        }

        formError.classList.add('d-none');
        progressWrap.classList.remove('d-none');
        progressBar.style.width = '0%';
        progressBar.textContent = '0%';
        submitBtn.disabled = true;

        var xhr = new XMLHttpRequest();
        xhr.open('POST', form.action);
        xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.upload.addEventListener('progress', function (evt) {
            if (!evt.lengthComputable) return;
            var pct = Math.round((evt.loaded / evt.total) * 100);
            progressBar.style.width = pct + '%';
            progressBar.textContent = pct + '%';
        });

        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;
            submitBtn.disabled = false;
            var data = {};
            try { data = JSON.parse(xhr.responseText || '{}'); } catch (err) {}

            if (xhr.status >= 200 && xhr.status < 300 && data.redirect) {
                progressBar.style.width = '100%';
                progressBar.textContent = '100%';
                window.location.href = data.redirect;
                return;
            }

            var msg = data.message || i18n.submission_failed;
            if (data.errors) {
                msg = Object.keys(data.errors).map(function (k) { return data.errors[k].join(' '); }).join(' ');
            }
            formError.classList.remove('d-none');
            formError.textContent = msg;
        };

        xhr.send(new FormData(form));
    });
})();
</script>
@endpush
