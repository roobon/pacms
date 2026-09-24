<?php

namespace App\Services\Seo;

use App\Models\Redirect;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Redirect bookkeeping. When a URL moves, a 301 is recorded from the old path, and any
 * existing redirects that pointed at the old path are repointed, so chains never form.
 */
class RedirectService
{
    public function recordMove(string $from, string $to, ?User $user = null): void
    {
        $from = Redirect::normalizePath($from);
        $to = Redirect::normalizePath($to);

        if ($from === $to) {
            return;
        }

        DB::transaction(function () use ($from, $to, $user) {
            // Avoid chains: A→B plus B→C becomes A→C and B→C.
            Redirect::query()->where('target_path', $from)->update(['target_path' => $to]);

            // A page moved back to an old URL must not redirect away from itself.
            Redirect::query()->where('source_path', $to)->delete();

            Redirect::query()->updateOrCreate(
                ['source_path' => $from],
                ['target_path' => $to, 'status_code' => 301, 'is_auto' => true, 'created_by' => $user?->getKey()],
            );
        });
    }

    public function find(string $path): ?Redirect
    {
        return Redirect::query()->where('source_path', Redirect::normalizePath($path))->first();
    }

    public function recordHit(Redirect $redirect): void
    {
        Redirect::query()->whereKey($redirect->getKey())->increment('hits', 1, ['last_hit_at' => now()]);
    }
}
