<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * A category or tag in one of the registered taxonomies (ContentTypeRegistry::taxonomies()).
 */
class Term extends Model
{
    use SoftDeletes;

    protected $fillable = ['taxonomy', 'parent_id', 'name', 'slug', 'description', 'position'];

    /**
     * @return BelongsTo<Term, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'parent_id');
    }

    /**
     * @return HasMany<Term, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Term::class, 'parent_id');
    }

    /**
     * @param  Builder<Term>  $query
     */
    public function scopeInTaxonomy(Builder $query, string $taxonomy): void
    {
        $query->where('taxonomy', $taxonomy);
    }

    public function usageCount(): int
    {
        return DB::table('termables')->where('term_id', $this->id)->count();
    }
}
