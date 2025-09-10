<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'genre',
        'description',
        'duration',
        'year',
        'is_featured',
        'thumbnail_url',
        'video_url',
    ];

    protected $casts = [
        'duration'    => 'integer',
        'year'        => 'integer',
        'is_featured' => 'boolean',
    ];

}