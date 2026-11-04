<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Uma página baixada numa sondagem web (corpo com segredos mascarados). */
class WebProbePage extends Model
{
    protected $fillable = ['web_probe_id', 'path', 'status', 'bytes', 'title', 'found_via', 'body', 'sort'];

    protected $casts = ['status' => 'integer', 'bytes' => 'integer', 'sort' => 'integer'];

    public function probe(): BelongsTo
    {
        return $this->belongsTo(WebProbe::class);
    }
}
