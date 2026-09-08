<?php

namespace App\Models;

use Database\Factories\UrlFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['original_url', 'short_code', 'click_count'])]

class Url extends Model
{
    /** @use HasFactory<\Database\Factories\UrlFactory> */
    use HasFactory;
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
