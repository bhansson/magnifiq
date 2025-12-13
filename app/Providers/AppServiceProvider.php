<?php

namespace App\Providers;

use App\Models\TeamActivity;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use App\Services\StoreIntegration\Adapters\ShopifyAdapter;
use App\Services\StoreIntegration\ShopifyLocaleService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Jetstream\Events\TeamMemberAdded;
use Laravel\Jetstream\Events\TeamMemberRemoved;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ShopifyLocaleService::class, function ($app) {
            return new ShopifyLocaleService(new ShopifyAdapter);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Send email verification notification when a user registers
        Event::listen(Registered::class, SendEmailVerificationNotification::class);

        // Use custom branded verification email
        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            return (new VerifyEmailNotification($url))->toMail($notifiable);
        });

        // Use custom branded password reset email
        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            return (new ResetPasswordNotification($token))->toMail($notifiable);
        });

        Event::listen(TeamMemberAdded::class, function (TeamMemberAdded $event) {
            TeamActivity::create([
                'team_id' => $event->team->id,
                'user_id' => $event->user->id,
                'type' => TeamActivity::TYPE_TEAM_MEMBER_ADDED,
                'subject_type' => User::class,
                'subject_id' => $event->user->id,
                'properties' => [
                    'member_name' => $event->user->name,
                    'member_email' => $event->user->email,
                ],
            ]);
        });

        Event::listen(TeamMemberRemoved::class, function (TeamMemberRemoved $event) {
            TeamActivity::create([
                'team_id' => $event->team->id,
                'user_id' => $event->user->id,
                'type' => TeamActivity::TYPE_TEAM_MEMBER_REMOVED,
                'subject_type' => User::class,
                'subject_id' => $event->user->id,
                'properties' => [
                    'member_name' => $event->user->name,
                    'member_email' => $event->user->email,
                ],
            ]);
        });
    }
}
