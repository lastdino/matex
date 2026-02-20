<?php

declare(strict_types=1);

namespace Lastdino\Matex\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lastdino\Matex\Support\Tables;

class MaterialRiskAssessment extends Model
{
    protected $fillable = [
        'material_id', 'assessment_date', 'risk_level', 'assessment_results', 'countermeasures', 'next_assessment_date', 'assessor_name',
    ];

    public function getTable()
    {
        return Tables::name('material_risk_assessments');
    }

    protected function casts(): array
    {
        return [
            'assessment_date' => 'date',
            'next_assessment_date' => 'date',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
