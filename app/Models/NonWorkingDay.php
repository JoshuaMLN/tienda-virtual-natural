<?php

namespace App\Models;

use Database\Factories\NonWorkingDayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NonWorkingDay extends Model
{
    /** @use HasFactory<NonWorkingDayFactory> */
    use HasFactory;

    protected $fillable = [
        'date',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
        ];
    }
}
