<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BulkUpload extends Model
{
    protected $fillable = [
        'status',
        'total_file',
        'processed',
        'success',
        'failed',
        'error_log_json',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'error_log_json' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
