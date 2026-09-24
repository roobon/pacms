<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Term;
use App\Services\ActivityLog\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Category and tag management for every registered taxonomy (config pacms.taxonomies).
 */
class TermController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function index(string $taxonomy): View
    {
        $definition = $this->definition($taxonomy);

        $terms = Term::query()->inTaxonomy($taxonomy)->orderBy('position')->orderBy('name')->get();
        $usage = DB::table('termables')
            ->whereIn('term_id', $terms->pluck('id'))
            ->selectRaw('term_id, count(*) as aggregate') // constant SQL, no input
            ->groupBy('term_id')
            ->pluck('aggregate', 'term_id');

        return view('admin.terms.index', [
            'taxonomy' => $taxonomy,
            'definition' => $definition,
            'terms' => $terms,
            'usage' => $usage,
            'taxonomies' => config('pacms.taxonomies'),
        ]);
    }

    public function store(Request $request, string $taxonomy): RedirectResponse
    {
        $definition = $this->definition($taxonomy);
        $data = $this->validated($request, $taxonomy, $definition);

        $term = Term::query()->create($data + ['taxonomy' => $taxonomy]);
        $this->logger->log('term.created', $term, ['taxonomy' => $taxonomy]);

        return back()->with('success', __('":name" added.', ['name' => $term->name]));
    }

    public function update(Request $request, string $taxonomy, Term $term): RedirectResponse
    {
        $definition = $this->definition($taxonomy);
        abort_unless($term->taxonomy === $taxonomy, 404);

        $data = $this->validated($request, $taxonomy, $definition, $term);

        if (isset($data['parent_id']) && $this->wouldCycle($term, (int) $data['parent_id'])) {
            throw ValidationException::withMessages(['parent_id' => __('A category cannot be placed under itself.')]);
        }

        $term->update($data);
        $this->logger->log('term.updated', $term, ['taxonomy' => $taxonomy]);

        return back()->with('success', __('":name" updated.', ['name' => $term->name]));
    }

    public function destroy(string $taxonomy, Term $term): RedirectResponse
    {
        $this->definition($taxonomy);
        abort_unless($term->taxonomy === $taxonomy, 404);

        if ($term->usageCount() > 0 || $term->children()->exists()) {
            throw ValidationException::withMessages(['term' => __('":name" is still in use or has sub-categories. Reassign them first.', ['name' => $term->name])]);
        }

        $term->forceDelete();
        $this->logger->log('term.deleted', $term, ['taxonomy' => $taxonomy]);

        return back()->with('success', __('":name" deleted.', ['name' => $term->name]));
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function validated(Request $request, string $taxonomy, array $definition, ?Term $term = null): array
    {
        $request->merge(['slug' => Str::slug((string) ($request->input('slug') ?: $request->input('name')))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'slug' => ['required', 'string', 'max:191', Rule::unique('terms')->where('taxonomy', $taxonomy)->ignore($term?->getKey())],
            'description' => ['nullable', 'string', 'max:1000'],
            'parent_id' => $definition['hierarchical']
                ? ['nullable', 'integer', Rule::exists('terms', 'id')->where('taxonomy', $taxonomy)]
                : ['prohibited'],
        ]);
    }

    /**
     * @return array{label: string, singular: string, hierarchical: bool}
     */
    private function definition(string $taxonomy): array
    {
        $definition = config('pacms.taxonomies.'.$taxonomy);
        abort_if($definition === null, 404);

        return $definition;
    }

    private function wouldCycle(Term $term, int $parentId): bool
    {
        $current = $parentId;
        for ($i = 0; $i < 20 && $current; $i++) {
            if ($current === $term->id) {
                return true;
            }
            $current = (int) Term::query()->whereKey($current)->value('parent_id');
        }

        return false;
    }
}
