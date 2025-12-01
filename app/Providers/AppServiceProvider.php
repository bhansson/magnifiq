<?php

namespace App\Providers;

use App\Models\TeamActivity;
use App\Models\User;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
