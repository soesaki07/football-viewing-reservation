<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competition extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_competition_id',
        'code',
        'type',
        'name',
        'area_name',
        'emblem_url',
        'is_active',
    ];

    public function footballMatches(): HasMany
    {
        return $this->hasMany(FootballMatch::class);
    }
}
