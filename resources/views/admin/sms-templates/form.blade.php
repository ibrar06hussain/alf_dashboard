@extends('layouts.admin')

@php
    $isEdit = $template->exists;
    $title = $isEdit ? "Edit SMS Template: {$template->name}" : 'Create SMS Notification Template';
@endphp

@section('title', $title)

@section('content')
@include('admin.partials.page-hero', [
    'title' => $title,
    'subtitle' => 'Configure bilingual (English & Urdu) SMS templates with dynamic applicant, application ID, CNIC password, bag count, and GBPay bill variables.',
    'icon' => 'fa-solid fa-comment-sms',
    'actions' => [
        ['label' => 'Back to SMS Templates', 'url' => route('admin.sms-templates.index'), 'class' => 'btn btn-outline-secondary'],
    ],
])

<form action="{{ $isEdit ? route('admin.sms-templates.update', $template) : route('admin.sms-templates.store') }}" method="POST">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="row g-3">
        {{-- Left Column: Template Settings & Bilingual Editors --}}
        <div class="col-lg-8">
            <div class="card alf-panel shadow-sm mb-3">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-sliders text-primary me-2"></i> Trigger Status &amp; Scope Configuration
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Template Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name', $template->name) }}" placeholder="e.g. Application Approved & Challan Notification" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Application Status / Event Trigger <span class="text-danger">*</span></label>
                            <select name="event_key" id="event-key-select" class="form-select @error('event_key') is-invalid @enderror" required>
                                @foreach($eventLabels as $key => $label)
                                    <option value="{{ $key }}" @selected(old('event_key', $template->event_key) === $key)>
                                        {{ $label }} [{{ $key }}]
                                    </option>
                                @endforeach
                                @if($template->event_key && !array_key_exists($template->event_key, $eventLabels))
                                    <option value="{{ $template->event_key }}" selected>Custom Status: {{ $template->event_key }}</option>
                                @endif
                            </select>
                            @error('event_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Scheme Scope</label>
                            <select name="scheme_id" class="form-select @error('scheme_id') is-invalid @enderror">
                                <option value="">— All Schemes (Global Default Template) —</option>
                                @foreach($schemes as $s)
                                    <option value="{{ $s->id }}" @selected(old('scheme_id', $template->scheme_id) == $s->id)>
                                        {{ $s->title }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text small">Leave as "All Schemes" to apply globally, or pick a specific scheme to override.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">SMS Dispatch Language Mode <span class="text-danger">*</span></label>
                            <select name="send_language" id="send-language" class="form-select @error('send_language') is-invalid @enderror" required>
                                <option value="both" @selected(old('send_language', $template->send_language) === 'both')>
                                    Both English &amp; Urdu (Sends English `UniCode=0` + Urdu `UniCode=1`)
                                </option>
                                <option value="en" @selected(old('send_language', $template->send_language) === 'en')>
                                    English Only (`UniCode=0`, 160 chars/SMS)
                                </option>
                                <option value="ur" @selected(old('send_language', $template->send_language) === 'ur')>
                                    Urdu Only (`UniCode=1`, 70 chars/SMS)
                                </option>
                            </select>
                            @error('send_language')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Parallel English & Urdu Content Editors --}}
            <div class="card alf-panel shadow-sm mb-3">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-language text-success me-2"></i> Bilingual SMS Message Content (English &amp; Urdu)
                    </h6>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Insert target">
                        <input type="radio" class="btn-check" name="insert_target" id="target_both" value="both" checked>
                        <label class="btn btn-outline-primary" for="target_both">Insert into Active Box</label>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        {{-- English Editor --}}
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-bold mb-0" for="message_en">
                                    <span class="badge bg-primary me-1">EN</span> English SMS Content (`UniCode=0`)
                                </label>
                                <small class="text-muted" id="en-counter">0 / 160 chars (1 SMS)</small>
                            </div>
                            <textarea name="message_en" id="message_en" rows="5"
                                class="form-control @error('message_en') is-invalid @enderror"
                                placeholder="Dear {name}, your application {application_id} for {scheme}...">{{ old('message_en', $template->message_en) }}</textarea>
                            @error('message_en')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text small">Standard GSM_7 character set: 160 characters per SMS (max 12 SMS).</div>

                            <div class="mt-3 p-2 rounded bg-light border">
                                <div class="small fw-bold text-secondary mb-1">
                                    <i class="fa-solid fa-eye me-1"></i> Live English Preview:
                                </div>
                                <div id="preview-en" class="small text-dark" style="min-height: 48px; white-space: pre-wrap;"></div>
                            </div>
                        </div>

                        {{-- Urdu Editor --}}
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-bold mb-0" for="message_ur">
                                    <span class="badge bg-success me-1">UR</span> Urdu SMS Content (`UniCode=1` اردو)
                                </label>
                                <small class="text-muted" id="ur-counter">0 / 70 chars (1 SMS)</small>
                            </div>
                            <textarea name="message_ur" id="message_ur" rows="5" dir="rtl" lang="ur"
                                class="form-control @error('message_ur') is-invalid @enderror"
                                style="font-size: 15px; line-height: 1.7; font-family: 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', Tahoma, sans-serif;"
                                placeholder="محترم {name}، آپ کی درخواست {application_id}...">{{ old('message_ur', $template->message_ur) }}</textarea>
                            @error('message_ur')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text small">Urdu Unicode (`UniCode=1`): 70 characters per SMS (max 11 SMS).</div>

                            <div class="mt-3 p-2 rounded bg-light border">
                                <div class="small fw-bold text-secondary mb-1">
                                    <i class="fa-solid fa-eye me-1"></i> Live Urdu Preview (اردو):
                                </div>
                                <div id="preview-ur" class="small text-dark" dir="rtl" lang="ur" style="min-height: 48px; white-space: pre-wrap; font-size: 14px; line-height: 1.7;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right Column: Click-to-Insert Dynamic Placeholders & Save Actions --}}
        <div class="col-lg-4">
            <div class="card alf-panel shadow-sm mb-3 border-primary-subtle">
                <div class="card-header bg-light py-3 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-code text-primary me-2"></i> Dynamic Variables (Click to Insert)
                    </h6>
                    <small class="text-muted d-block mt-1">
                        Click any variable badge below to insert it into the currently focused English or Urdu message box:
                    </small>
                </div>
                <div class="card-body p-2" style="max-height: 430px; overflow-y: auto;">
                    <div class="d-flex flex-wrap gap-1 mb-2 p-1">
                        @foreach($placeholders as $token => $meta)
                            <button type="button"
                                class="btn btn-xs btn-outline-primary py-1 px-2 js-insert-token text-start"
                                data-token="{{ $token }}"
                                title="{{ $meta['description'] }} (Sample: {{ $meta['sample_en'] }})"
                                style="font-size: 11.5px;">
                                <code>{{ $token }}</code>
                                <span class="text-dark ms-1">{{ $meta['label'] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card alf-panel shadow-sm">
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                            @checked(old('is_active', $template->is_active ?? true))>
                        <label class="form-check-label fw-bold" for="is_active">Template Active</label>
                        <div class="form-text small">Automatically send this SMS whenever an application enters this status.</div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-bold mb-2">
                        <i class="fa-solid fa-floppy-disk me-1"></i> {{ $isEdit ? 'Update SMS Template' : 'Save SMS Template' }}
                    </button>
                    <a href="{{ route('admin.sms-templates.index') }}" class="btn btn-light border w-100">
                        Cancel
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const enBox = document.getElementById('message_en');
    const urBox = document.getElementById('message_ur');
    const enCounter = document.getElementById('en-counter');
    const urCounter = document.getElementById('ur-counter');
    const previewEn = document.getElementById('preview-en');
    const previewUr = document.getElementById('preview-ur');

    const sampleEn = @json(collect($placeholders)->mapWithKeys(fn($v, $k) => [$k => $v['sample_en']]));
    const sampleUr = @json(collect($placeholders)->mapWithKeys(fn($v, $k) => [$k => $v['sample_ur']]));

    let activeTextarea = enBox;

    if (enBox) {
        enBox.addEventListener('focus', () => { activeTextarea = enBox; });
        enBox.addEventListener('input', updateStatsAndPreview);
    }
    if (urBox) {
        urBox.addEventListener('focus', () => { activeTextarea = urBox; });
        urBox.addEventListener('input', updateStatsAndPreview);
    }

    function interpolateSample(text, map) {
        if (!text) return '—';
        let out = text;
        Object.keys(map).forEach(function (k) {
            out = out.split(k).join(map[k]);
        });
        return out;
    }

    function updateStatsAndPreview() {
        if (enBox && enCounter) {
            const len = enBox.value.length;
            const segments = len === 0 ? 0 : Math.ceil(len / 160);
            enCounter.textContent = `${len} chars (${segments} / 12 SMS)`;
            enCounter.className = segments > 12 ? 'text-danger fw-bold small' : 'text-muted small';
            if (previewEn) {
                previewEn.textContent = interpolateSample(enBox.value, sampleEn);
            }
        }
        if (urBox && urCounter) {
            const len = urBox.value.length;
            const segments = len === 0 ? 0 : Math.ceil(len / 70);
            urCounter.textContent = `${len} chars (${segments} / 11 SMS)`;
            urCounter.className = segments > 11 ? 'text-danger fw-bold small' : 'text-muted small';
            if (previewUr) {
                previewUr.textContent = interpolateSample(urBox.value, sampleUr);
            }
        }
    }

    document.querySelectorAll('.js-insert-token').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const token = this.getAttribute('data-token');
            const target = activeTextarea || enBox;
            if (!target) return;

            const start = target.selectionStart || 0;
            const end = target.selectionEnd || 0;
            const val = target.value;
            target.value = val.substring(0, start) + token + val.substring(end);
            target.focus();
            target.selectionStart = target.selectionEnd = start + token.length;
            updateStatsAndPreview();
        });
    });

    updateStatsAndPreview();
});
</script>
@endpush

