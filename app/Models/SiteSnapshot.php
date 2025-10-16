<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteSnapshot extends Model
{
    protected $fillable = [
        'registered_site_id',
        'domain',
        'html_content',
    ];

    public function registeredSite(): BelongsTo
    {
        return $this->belongsTo(RegisteredSite::class);
    }
}
