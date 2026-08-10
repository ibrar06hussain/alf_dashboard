<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentStat extends Model
{
    use HasLocalizedFields;

    protected $fillable = [
        'department_id', 'stat_label', 'stat_label_ur', 'stat_value', 'icon',
        'source', 'auto_metric', 'display_order', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
