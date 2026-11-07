<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Nomenclature extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'nomenclature_id_sbis',
        'nomenclature_id',
        'folder_sbis',
        'folder',
        'is_folder',
        'name',
        'nomenclature_type',
        'cost',
        'deleted',
    ];
}
