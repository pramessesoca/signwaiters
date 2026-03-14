<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TteRequest extends Model
{
    use HasFactory;

    public const LIST_TIM = [
        'ITSA',
        'Monitoring',
        'Proteksi',
        'Analisis Malware',
        'Threat Hunting',
        'Cyber Threat Intelligence',
        'Digital Forensic',
        'Incident Response',
        'Infrastruktur',
    ];

    public const STATUS_TUNGGU = 'tunggu';
    public const STATUS_SETUJU = 'setuju';
    public const STATUS_TOLAK = 'tolak';
    public const STATUS_SIAP = 'siap';

    protected $fillable = [
        'nama',
        'tim',
        'token',
        'status',
        'file_req',
        'file_tte',
        'cat_admin',
        'tgl_setuju',
        'tgl_tolak',
        'kedaluwarsa',
    ];

    protected function casts(): array
    {
        return [
            'tgl_setuju' => 'datetime',
            'tgl_tolak' => 'datetime',
            'kedaluwarsa' => 'datetime',
        ];
    }

    public static function buatTokenUnik(): string
    {
        do {
            $token = Str::upper(Str::random(8));
        } while (self::where('token', $token)->exists());

        return $token;
    }

    public static function listTim(): array
    {
        return self::LIST_TIM;
    }
}
