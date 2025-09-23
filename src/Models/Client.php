<?php

namespace Liqrgv\ShopSync\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $dates = ['deleted_at'];

    protected $fillable = [
        'site_url',
        'staging_domain',
        'callback_url',
        'is_staging_mode',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'access_token',
    ];

    /**
     * Get the active URL for the client (staging or production)
     *
     * @return string
     */
    public function getActiveUrl(): string
    {
        if ($this->is_staging_mode) {
            return $this->getStagingUrl();
        }

        $url = $this->site_url;
        if (empty($url)) {
            return '';
        }

        if (!preg_match('/^https?:\/\//i', $url)) {
            return 'https://' . ltrim($url, '/');
        }

        return $url;
    }

    public function getStagingUrl(): string
    {
        $url = $this->staging_domain;
        if (!empty($this->staging_domain)) {
            $url = $this->staging_domain . config('constants.STAGING_DOMAIN');
        }
        if (empty($url)) {
            return '';
        }
        if (!preg_match('/^https?:\/\//i', $url)) {
            $fallback = 'https://' . ltrim($url, '/');
            return $fallback;
        }
        return $url;
    }
}
