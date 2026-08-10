<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedFields;
use Illuminate\Database\Eloquent\Model;

class CarouselSlide extends Model
{
    use HasLocalizedFields;

    protected $fillable = [
        'title', 'title_ur', 'subtitle', 'subtitle_ur', 'image',
        'button_label', 'button_label_ur', 'button_url',
        'button2_label', 'button2_label_ur', 'button2_url',
        'display_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function imageUrl(string $fallback = 'frontend/img/bg-img/1.jpg'): string
    {
        if ($this->image) {
            if (str_starts_with($this->image, 'frontend/') || str_starts_with($this->image, 'http')) {
                return str_starts_with($this->image, 'http') ? $this->image : asset($this->image);
            }

            return asset('storage/'.$this->image);
        }

        return asset($fallback);
    }
}
