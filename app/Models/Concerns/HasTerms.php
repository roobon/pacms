<?php

namespace App\Models\Concerns;

use App\Models\Term;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Categories and tags through the unified taxonomy (terms / termables).
 */
trait HasTerms
{
    /**
     * @return MorphToMany<Term, $this>
     */
    public function terms(): MorphToMany
    {
        return $this->morphToMany(Term::class, 'termable')->withPivot('position');
    }

    /**
     * Replace this item's terms for one taxonomy, leaving other taxonomies untouched.
     *
     * @param  list<int>  $termIds
     */
    public function syncTerms(string $taxonomy, array $termIds): void
    {
        $valid = Term::query()->where('taxonomy', $taxonomy)->whereKey($termIds)->pluck('id')->all();
        $other = $this->terms()->where('taxonomy', '!=', $taxonomy)->pluck('terms.id')->all();

        $this->terms()->sync(array_merge($other, $valid));
    }
}
