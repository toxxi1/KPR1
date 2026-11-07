<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Price extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'sbis_id',
        'name',
        'sale_points',
        'is_used',
        'name_file',
    ];
}