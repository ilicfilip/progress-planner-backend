<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RegisteredSite extends Model
{
    protected $fillable = [
        'site_url',
        'license_key',
        'last_emailed_at',
        'last_emailed_date',
        'raw_data',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'last_emailed_date' => 'date',
    ];

    public function siteStat(): HasOne
    {
        return $this->hasOne(SiteStat::class);
    }

    public function siteSnapshots(): HasMany
    {
        return $this->hasMany(SiteSnapshot::class);
    }

    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(SiteSnapshot::class)->latestOfMany();
    }
}
