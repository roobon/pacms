<?php

namespace App\Services\ActivityLog;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Records explicit domain events (CMS-ARCHITECTURE.md §27.1).
 * Properties must describe *what* changed (field names, ids) — never secrets or passwords.
 */
class ActivityLogger
{
    /** Keys that are always removed from properties, at any depth. */
    private const REDACTED_KEYS = [
        'password', 'password_confirmation', 'current_password', 'token', 'secret',
        'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'api_key',
    ];

    /** Actions for which the client IP is recorded (auth and security events). */
    private const IP_ACTIONS = ['auth.', 'security.', 'user.'];

    private function request(): ?Request
    {
        return app()->bound('request') ? app('request') : null;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(
        string $action,
        ?Model $subject = null,
        array $properties = [],
        ?Authenticatable $user = null,
        ?string $subjectLabel = null,
    ): ActivityLog {
        $user ??= $this->request()?->user();

        return ActivityLog::query()->create([
            'user_id' => $user instanceof User ? $user->getKey() : null,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subjectLabel ?? $this->labelFor($subject),
            'ip' => $this->shouldRecordIp($action) ? $this->request()?->ip() : null,
            'user_agent_hash' => ($agent = $this->request()?->userAgent()) ? hash('sha256', $agent) : null,
            'properties' => $properties === [] ? null : $this->redact($properties),
        ]);
    }

    private function shouldRecordIp(string $action): bool
    {
        foreach (self::IP_ACTIONS as $prefix) {
            if (str_starts_with($action, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function labelFor(?Model $subject): ?string
    {
        if ($subject === null) {
            return null;
        }

        foreach (['title', 'name', 'email'] as $attribute) {
            if (filled($subject->getAttribute($attribute))) {
                return mb_substr((string) $subject->getAttribute($attribute), 0, 255);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private function redact(array $properties): array
    {
        foreach ($properties as $key => $value) {
            if (in_array(strtolower((string) $key), self::REDACTED_KEYS, true)) {
                unset($properties[$key]);
            } elseif (is_array($value)) {
                $properties[$key] = $this->redact($value);
            }
        }

        return $properties;
    }
}
