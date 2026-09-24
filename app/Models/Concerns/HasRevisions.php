<?php

namespace App\Models\Concerns;

use App\Models\Revision;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasRevisions
{
    /**
     * @return MorphMany<Revision, $this>
     */
    public function revisions(): MorphMany
    {
        return $this->morphMany(Revision::class, 'revisionable')->orderByDesc('number');
    }
}
