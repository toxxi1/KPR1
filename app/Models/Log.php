<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'command',
        'name',
        'status',
        'description',
    ];
    
    public function createLog($command, $name, $status, $description = null): Log
    {
       return Log::create(
            [
                'command' => $command,
                'name' => $name,
                'status' => $status,
                'description' => $description
            ]
        );
    }
}
