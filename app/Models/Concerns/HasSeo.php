<?php

namespace App\Models\Concerns;

use App\Models\SeoMetadata;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasSeo
{
    /**
     * @return MorphOne<SeoMetadata, $this>
     */
    public function seo(): MorphOne
    {
        return $this->morphOne(SeoMetadata::class, 'seoable');
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function seoSnapshot(): ?array
    {
        $seo = $this->seo;

        return $seo ? $seo->only(SeoMetadata::FIELDS) : null;
    }

    /**
     * @param  array<string, mixed>|null  $values
     */
    public function saveSeo(?array $values): void
    {
        $values = array_intersect_key((array) $values, array_flip(SeoMetadata::FIELDS));

        if ($values === [] && $this->seo === null) {
            return;
        }

        $this->seo()->updateOrCreate([], $values);
        $this->unsetRelation('seo');
    }
}
