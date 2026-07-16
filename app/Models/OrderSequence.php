<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderSequence extends Model
{
    protected $primaryKey = 'year';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'year',
        'last_number',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_number' => 'integer',
        ];
    }
}
