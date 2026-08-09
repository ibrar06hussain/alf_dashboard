<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormDocument;
use App\Models\FormField;
use App\Models\Scheme;
use App\Support\CoreFormFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FormBuilderController extends Controller
{
    public function edit(Scheme $scheme): View
    {
        $form = $scheme->forms()->with(['fields', 'documents'])->latest('version')->first();

        if (! $form) {
            $form = Form::create([
                'scheme_id' => $scheme->id,
                'title' => $scheme->title.' Application',
                'version' => 1,
                'is_published' => false,
            ]);

            foreach (CoreFormFields::definitions() as $field) {
                FormField::create(array_merge($field, ['form_id' => $form->id]));
            }

            $form->load(['fields', 'documents']);
        }

        $this->ensureCoreFields($form);
        $form->load(['fields', 'documents']);

        $fieldTypes = [
            'text', 'textarea', 'number', 'radio', 'checkbox',
            'select', 'switch', 'district', 'cnic', 'phone',
        ];

        return view('admin.form-builder.edit', compact('scheme', 'form', 'fieldTypes'));
    }

    public function update(Request $request, Scheme $scheme): RedirectResponse
    {
        $form = $scheme->forms()->latest('version')->firstOrFail();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'fields' => ['nullable', 'array'],
            'fields.*.id' => ['nullable', 'integer'],
            'fields.*.field_key' => ['required', 'string', 'max:100'],
            'fields.*.field_label' => ['required', 'string', 'max:255'],
            'fields.*.field_type' => ['required', 'string', 'max:50'],
            'fields.*.is_mandatory' => ['nullable'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:255'],
            'fields.*.help_text' => ['nullable', 'string', 'max:255'],
            'fields.*.options' => ['nullable', 'string'],
            'fields.*.display_order' => ['nullable', 'integer', 'min:0'],
            'documents' => ['nullable', 'array'],
            'documents.*.id' => ['nullable', 'integer'],
            'documents.*.document_name' => ['required', 'string', 'max:255'],
            'documents.*.is_mandatory' => ['nullable'],
            'documents.*.allowed_extensions' => ['nullable', 'string', 'max:255'],
            'documents.*.max_size_mb' => ['nullable', 'integer', 'min:1', 'max:50'],
            'documents.*.display_order' => ['nullable', 'integer', 'min:0'],
            'publish' => ['nullable'],
        ]);

        DB::transaction(function () use ($form, $data, $request) {
            $form->update([
                'title' => $data['title'],
                'is_published' => $request->boolean('publish') ? true : $form->is_published,
            ]);

            if ($request->boolean('publish')) {
                Form::where('scheme_id', $form->scheme_id)
                    ->where('id', '!=', $form->id)
                    ->update(['is_published' => false]);
                $form->update(['is_published' => true]);
            }

            $coreKeys = collect(CoreFormFields::definitions())->pluck('field_key')->all();
            $keptFieldIds = [];

            foreach ($data['fields'] ?? [] as $index => $fieldData) {
                $isCore = in_array($fieldData['field_key'], $coreKeys, true);
                $options = null;

                if (! empty($fieldData['options'])) {
                    $options = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n|,/', $fieldData['options']))));
                }

                $payload = [
                    'form_id' => $form->id,
                    'field_key' => $fieldData['field_key'],
                    'field_label' => $isCore
                        ? (collect(CoreFormFields::definitions())->firstWhere('field_key', $fieldData['field_key'])['field_label'] ?? $fieldData['field_label'])
                        : $fieldData['field_label'],
                    'field_type' => $isCore
                        ? (collect(CoreFormFields::definitions())->firstWhere('field_key', $fieldData['field_key'])['field_type'] ?? $fieldData['field_type'])
                        : $fieldData['field_type'],
                    'is_core' => $isCore,
                    'is_mandatory' => $isCore
                        ? true
                        : (bool) ($fieldData['is_mandatory'] ?? false),
                    'placeholder' => $fieldData['placeholder'] ?? null,
                    'help_text' => $fieldData['help_text'] ?? null,
                    'options_json' => $isCore ? null : $options,
                    'display_order' => $fieldData['display_order'] ?? ($index + 1),
                ];

                if (! empty($fieldData['id'])) {
                    $field = FormField::where('form_id', $form->id)->where('id', $fieldData['id'])->first();
                    if ($field) {
                        if ($field->is_core) {
                            $field->update([
                                'display_order' => $payload['display_order'],
                                'placeholder' => $payload['placeholder'],
                                'help_text' => $payload['help_text'],
                            ]);
                        } else {
                            $field->update($payload);
                        }
                        $keptFieldIds[] = $field->id;
                        continue;
                    }
                }

                if ($isCore) {
                    $field = FormField::updateOrCreate(
                        ['form_id' => $form->id, 'field_key' => $payload['field_key']],
                        $payload
                    );
                    $keptFieldIds[] = $field->id;
                } else {
                    $field = FormField::create($payload);
                    $keptFieldIds[] = $field->id;
                }
            }

            FormField::where('form_id', $form->id)
                ->where('is_core', false)
                ->whereNotIn('id', $keptFieldIds)
                ->delete();

            $keptDocIds = [];
            foreach ($data['documents'] ?? [] as $index => $docData) {
                $payload = [
                    'form_id' => $form->id,
                    'document_name' => $docData['document_name'],
                    'is_mandatory' => (bool) ($docData['is_mandatory'] ?? false),
                    'allowed_extensions' => $docData['allowed_extensions'] ?: 'pdf,jpg,jpeg,png',
                    'max_size_mb' => $docData['max_size_mb'] ?? 5,
                    'display_order' => $docData['display_order'] ?? ($index + 1),
                ];

                if (! empty($docData['id'])) {
                    $doc = FormDocument::where('form_id', $form->id)->where('id', $docData['id'])->first();
                    if ($doc) {
                        $doc->update($payload);
                        $keptDocIds[] = $doc->id;
                        continue;
                    }
                }

                $doc = FormDocument::create($payload);
                $keptDocIds[] = $doc->id;
            }

            FormDocument::where('form_id', $form->id)->whereNotIn('id', $keptDocIds)->delete();
        });

        $message = $request->boolean('publish')
            ? 'Form saved and published.'
            : 'Form saved.';

        return redirect()
            ->route('admin.form-builder.edit', $scheme)
            ->with('success', $message);
    }

    private function ensureCoreFields(Form $form): void
    {
        foreach (CoreFormFields::definitions() as $field) {
            FormField::firstOrCreate(
                ['form_id' => $form->id, 'field_key' => $field['field_key']],
                array_merge($field, ['form_id' => $form->id])
            );
        }
    }
}
