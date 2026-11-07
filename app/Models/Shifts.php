<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shifts extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'sbis_id',
        'shift_id',
        'data',
        'point',
        'open',
        'closed',
        'staff',
    ];
}