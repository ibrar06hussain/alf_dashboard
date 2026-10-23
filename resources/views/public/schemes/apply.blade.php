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

                <form id="applyForm" method="post" action="{{ route('schemes.apply.store', $scheme->slug) }}" enctype="multipart/form-data" novalidate>
                    @csrf

                    <div class="border rounded-3 p-4 mb-4 bg-white shadow-sm">
                        <div class="d-flex justify-content-between align-items-baseline mb-3 pb-2 border-bottom flex-wrap gap-2">
                            <h5 class="mb-0 text-dark fw-bold">{{ __('portal.applicant_details') }}</h5>
                            <span class="text-success fw-bold alf-urdu-heading" dir="rtl">
                                درخواست گزار کی تفصیلات
                            </span>
                        </div>
                        <div class="row g-3">
                            @foreach($form->fields as $field)
                                @php
                                    $name = $field->field_key;
                                    $old = old($name);
                                    $required = $field->is_mandatory;
                                    $options = $field->options_json ?? [];
                                    $isLocationField = in_array($field->field_type, ['district', 'tehsil', 'union_council'], true)
                                        || in_array($name, ['district_id', 'tehsil', 'union_council', 'village'], true);

                                    if (in_array($field->field_type, ['textarea', 'checkbox', 'checkbox_with_text'], true)) {
                                        $colClass = 'col-12';
                                    } elseif ($isLocationField) {
                                        $colClass = 'col-12 col-md-6 col-lg-3';
                                    } else {
                                        $colClass = 'col-12 col-md-6';
                                    }

                                    $urduLabel = $field->getUrduLabel();
                                    $urduPlaceholder = $field->getUrduPlaceholder();
                                    $englishPlaceholder = $field->placeholder ?: '';
                                    if ($urduPlaceholder && $englishPlaceholder) {
                                        $fieldPlaceholder = $englishPlaceholder . ' / ' . $urduPlaceholder;
                                    } else {
                                        $fieldPlaceholder = $englishPlaceholder ?: ($urduPlaceholder ?: '');
                                    }
                                @endphp

                                <div class="{{ $colClass }}">
                                    <div class="field-label-wrapper">
                                        <label class="field-label-en" for="field_{{ $name }}" id="label_{{ $name }}">
                                            {{ $field->field_label }}
                                            @if($required)<span class="text-danger fw-bold ms-0" id="req_{{ $name }}">*</span>@endif
                                        </label>
                                        @if($urduLabel)
                                            <span class="field-label-ur" dir="rtl" id="label_ur_{{ $name }}">
                                                {{ $urduLabel }}
                                                @if($required)<span class="req-star">*</span>@endif
                                            </span>
                                        @endif
                                    </div>

                                    @if($field->field_type === 'textarea')
                                        <textarea id="field_{{ $name }}" name="{{ $name }}" rows="3"
                                                  class="form-control @error($name) is-invalid @enderror"
                                                  placeholder="{{ $fieldPlaceholder }}"
                                                  @if($required) required @endif>{{ $old }}</textarea>

                                    @elseif($field->field_type === 'number')
                                        @php
                                            $isBagsField = in_array($name, ['bags_required', 'seed_bags_required'], true);
                                        @endphp
                                        <input type="number" 
                                               step="{{ $isBagsField ? '1' : 'any' }}" 
                                               @if($isBagsField) min="1" @endif
                                               id="field_{{ $name }}" 
                                               name="{{ $name }}"
                                               class="form-control @error($name) is-invalid @enderror {{ $isBagsField ? 'js-seed-bags-input' : ($name === 'land_kanals' ? 'js-land-kanals-input' : '') }}"
                                               value="{{ $old }}" 
                                               placeholder="{{ $fieldPlaceholder }}"
                                               @if($required) required @endif>
                                        @if($isBagsField)
                                            <div id="bags_limit_feedback_{{ $name }}" class="small mt-1 text-muted js-bags-limit-feedback">
                                                <i class="fa-solid fa-calculator text-primary me-1"></i>
                                                <span class="js-bags-limit-text">
                                                    {{ __('portal.bags_limit_help', ['max' => '...', 'rate' => 4]) }}
                                                </span>
                                            </div>
                                            <div id="bags_dynamic_error_{{ $name }}" class="invalid-feedback d-none js-bags-dynamic-error"></div>
                                        @endif

                                    @elseif($field->field_type === 'cnic')
                                        <div class="input-group cnic-input-group">
                                            <input type="text" id="field_{{ $name }}" name="{{ $name }}"
                                                   class="form-control js-cnic @error($name) is-invalid @enderror"
                                                   value="{{ $old }}" maxlength="15" inputmode="numeric"
                                                   placeholder="{{ $fieldPlaceholder ?: __('portal.cnic_hint') }}"
                                                   @if($required) required @endif
                                                   autocomplete="off">
                                            <button type="button" class="btn btn-outline-success js-verify-cnic-btn"
                                                    id="btn_verify_{{ $name }}" data-target="field_{{ $name }}"
                                                    data-status="cnic_verification_status_{{ $name }}"
                                                    title="{{ __('portal.verify') }}">
                                                <i class="fa-solid fa-shield-halved me-1"></i>
                                                <span class="btn-text">{{ __('portal.verify') }}</span>
                                            </button>
                                        </div>
                                        <div id="cnic_verification_status_{{ $name }}" class="cnic-status-box mt-2 d-none"></div>
                                        <div class="field-help-wrapper">
                                            <span class="form-text small mb-0">{{ __('portal.cnic_digits_hint') }}</span>
                                            <span dir="rtl" class="field-help-ur">13 ہندسے بغیر ڈیش کے لکھیں</span>
                                        </div>

                                    @elseif($field->field_type === 'phone')
                                        <input type="tel" id="field_{{ $name }}" name="{{ $name }}"
                                               class="form-control js-phone @error($name) is-invalid @enderror"
                                               value="{{ $old }}" placeholder="{{ $fieldPlaceholder ?: '03XXXXXXXXX' }}"
                                               maxlength="15" inputmode="tel"
                                               @if($required) required @endif>

                                    @elseif($field->field_type === 'district' || $name === 'district_id')
                                        <select id="field_{{ $name }}" name="{{ $name }}"
                                                class="form-select js-cascading-district @error($name) is-invalid @enderror"
                                                data-placeholder="{{ __('portal.select_district') }}"
                                                @if($required) required @endif>
                                            <option value="" disabled {{ $old ? '' : 'selected' }} hidden>{{ __('portal.select_district') }}</option>
                                            @foreach($districts as $district)
                                                <option value="{{ $district->id }}" @selected((string) $old === (string) $district->id)>
                                                    {{ $district->t("name") }}
                                                </option>
                                            @endforeach
                                        </select>

                                    @elseif($field->field_type === 'tehsil' || $name === 'tehsil')
                                        <select id="field_{{ $name }}" name="{{ $name }}"
                                                class="form-select js-cascading-tehsil @error($name) is-invalid @enderror"
                                                data-old="{{ $old }}"
                                                data-placeholder="{{ __('portal.select_tehsil') }}"
                                                data-empty-placeholder="{{ __('portal.select_district_first') }}"
                                                @if($required) required @endif
                                                disabled>
                                            <option value="" disabled {{ $old ? '' : 'selected' }} hidden>{{ __('portal.select_district_first') }}</option>
                                        </select>

                                    @elseif($field->field_type === 'union_council' || $name === 'union_council')
                                        <select id="field_{{ $name }}" name="{{ $name }}"
                                                class="form-select js-cascading-uc @error($name) is-invalid @enderror"
                                                data-old="{{ $old }}"
                                                data-placeholder="{{ __('portal.select_union_council') }}"
                                                data-empty-placeholder="{{ __('portal.select_tehsil_first') }}"
                                                data-no-uc-msg="{{ __('portal.no_union_councils') }}"
                                                @if($required) required @endif
                                                disabled>
                                            <option value="" disabled {{ $old ? '' : 'selected' }} hidden>{{ __('portal.select_tehsil_first') }}</option>
                                        </select>
                                        <div id="ucHelpText" class="form-text small mt-1 d-none"></div>

                                    @elseif($field->field_type === 'select')
                                        <select id="field_{{ $name }}" name="{{ $name }}"
                                                class="form-select js-custom-select @error($name) is-invalid @enderror"
                                                data-placeholder="{{ $fieldPlaceholder ?: __('portal.select') }}"
                                                @if($required) required @endif>
                                            <option value="" disabled {{ $old ? '' : 'selected' }} hidden>{{ $fieldPlaceholder ?: __('portal.select') }}</option>
                                            @foreach(collect($options)->sort(fn($a,$b) => class_exists(\Collator::class) ? (new \Collator(app()->getLocale() === 'ur' ? 'ur_PK' : 'en_US'))->compare((string)$a, (string)$b) : strnatcasecmp((string)$a, (string)$b))->values() as $option)
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

                                    @elseif($field->field_type === 'checkbox_with_text')
                                        @php
                                            $rawOld = old($name);
                                            $itemsState = [];
                                            if (is_array($rawOld)) {
                                                foreach ($rawOld as $k => $item) {
                                                    if (is_array($item)) {
                                                        $lbl = $item['label'] ?? null;
                                                        $isChecked = !empty($item['checked']);
                                                        $txt = $item['text'] ?? '';
                                                        if ($lbl !== null) {
                                                            $itemsState[$lbl] = ['checked' => $isChecked, 'text' => $txt];
                                                        }
                                                        $itemsState[$k] = ['checked' => $isChecked, 'text' => $txt];
                                                    }
                                                }
                                                if (isset($rawOld['checked'])) {
                                                    $itemsState['__single__'] = ['checked' => !empty($rawOld['checked']), 'text' => $rawOld['text'] ?? ''];
                                                }
                                            } elseif (is_string($rawOld) && $rawOld !== '') {
                                                $parts = array_map('trim', explode(';', $rawOld));
                                                foreach ($parts as $part) {
                                                    if (str_contains($part, ':')) {
                                                        [$pLabel, $pText] = array_map('trim', explode(':', $part, 2));
                                                        $itemsState[$pLabel] = ['checked' => true, 'text' => $pText];
                                                    } else {
                                                        $itemsState[$part] = ['checked' => true, 'text' => ''];
                                                    }
                                                }
                                            }

                                            $fieldOptions = !empty($options) ? $options : [$field->field_label ?: __('portal.yes')];
                                        @endphp
                                        <div class="checkbox-with-text-group">
                                            @foreach($fieldOptions as $opt)
                                                @php
                                                    $itemState = $itemsState[$opt] ?? ($itemsState[$loop->index] ?? ($itemsState['__single__'] ?? ['checked' => false, 'text' => '']));
                                                    $isChecked = !empty($itemState['checked']);
                                                    $textVal = $itemState['text'] ?? '';
                                                    $optId = 'field_' . $name . '_' . $loop->index;
                                                @endphp
                                                <div class="checkbox-with-text-box border rounded-3 p-3 mb-2 bg-light shadow-xs">
                                                    <div class="form-check d-flex align-items-center gap-2 mb-0">
                                                        <input type="hidden" name="{{ $name }}[{{ $loop->index }}][label]" value="{{ $opt }}">
                                                        <input class="form-check-input js-toggle-text-field"
                                                               type="checkbox"
                                                               name="{{ $name }}[{{ $loop->index }}][checked]"
                                                               id="{{ $optId }}_check"
                                                               value="1"
                                                               data-target="#{{ $optId }}_text_wrap"
                                                               data-input="#{{ $optId }}_text_input"
                                                               @checked($isChecked)>
                                                        <label class="form-check-label fw-semibold text-dark mb-0 user-select-none" for="{{ $optId }}_check" style="cursor:pointer;">
                                                            {{ $opt }}
                                                        </label>
                                                    </div>

                                                    <div id="{{ $optId }}_text_wrap" class="conditional-text-container mt-2 pt-2 border-top {{ $isChecked ? '' : 'd-none' }}">
                                                        <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-2">
                                                            <label for="{{ $optId }}_text_input" class="form-label small fw-semibold text-muted mb-0">
                                                                <span>{{ $englishPlaceholder ?: ('Please provide details for ' . $opt) }}</span>
                                                                <span class="text-danger">*</span>
                                                            </label>
                                                            @if($urduPlaceholder)
                                                                <span class="small text-success urdu-font" dir="rtl">{{ $urduPlaceholder }}</span>
                                                            @endif
                                                        </div>
                                                        <input type="text"
                                                               id="{{ $optId }}_text_input"
                                                               name="{{ $name }}[{{ $loop->index }}][text]"
                                                               class="form-control @error($name.'.'.$loop->index.'.text') is-invalid @enderror @error($name) is-invalid @enderror"
                                                               placeholder="{{ $fieldPlaceholder ?: ('Enter details for ' . $opt . '...') }}"
                                                               value="{{ $textVal }}"
                                                               {{ $isChecked ? '' : 'disabled' }}>
                                                        @error($name.'.'.$loop->index.'.text')
                                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                                        @enderror
                                                    </div>
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
                                               value="{{ $old }}"
                                               placeholder="{{ $fieldPlaceholder }}"
                                               @if($required) required @endif>
                                    @endif

                                    @if($field->help_text || $field->help_text_ur)
                                        <div class="field-help-wrapper">
                                            @if($field->help_text)
                                                <div class="form-text small mb-0">{{ $field->help_text }}</div>
                                            @endif
                                            @if($field->help_text_ur)
                                                <div dir="rtl" class="field-help-ur">{{ $field->help_text_ur }}</div>
                                            @endif
                                        </div>
                                    @endif
                                    @error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            @endforeach
                        </div>
                    </div>

                    @if($form->documents->isNotEmpty())
                        <div class="border rounded-3 p-4 mb-4 bg-white shadow-sm">
                            <div class="d-flex justify-content-between align-items-baseline mb-3 pb-2 border-bottom flex-wrap gap-2">
                                <h5 class="mb-0 text-dark fw-bold">{{ __('portal.required_documents') }}</h5>
                                <span class="text-success fw-bold alf-urdu-heading" dir="rtl">
                                    مطلوبہ دستاویزات
                                </span>
                            </div>
                            <div class="row g-3">
                                @foreach($form->documents as $document)
                                    @php
                                        $inputName = 'documents['.$document->id.']';
                                        $urduDocName = $document->getUrduName();
                                    @endphp
                                    <div class="col-12">
                                        <div class="field-label-wrapper">
                                            <label class="field-label-en" for="doc_input_{{ $document->id }}">
                                                {{ $document->document_name }}
                                                @if($document->is_mandatory)<span class="text-danger fw-bold ms-0">*</span>@endif
                                            </label>
                                            @if($urduDocName)
                                                <span class="field-label-ur" dir="rtl">
                                                    {{ $urduDocName }}
                                                    @if($document->is_mandatory)<span class="req-star">*</span>@endif
                                                </span>
                                            @endif
                                        </div>
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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('frontend/css/tom-select.bootstrap5.min.css') }}">
<style>
.field-label-wrapper {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-bottom: 0.35rem;
}
.field-label-en {
    font-weight: 600;
    color: #1f2937;
    font-size: 0.95rem;
    margin-bottom: 0;
}
.field-label-ur {
    font-family: 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', Tahoma, sans-serif;
    font-weight: 700;
    font-size: 1.02rem;
    color: #276722;
    line-height: 1.8;
    text-align: right;
    letter-spacing: 0;
}
.field-label-ur .req-star {
    color: #dc3545;
    margin-right: 3px;
    font-family: sans-serif;
}
.field-help-wrapper {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-top: 0.25rem;
}
.field-help-ur {
    font-family: 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', Tahoma, sans-serif;
    font-size: 0.88rem;
    color: #6b7280;
    line-height: 1.7;
    margin-left: auto;
}
.alf-urdu-heading {
    font-family: 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', Tahoma, sans-serif;
    line-height: 1.8;
}
@media (max-width: 576px) {
    .field-label-wrapper {
        flex-direction: row;
        align-items: center;
    }
    .field-label-ur {
        font-size: 0.95rem;
    }
}
.dropzone-box { cursor: pointer; background: #fafafa; transition: border-color .2s, background .2s; }
.dropzone-box.dragover { border-color: #198754 !important; background: #edf7f0; }

/* TomSelect Integration & Custom Styling matching ALF theme */
.ts-wrapper.form-select {
    padding: 0;
    border: none;
    box-shadow: none;
    background: transparent;
}
.ts-control {
    border-radius: 0.375rem !important;
    border: 1px solid #ced4da !important;
    padding: 0.5rem 0.75rem !important;
    font-size: 0.95rem !important;
    min-height: 44px !important;
    display: flex !important;
    align-items: center !important;
    background-color: #fff !important;
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.04);
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}
.ts-wrapper.focus .ts-control {
    border-color: #77b122 !important;
    box-shadow: 0 0 0 0.25rem rgba(119, 177, 34, 0.25) !important;
}
.ts-wrapper.disabled .ts-control {
    background-color: #e9ecef !important;
    opacity: 0.75;
    cursor: not-allowed !important;
    border-color: #dee2e6 !important;
}
.ts-dropdown {
    border-radius: 0.375rem !important;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    border: 1px solid #cfd5d8 !important;
    z-index: 1050 !important;
    margin-top: 4px;
}
.ts-dropdown .active {
    background-color: #f1f8e8 !important;
    color: #2e5a08 !important;
}
.ts-dropdown .selected {
    background-color: #77b122 !important;
    color: #fff !important;
}
.ts-dropdown .no-results {
    padding: 10px 14px;
    color: #6c757d;
    font-style: italic;
    font-size: 0.9rem;
}
.ts-wrapper .ts-control input {
    font-size: 0.95rem;
}
.uc-empty-state-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 6px;
    background-color: #fff8e6;
    border: 1px solid #ffe299;
    color: #8c5b00;
    font-size: 0.85rem;
    margin-top: 6px;
}
.cnic-status-box {
    border-radius: 8px;
    font-size: 0.88rem;
    transition: all 0.25s ease-in-out;
}
/* CNIC Input & Verify Button exact height, alignment, and border matching */
.cnic-input-group {
    display: flex !important;
    flex-wrap: nowrap !important;
    align-items: stretch !important;
    width: 100% !important;
    margin-bottom: 0 !important;
    position: relative !important;
}

/* Override theme style.css margin-bottom: 30px (line 11641) and lock exact 48px height */
.cnic-input-group > .form-control.js-cnic {
    height: 48px !important;
    min-height: 48px !important;
    max-height: 48px !important;
    margin-bottom: 0 !important;
    padding: 10px 16px !important;
    font-size: 0.95rem !important;
    line-height: 1.5 !important;
    box-sizing: border-box !important;
    flex: 1 1 auto !important;
    width: 1% !important;
    background-color: #ffffff !important;
}

.cnic-input-group > .js-verify-cnic-btn {
    height: 48px !important;
    min-height: 48px !important;
    max-height: 48px !important;
    margin: 0 !important;
    padding: 0 1.25rem !important;
    font-size: 0.95rem !important;
    font-weight: 600 !important;
    white-space: nowrap !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    box-sizing: border-box !important;
    cursor: pointer !important;
    z-index: 2 !important;
    transition: all 0.2s ease-in-out !important;
    line-height: 1 !important;
}

/* Default / Unverified button styling */
.cnic-input-group > .js-verify-cnic-btn.btn-outline-success {
    color: #276722 !important;
    border-color: #276722 !important;
    background-color: #f7faf4 !important;
}
.cnic-input-group > .js-verify-cnic-btn.btn-outline-success:hover,
.cnic-input-group > .js-verify-cnic-btn.btn-outline-success:focus {
    color: #ffffff !important;
    background-color: #276722 !important;
    border-color: #276722 !important;
}

/* Verified state styling */
.cnic-input-group > .js-verify-cnic-btn.btn-success {
    color: #ffffff !important;
    background-color: #198754 !important;
    border-color: #198754 !important;
}

/* Seamless corners for LTR (English) */
html:not([dir="rtl"]) .cnic-input-group > .form-control.js-cnic,
body:not(.locale-ur) .cnic-input-group > .form-control.js-cnic {
    border-top-left-radius: 4px !important;
    border-bottom-left-radius: 4px !important;
    border-top-right-radius: 0 !important;
    border-bottom-right-radius: 0 !important;
}

html:not([dir="rtl"]) .cnic-input-group > .js-verify-cnic-btn,
body:not(.locale-ur) .cnic-input-group > .js-verify-cnic-btn {
    border-top-left-radius: 0 !important;
    border-bottom-left-radius: 0 !important;
    border-top-right-radius: 4px !important;
    border-bottom-right-radius: 4px !important;
    margin-left: -1px !important;
}

/* Seamless corners for RTL (Urdu) */
html[dir="rtl"] .cnic-input-group > .form-control.js-cnic,
body.locale-ur .cnic-input-group > .form-control.js-cnic {
    border-top-right-radius: 4px !important;
    border-bottom-right-radius: 4px !important;
    border-top-left-radius: 0 !important;
    border-bottom-left-radius: 0 !important;
}

html[dir="rtl"] .cnic-input-group > .js-verify-cnic-btn,
body.locale-ur .cnic-input-group > .js-verify-cnic-btn {
    border-top-right-radius: 0 !important;
    border-bottom-right-radius: 0 !important;
    border-top-left-radius: 4px !important;
    border-bottom-left-radius: 4px !important;
    margin-right: -1px !important;
    margin-left: 0 !important;
    font-family: 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', Tahoma, sans-serif !important;
}
</style>
@endpush

@push('scripts')
<script src="{{ asset('frontend/js/tom-select.complete.min.js') }}"></script>
<script>
(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var urlCnicVerify = @json(route('cnic.verify'));
    var i18n = {!! json_encode([
        'cnic_must_13' => __('portal.cnic_must_13'),
        'submission_failed' => __('portal.submission_failed'),
        'select_district' => __('portal.select_district'),
        'select_tehsil' => __('portal.select_tehsil'),
        'select_union_council' => __('portal.select_union_council'),
        'select_district_first' => __('portal.select_district_first'),
        'select_tehsil_first' => __('portal.select_tehsil_first'),
        'loading_tehsils' => __('portal.loading_tehsils'),
        'loading_union_councils' => __('portal.loading_union_councils'),
        'no_union_councils' => __('portal.no_union_councils'),
        'no_results_found' => __('portal.no_results_found'),
        'verify' => __('portal.verify'),
        'verifying' => __('portal.verifying'),
        'verified' => __('portal.verified'),
        'cnic_verified' => __('portal.cnic_verified'),
        'cnic_verifying' => __('portal.cnic_verifying'),
        'cnic_not_found' => __('portal.cnic_not_found'),
        'cnic_must_verify' => __('portal.cnic_must_verify'),
        'cnic_service_unavailable' => __('portal.cnic_service_unavailable'),
        'cnic_verified_badge' => __('portal.cnic_verified_badge'),
        'manual_entry' => __('portal.manual_entry'),
        'auto_filled_hint' => __('portal.auto_filled_hint'),
    ], JSON_UNESCAPED_UNICODE) !!};


    function digitsOnly(value, max) {
        return String(value || '').replace(/\D/g, '').slice(0, max || 99);
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    // CNIC verification logic
    var lastVerifiedCnic = null;
    var cnicDebounceTimer = null;

    function resetCnicState(inputEl, btnEl, statusEl) {
        lastVerifiedCnic = null;
        if (inputEl) {
            inputEl.classList.remove('is-valid', 'is-invalid');
        }
        if (btnEl) {
            btnEl.disabled = false;
            btnEl.className = 'btn btn-outline-success js-verify-cnic-btn';
            btnEl.innerHTML = '<i class="fa-solid fa-shield-halved me-1"></i> <span class="btn-text">' + i18n.verify + '</span>';
        }
        if (statusEl) {
            statusEl.classList.add('d-none');
            statusEl.innerHTML = '';
        }
    }

    function verifyCnic(inputEl, btnEl, statusEl) {
        if (!inputEl) return;
        var rawVal = digitsOnly(inputEl.value, 13);
        if (rawVal.length !== 13) {
            if (statusEl) {
                statusEl.className = 'cnic-status-box alert alert-warning py-2 px-3 small mt-2 d-flex align-items-center gap-2 mb-1';
                statusEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-warning"></i> <span>' + i18n.cnic_must_13 + '</span>';
                statusEl.classList.remove('d-none');
            }
            return;
        }

        if (lastVerifiedCnic === rawVal && inputEl.classList.contains('is-valid')) {
            return;
        }

        if (btnEl) {
            btnEl.disabled = true;
            btnEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> <span class="btn-text">' + i18n.verify + '</span>';
        }

        if (statusEl) {
            statusEl.className = 'cnic-status-box alert alert-info py-2 px-3 small mt-2 d-flex align-items-center gap-2 mb-1';
            statusEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-primary"></i> <span>' + i18n.cnic_verifying + '</span>';
            statusEl.classList.remove('d-none');
        }

        fetch(urlCnicVerify, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ cnic: rawVal })
        })
        .then(function (res) {
            return res.json().then(function (data) {
                return { status: res.status, data: data };
            });
        })
        .then(function (result) {
            if (btnEl) btnEl.disabled = false;
            var data = result.data || {};

            if (result.status === 200 && (data.success === true || (data.data && typeof data.data === 'object'))) {
                lastVerifiedCnic = rawVal;
                inputEl.classList.remove('is-invalid');
                inputEl.classList.add('is-valid');

                if (btnEl) {
                    btnEl.className = 'btn btn-success js-verify-cnic-btn';
                    btnEl.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i> <span class="btn-text">' + i18n.verified + '</span>';
                }

                if (statusEl) {
                    statusEl.className = 'cnic-status-box alert alert-success py-2 px-3 small mt-2 mb-1';
                    statusEl.innerHTML = '<div class="d-flex align-items-center justify-content-between flex-wrap gap-2">' +
                        '<span><i class="fa-solid fa-circle-check text-success me-1"></i> <strong>' + i18n.cnic_verified + '</strong></span>' +
                        '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="fa-solid fa-shield-check me-1"></i>' + i18n.cnic_verified_badge + '</span>' +
                        '</div>';
                    statusEl.classList.remove('d-none');
                }
            } else {
                lastVerifiedCnic = null;
                inputEl.classList.remove('is-valid');
                inputEl.classList.add('is-invalid');
                if (btnEl) {
                    btnEl.className = 'btn btn-outline-success js-verify-cnic-btn';
                    btnEl.innerHTML = '<i class="fa-solid fa-shield-halved me-1"></i> <span class="btn-text">' + i18n.verify + '</span>';
                }
                var errMsg = data.message || i18n.cnic_not_found;
                if (statusEl) {
                    statusEl.className = 'cnic-status-box alert alert-danger py-2 px-3 small mt-2 d-flex align-items-center gap-2 mb-1';
                    statusEl.innerHTML = '<i class="fa-solid fa-circle-exclamation text-danger fs-5"></i> <span>' + escapeHtml(errMsg) + '</span>';
                    statusEl.classList.remove('d-none');
                }
            }

        })
        .catch(function (err) {
            console.error('CNIC verification error', err);
            if (btnEl) {
                btnEl.disabled = false;
                btnEl.className = 'btn btn-outline-success js-verify-cnic-btn';
                btnEl.innerHTML = '<i class="fa-solid fa-shield-halved me-1"></i> <span class="btn-text">' + i18n.verify + '</span>';
            }
            if (statusEl) {
                statusEl.className = 'cnic-status-box alert alert-warning py-2 px-3 small mt-2 d-flex align-items-center gap-2 mb-1';
                statusEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-warning"></i> <span>' + i18n.cnic_service_unavailable + '</span>';
                statusEl.classList.remove('d-none');
            }
        });
    }

    // Attach CNIC input & button handlers
    document.querySelectorAll('.js-cnic').forEach(function (input) {
        var parentCol = input.closest('.col-12, .col-md-6') || input.parentElement;
        var btn = parentCol.querySelector('.js-verify-cnic-btn');
        var statusBox = parentCol.querySelector('.cnic-status-box');

        input.addEventListener('input', function () {
            var oldVal = this.value;
            this.value = digitsOnly(this.value, 13);
            if (this.value !== lastVerifiedCnic) {
                resetCnicState(this, btn, statusBox);
            }
            clearTimeout(cnicDebounceTimer);
            if (this.value.length === 13) {
                cnicDebounceTimer = setTimeout(function () {
                    verifyCnic(input, btn, statusBox);
                }, 400);
            }
        });

        if (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                verifyCnic(input, btn, statusBox);
            });
        }
    });

    // Phone auto-format: keep 15 digits only
    document.querySelectorAll('.js-phone').forEach(function (input) {
        input.addEventListener('input', function () {
            this.value = digitsOnly(this.value, 15);
        });
    });

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

        var cnicEl = form.querySelector('.js-cnic');
        if (cnicEl) {
            var rawCnic = digitsOnly(cnicEl.value, 13);
            if (rawCnic.length !== 13) {
                formError.classList.remove('d-none');
                formError.textContent = i18n.cnic_must_13;
                cnicEl.focus();
                return;
            }
            if (rawCnic !== lastVerifiedCnic || !cnicEl.classList.contains('is-valid')) {
                formError.classList.remove('d-none');
                var notFoundMsg = i18n.cnic_not_found || i18n.cnic_must_verify;
                formError.textContent = notFoundMsg;
                cnicEl.classList.remove('is-valid');
                cnicEl.classList.add('is-invalid');
                cnicEl.focus();

                var parentCol = cnicEl.closest('.col-12, .col-md-6') || cnicEl.parentElement;
                var statusBox = parentCol ? parentCol.querySelector('.cnic-status-box') : null;
                if (statusBox) {
                    statusBox.className = 'cnic-status-box alert alert-danger py-2 px-3 small mt-2 d-flex align-items-center gap-2 mb-1';
                    statusBox.innerHTML = '<i class="fa-solid fa-circle-exclamation text-danger fs-5"></i> <span>' + escapeHtml(notFoundMsg) + '</span>';
                    statusBox.classList.remove('d-none');
                }
                return;
            }
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

    // Cascading Dropdowns: District -> Tehsil -> Union Council
    function initCascadingDropdowns() {
        var districtSelect = document.querySelector('.js-cascading-district');
        var tehsilSelect = document.querySelector('.js-cascading-tehsil');
        var ucSelect = document.querySelector('.js-cascading-uc');
        var ucHelpText = document.getElementById('ucHelpText');

        if (!districtSelect || !tehsilSelect) return;

        var urlTehsilsTemplate = @json(route('locations.tehsils', ['district' => '__ID__']));
        var urlUcTemplate = @json(route('locations.union-councils', ['tehsil' => '__ID__']));

        var currentTehsils = [];
        var pendingTehsilOld = tehsilSelect.getAttribute('data-old') || '';
        var pendingUcOld = ucSelect ? (ucSelect.getAttribute('data-old') || '') : '';
        var ucOriginallyMandatory = ucSelect ? ucSelect.hasAttribute('required') : false;

        var districtTom = null;
        var tehsilTom = null;
        var ucTom = null;

        var tomCommonConfig = {
            create: false,
            allowEmptyOption: false,
            maxItems: 1,
            sortField: { field: '$order', direction: 'asc' },
            render: {
                no_results: function (data, escape) {
                    return '<div class="no-results">' + escape(i18n.no_results_found) + '</div>';
                }
            }
        };

        if (window.TomSelect) {
            try {
                districtTom = new TomSelect(districtSelect, Object.assign({}, tomCommonConfig, {
                    placeholder: districtSelect.getAttribute('data-placeholder') || i18n.select_district
                }));
                if (districtTom && districtTom.options['']) {
                    districtTom.removeOption('');
                }

                tehsilTom = new TomSelect(tehsilSelect, Object.assign({}, tomCommonConfig, {
                    placeholder: tehsilSelect.getAttribute('data-empty-placeholder') || i18n.select_district_first
                }));
                if (tehsilTom && tehsilTom.options['']) {
                    tehsilTom.removeOption('');
                }

                if (ucSelect) {
                    ucTom = new TomSelect(ucSelect, Object.assign({}, tomCommonConfig, {
                        placeholder: ucSelect.getAttribute('data-empty-placeholder') || i18n.select_tehsil_first
                    }));
                    if (ucTom && ucTom.options['']) {
                        ucTom.removeOption('');
                    }
                }

                document.querySelectorAll('.js-custom-select').forEach(function (sel) {
                    try {
                        var customTom = new TomSelect(sel, Object.assign({}, tomCommonConfig, {
                            placeholder: sel.getAttribute('data-placeholder') || i18n.select
                        }));
                        if (customTom && customTom.options['']) {
                            customTom.removeOption('');
                        }
                    } catch (err) {}
                });
            } catch (e) {
                console.warn('TomSelect init error, falling back to native selects', e);
            }
        }

        function setPlaceholder(tomInstance, nativeEl, text) {
            if (tomInstance) {
                tomInstance.settings.placeholder = text;
                if (tomInstance.control_input) {
                    tomInstance.control_input.placeholder = text;
                }
            } else if (nativeEl && nativeEl.options[0]) {
                nativeEl.options[0].textContent = text;
            }
        }

        function disableControl(tomInstance, nativeEl, placeholderText) {
            if (tomInstance) {
                tomInstance.clear();
                tomInstance.clearOptions();
                tomInstance.disable();
                if (placeholderText) setPlaceholder(tomInstance, nativeEl, placeholderText);
            } else if (nativeEl) {
                nativeEl.innerHTML = '<option value="" disabled selected hidden>' + (placeholderText || '') + '</option>';
                nativeEl.value = '';
                nativeEl.disabled = true;
            }
        }

        function enableControl(tomInstance, nativeEl, placeholderText) {
            if (tomInstance) {
                tomInstance.enable();
                if (placeholderText) setPlaceholder(tomInstance, nativeEl, placeholderText);
            } else if (nativeEl) {
                nativeEl.disabled = false;
            }
        }

        function showUcEmptyState(msg) {
            var emptyMsg = msg || i18n.no_union_councils;
            disableControl(ucTom, ucSelect, emptyMsg);
            if (ucTom) {
                ucTom.addOption({ value: '', text: emptyMsg });
                ucTom.setValue('', true);
            }
            if (ucSelect) {
                ucSelect.removeAttribute('required');
                var reqStar = document.getElementById('req_union_council');
                if (reqStar) reqStar.style.display = 'none';
            }
            if (ucHelpText) {
                ucHelpText.className = 'uc-empty-state-badge d-inline-flex';
                ucHelpText.innerHTML = '<i class="fa-solid fa-circle-info"></i> ' + emptyMsg;
            }
        }

        function hideUcEmptyState() {
            if (ucHelpText) {
                ucHelpText.className = 'form-text small mt-1 d-none';
                ucHelpText.innerHTML = '';
            }
            if (ucSelect && ucOriginallyMandatory) {
                ucSelect.setAttribute('required', 'required');
                var reqStar = document.getElementById('req_union_council');
                if (reqStar) reqStar.style.display = '';
            }
        }

        function loadTehsils(districtId, preselectTehsil, preselectUc) {
            if (!districtId) return;

            setPlaceholder(tehsilTom, tehsilSelect, i18n.loading_tehsils);
            if (tehsilTom) tehsilTom.disable();
            else tehsilSelect.disabled = true;

            var fetchUrl = urlTehsilsTemplate.replace('__ID__', encodeURIComponent(districtId));
            fetch(fetchUrl, {
                headers: { 'Accept': 'application/json' }
            })
            .then(function (r) { return r.json(); })
            .then(function (tehsils) {
                currentTehsils = tehsils || [];
                var currentLang = document.documentElement.getAttribute('lang') || (document.documentElement.getAttribute('dir') === 'rtl' ? 'ur' : 'en');
                currentTehsils.sort(function (a, b) {
                    return a.name.localeCompare(b.name, currentLang, { sensitivity: 'base', numeric: true });
                });

                if (tehsilTom) {
                    tehsilTom.clear();
                    tehsilTom.clearOptions();
                    currentTehsils.forEach(function (t) {
                        tehsilTom.addOption({ value: t.name, text: t.name, id: t.id });
                    });
                    setPlaceholder(tehsilTom, tehsilSelect, i18n.select_tehsil);
                    enableControl(tehsilTom, tehsilSelect, i18n.select_tehsil);
                } else {
                    tehsilSelect.innerHTML = '<option value="" disabled selected hidden>' + i18n.select_tehsil + '</option>';
                    currentTehsils.forEach(function (t) {
                        var opt = document.createElement('option');
                        opt.value = t.name;
                        opt.textContent = t.name;
                        opt.setAttribute('data-id', t.id);
                        tehsilSelect.appendChild(opt);
                    });
                    enableControl(null, tehsilSelect, i18n.select_tehsil);
                }

                var toSelect = preselectTehsil || pendingTehsilOld;
                if (toSelect) {
                    pendingTehsilOld = null;
                    var match = currentTehsils.find(function (t) {
                        return String(t.id) === String(toSelect) || t.name.toLowerCase() === String(toSelect).toLowerCase();
                    });
                    if (match) {
                        if (tehsilTom) {
                            tehsilTom.setValue(match.name, true);
                        } else {
                            tehsilSelect.value = match.name;
                        }
                        loadUnionCouncils(match.id, preselectUc || pendingUcOld);
                        pendingUcOld = null;
                    }
                }
            })
            .catch(function (err) {
                console.error('Failed to load tehsils', err);
                disableControl(tehsilTom, tehsilSelect, 'Failed to load tehsils');
            });
        }

        function loadUnionCouncils(tehsilId, preselectUc) {
            if (!ucSelect) return;
            if (!tehsilId) return;

            hideUcEmptyState();
            setPlaceholder(ucTom, ucSelect, i18n.loading_union_councils);
            if (ucTom) ucTom.disable();
            else ucSelect.disabled = true;

            var fetchUrl = urlUcTemplate.replace('__ID__', encodeURIComponent(tehsilId));
            fetch(fetchUrl, {
                headers: { 'Accept': 'application/json' }
            })
            .then(function (r) { return r.json(); })
            .then(function (ucs) {
                ucs = ucs || [];

                if (ucs.length === 0) {
                    showUcEmptyState(i18n.no_union_councils);
                    return;
                }

                var currentLang = document.documentElement.getAttribute('lang') || (document.documentElement.getAttribute('dir') === 'rtl' ? 'ur' : 'en');
                ucs.sort(function (a, b) {
                    return a.name.localeCompare(b.name, currentLang, { sensitivity: 'base', numeric: true });
                });

                if (ucTom) {
                    ucTom.clear();
                    ucTom.clearOptions();
                    ucs.forEach(function (u) {
                        ucTom.addOption({ value: u.name, text: u.name, id: u.id });
                    });
                    setPlaceholder(ucTom, ucSelect, i18n.select_union_council);
                    enableControl(ucTom, ucSelect, i18n.select_union_council);
                } else {
                    ucSelect.innerHTML = '<option value="" disabled selected hidden>' + i18n.select_union_council + '</option>';
                    ucs.forEach(function (u) {
                        var opt = document.createElement('option');
                        opt.value = u.name;
                        opt.textContent = u.name;
                        opt.setAttribute('data-id', u.id);
                        ucSelect.appendChild(opt);
                    });
                    enableControl(null, ucSelect, i18n.select_union_council);
                }

                var toSelectUc = preselectUc || pendingUcOld;
                if (toSelectUc) {
                    pendingUcOld = null;
                    var matchUc = ucs.find(function (u) {
                        return String(u.id) === String(toSelectUc) || u.name.toLowerCase() === String(toSelectUc).toLowerCase();
                    });
                    if (matchUc) {
                        if (ucTom) {
                            ucTom.setValue(matchUc.name, true);
                        } else {
                            ucSelect.value = matchUc.name;
                        }
                    }
                }
            })
            .catch(function (err) {
                console.error('Failed to load union councils', err);
                disableControl(ucTom, ucSelect, 'Failed to load union councils');
            });
        }

        function onDistrictChange(districtId) {
            disableControl(tehsilTom, tehsilSelect, districtId ? i18n.loading_tehsils : i18n.select_district_first);
            if (ucSelect) {
                hideUcEmptyState();
                disableControl(ucTom, ucSelect, i18n.select_tehsil_first);
            }

            if (!districtId) return;

            loadTehsils(districtId, null, null);
        }

        function onTehsilChange(tehsilVal) {
            if (!ucSelect) return;

            hideUcEmptyState();
            disableControl(ucTom, ucSelect, tehsilVal ? i18n.loading_union_councils : i18n.select_tehsil_first);

            if (!tehsilVal) return;

            var matchTehsil = currentTehsils.find(function (t) {
                return String(t.id) === String(tehsilVal) || t.name === tehsilVal;
            });

            if (matchTehsil) {
                loadUnionCouncils(matchTehsil.id, null);
            }
        }

        if (districtTom) {
            districtTom.on('change', onDistrictChange);
        } else {
            districtSelect.addEventListener('change', function () {
                onDistrictChange(this.value);
            });
        }

        if (tehsilTom) {
            tehsilTom.on('change', onTehsilChange);
        } else {
            tehsilSelect.addEventListener('change', function () {
                onTehsilChange(this.value);
            });
        }

        var initialDistrictId = districtSelect.value;
        if (initialDistrictId) {
            loadTehsils(initialDistrictId, pendingTehsilOld, pendingUcOld);
        } else {
            disableControl(tehsilTom, tehsilSelect, i18n.select_district_first);
            if (ucSelect) {
                disableControl(ucTom, ucSelect, i18n.select_tehsil_first);
            }
        }
    }

    initCascadingDropdowns();

    // Checkbox with conditional text field interaction
    document.querySelectorAll('.js-toggle-text-field').forEach(function (checkbox) {
        function updateState() {
            var target = document.querySelector(checkbox.dataset.target);
            var input = document.querySelector(checkbox.dataset.input);
            if (checkbox.checked) {
                if (target) target.classList.remove('d-none');
                if (input) {
                    input.disabled = false;
                }
            } else {
                if (target) target.classList.add('d-none');
                if (input) {
                    input.disabled = true;
                }
            }
        }
        checkbox.addEventListener('change', function () {
            updateState();
            if (checkbox.checked) {
                var input = document.querySelector(checkbox.dataset.input);
                if (input) input.focus();
            }
        });
        updateState();
    });

    // Dynamic Seed Bags Requirement Limitation (1 bag per 4 kanals of available land)
    (function initSeedBagsLimitation() {
        var landInput = document.getElementById('field_land_kanals') || document.querySelector('.js-land-kanals-input');
        var bagsInput = document.getElementById('field_bags_required') || document.querySelector('.js-seed-bags-input') || document.querySelector('input[name="bags_required"]');

        if (!landInput || !bagsInput) return;

        var kanalsPerBag = 4;
        var feedbackText = document.querySelector('.js-bags-limit-text');
        var errorBox = document.querySelector('.js-bags-dynamic-error');

        function recalculateBagsLimit() {
            var land = parseFloat(landInput.value);
            var bagsVal = bagsInput.value.trim();
            var bags = parseFloat(bagsVal);

            if (isNaN(land) || land <= 0) {
                bagsInput.removeAttribute('max');
                if (feedbackText) {
                    feedbackText.innerHTML = '<span class="text-muted"><i class="fa-solid fa-circle-info text-secondary me-1"></i>Please enter <strong>Available Land (Kanals)</strong> first to calculate maximum allowable bags (1 bag per 4 kanals).</span>';
                }
                if (bagsVal !== '' && !isNaN(bags) && bags > 0) {
                    bagsInput.classList.add('is-invalid');
                    if (errorBox) {
                        errorBox.textContent = 'Please enter valid Available Land (Kanals) first. Bags are limited to 1 bag per 4 kanals.';
                        errorBox.classList.remove('d-none');
                    }
                } else {
                    bagsInput.classList.remove('is-invalid');
                    if (errorBox) errorBox.classList.add('d-none');
                }
                return;
            }

            var maxBags = Math.floor(land / kanalsPerBag);
            bagsInput.max = maxBags;

            if (feedbackText) {
                if (maxBags > 0) {
                    feedbackText.innerHTML = '<span class="text-success fw-semibold"><i class="fa-solid fa-check-circle me-1"></i>Maximum allowed: <strong>' + maxBags + ' bag(s)</strong> (based on ' + land + ' kanals: 1 bag per 4 kanals).</span>';
                } else {
                    feedbackText.innerHTML = '<span class="text-warning fw-semibold"><i class="fa-solid fa-triangle-exclamation me-1"></i>Available land is less than 4 kanals. Minimum 4 kanals required for 1 standard 50kg bag.</span>';
                }
            }

            if (bagsVal !== '' && !isNaN(bags)) {
                if (bags > maxBags) {
                    bagsInput.classList.add('is-invalid');
                    if (errorBox) {
                        errorBox.textContent = 'The requested ' + bags + ' bag(s) exceeds the maximum limit of ' + maxBags + ' bag(s) for ' + land + ' kanals (1 bag per 4 kanals).';
                        errorBox.classList.remove('d-none');
                    }
                } else if (bags < 1) {
                    bagsInput.classList.add('is-invalid');
                    if (errorBox) {
                        errorBox.textContent = 'Number of bags must be at least 1.';
                        errorBox.classList.remove('d-none');
                    }
                } else {
                    bagsInput.classList.remove('is-invalid');
                    if (errorBox) {
                        errorBox.classList.add('d-none');
                        errorBox.textContent = '';
                    }
                }
            } else {
                bagsInput.classList.remove('is-invalid');
                if (errorBox) {
                    errorBox.classList.add('d-none');
                    errorBox.textContent = '';
                }
            }
        }

        landInput.addEventListener('input', recalculateBagsLimit);
        landInput.addEventListener('change', recalculateBagsLimit);
        bagsInput.addEventListener('input', recalculateBagsLimit);
        bagsInput.addEventListener('change', recalculateBagsLimit);

        // Run once on load to sync prefilled or old values
        recalculateBagsLimit();

        // Intercept form submission if bags exceed max
        var form = document.getElementById('applyForm');
        if (form) {
            form.addEventListener('submit', function (e) {
                var land = parseFloat(landInput.value);
                var bags = parseFloat(bagsInput.value);
                var maxBags = !isNaN(land) && land > 0 ? Math.floor(land / kanalsPerBag) : 0;

                if (isNaN(land) || land <= 0) {
                    e.preventDefault();
                    e.stopPropagation();
                    landInput.classList.add('is-invalid');
                    landInput.focus();
                    return false;
                }

                if (!isNaN(bags) && bags > maxBags) {
                    e.preventDefault();
                    e.stopPropagation();
                    bagsInput.classList.add('is-invalid');
                    if (errorBox) {
                        errorBox.textContent = 'The requested ' + bags + ' bag(s) exceeds the maximum limit of ' + maxBags + ' bag(s) for ' + land + ' kanals (1 bag per 4 kanals).';
                        errorBox.classList.remove('d-none');
                    }
                    bagsInput.focus();
                    return false;
                }
            });
        }
    })();
})();
</script>
@endpush
