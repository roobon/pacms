<?php

namespace App\Auth;

use App\Models\User;
use App\Services\ActivityLog\ActivityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Events\Dispatcher;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;

/**
 * Writes authentication and account-security events to the activity log.
 */
class AuthActivitySubscriber
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handleLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ])->saveQuietly();

        $this->logger->log('auth.login', $event->user, user: $event->user);
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->logger->log('auth.logout', $event->user, user: $event->user);
        }
    }

    public function handleFailed(Failed $event): void
    {
        // The attempted email is recorded so repeated attacks on one account are visible;
        // the password is never part of the event payload we store.
        $this->logger->log('auth.failed', $event->user instanceof User ? $event->user : null, [
            'email' => mb_substr((string) ($event->credentials['email'] ?? ''), 0, 191),
        ], user: null);
    }

    public function handleLockout(Lockout $event): void
    {
        $this->logger->log('security.lockout', properties: [
            'email' => mb_substr((string) $event->request->input('email', ''), 0, 191),
        ], user: null);
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        if ($event->user instanceof User) {
            $this->logger->log('auth.password_reset', $event->user, user: $event->user);
        }
    }

    public function handleRegistered(Registered $event): void
    {
        if ($event->user instanceof User) {
            $this->logger->log('user.registered', $event->user, user: $event->user);
        }
    }

    public function handleVerified(Verified $event): void
    {
        if ($event->user instanceof User) {
            $this->logger->log('user.email_verified', $event->user, user: $event->user);
        }
    }

    public function handleTwoFactorConfirmed(TwoFactorAuthenticationConfirmed $event): void
    {
        $this->logger->log('security.two_factor_enabled', $event->user, user: $event->user);
    }

    public function handleTwoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        $this->logger->log('security.two_factor_disabled', $event->user, user: $event->user);
    }

    public function handleTwoFactorFailed(TwoFactorAuthenticationFailed $event): void
    {
        $this->logger->log('security.two_factor_failed', $event->user, user: null);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Logout::class => 'handleLogout',
            Failed::class => 'handleFailed',
            Lockout::class => 'handleLockout',
            PasswordReset::class => 'handlePasswordReset',
            Registered::class => 'handleRegistered',
            Verified::class => 'handleVerified',
            TwoFactorAuthenticationConfirmed::class => 'handleTwoFactorConfirmed',
            TwoFactorAuthenticationDisabled::class => 'handleTwoFactorDisabled',
            TwoFactorAuthenticationFailed::class => 'handleTwoFactorFailed',
        ];
    }
}
