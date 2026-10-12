<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TestimonialStatus;
use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use App\Services\ActivityLog\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * DELETE /api/v1/me — a registered visitor deletes their own account (Phase 11A). Staff
 * accounts are managed by administrators. Testimonials not published yet are deleted;
 * published ones stay on the website without a link to the account.
 */
class AccountController extends Controller
{
    public function destroy(Request $request, ActivityLogger $logger): JsonResponse
    {
        $user = $request->user();
        abort_if($user->can('admin.access'), 403, 'Staff accounts are deleted by an administrator.');

        $data = $request->validate(['password' => ['required', 'string']]);
        if (! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => __('The password is not correct.')]);
        }

        DB::transaction(function () use ($user, $logger) {
            // Logged first: the entry's user link is cleared when the account goes.
            $logger->log('user.self_deleted', null, [], null, 'A registered visitor deleted their account');
            // Removed for good (not to the bin): the person asked for their data to go.
            Testimonial::withTrashed()->where('user_id', $user->id)->where('status', '!=', TestimonialStatus::Published)->forceDelete();
            $user->tokens()->delete();
            $user->forceDelete();
        });

        // Not logout(): it would save a new remember token and so bring the account back.
        Auth::guard('web')->forgetUser();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => __('Your account has been deleted.')]);
    }
}
