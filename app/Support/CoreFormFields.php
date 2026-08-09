<?php

namespace App\Support;

class CoreFormFields
{
    public static function definitions(): array
    {
        return [
            [
                'field_key' => 'full_name',
                'field_label' => 'Full Name',
                'field_type' => 'text',
                'is_core' => true,
                'is_mandatory' => true,
                'display_order' => 1,
                'placeholder' => 'Enter full name',
            ],
            [
                'field_key' => 'father_name',
                'field_label' => 'Father / Husband Name',
                'field_type' => 'text',
                'is_core' => true,
                'is_mandatory' => true,
                'display_order' => 2,
                'placeholder' => 'Enter father or husband name',
            ],
            [
                'field_key' => 'cnic',
                'field_label' => 'CNIC Number',
                'field_type' => 'cnic',
                'is_core' => true,
                'is_mandatory' => true,
                'display_order' => 3,
                'placeholder' => '13 digits without dashes',
                'validation_rules' => ['regex' => '^\d{13}$'],
            ],
            [
                'field_key' => 'phone',
                'field_label' => 'Cell Number',
                'field_type' => 'phone',
                'is_core' => true,
                'is_mandatory' => true,
                'display_order' => 4,
                'placeholder' => '03XXXXXXXXX',
            ],
            [
                'field_key' => 'district_id',
                'field_label' => 'District',
                'field_type' => 'district',
                'is_core' => true,
                'is_mandatory' => true,
                'display_order' => 5,
            ],
            [
                'field_key' => 'tehsil',
                'field_label' => 'Tehsil / Union Council / Village',
                'field_type' => 'text',
                'is_core' => true,
                'is_mandatory' => true,
                'display_order' => 6,
                'placeholder' => 'Tehsil / UC / Village',
            ],
            [
                'field_key' => 'land_location',
                'field_label' => 'Land / Farm Location',
                'field_type' => 'textarea',
                'is_core' => true,
                'is_mandatory' => true,
                'display_order' => 7,
                'placeholder' => 'Detailed location of land or farm',
            ],
        ];
    }
}
