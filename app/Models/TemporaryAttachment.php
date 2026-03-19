<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemporaryAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'width',
        'height',
    ];

    protected $casts = [
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
