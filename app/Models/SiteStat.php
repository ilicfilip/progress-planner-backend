<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteStat extends Model
{
    protected $fillable = [
        'registered_site_id',
        'api_available',
        'plugin_version',
        'raw_response',
        'error_message',
        'last_fetched_at',
    ];

    protected $casts = [
        'raw_response' => 'array',
        'api_available' => 'boolean',
        'last_fetched_at' => 'datetime',
    ];

    public function registeredSite(): BelongsTo
    {
        return $this->belongsTo(RegisteredSite::class);
    }
}
