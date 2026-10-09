<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaCoverage;
use App\Services\MediaCoverage\SourceChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * "Check now" on a coverage item: runs the source check straight away (instead of waiting
 * for the daily check) and shows the result.
 */
class MediaCoverageController extends Controller
{
    public function checkSource(int $item, SourceChecker $checker): RedirectResponse
    {
        $coverage = MediaCoverage::query()->findOrFail($item);
        Gate::authorize('update', $coverage);

        if (! $coverage->source_url) {
            return back()->with('error', __('Add a link to the original first.'));
        }

        $checker->check($coverage);

        $error = $coverage->last_check_error;
        [$level, $message] = match (true) {
            $error === null => ['success', __('The original link works.')],
            $coverage->availability === 'unavailable' => ['warning', __('The original is no longer available (:error).', ['error' => $error])],
            $coverage->availability === 'unverified' => ['warning', __('The original could not be verified (:error). Many news sites block automatic checks, so the link stays visible.', ['error' => $error])],
            default => ['warning', __('The original did not answer correctly (:error). It is marked unavailable after :count failed checks in a row.', [
                'error' => $error, 'count' => SourceChecker::FAILURES_BEFORE_UNAVAILABLE,
            ])],
        };

        return redirect()->route('admin.media_coverage.edit', $coverage)->with($level, $message);
    }
}
