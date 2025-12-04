<?php

namespace App\Livewire\Concerns;

/**
 * Trait for enforcing super admin authorization in Livewire components.
 *
 * This trait should be used by all admin Livewire components to ensure
 * authorization is checked both on component mount and before destructive actions.
 * Route-level middleware alone is insufficient because Livewire actions can be
 * invoked directly via POST to /livewire/message, bypassing route middleware.
 */
trait AuthorizesSuperAdmin
{
    /**
     * Boot the trait and register authorization check on mount.
     *
     * This method is automatically called by Livewire when the trait is used.
     */
    public function bootAuthorizesSuperAdmin(): void
    {
        $this->authorizeSuperAdmin();
    }

    /**
     * Ensure the current user is a super administrator.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function authorizeSuperAdmin(): void
    {
        $user = auth()->user();

        if (! $user || ! $user->isSuperAdmin()) {
            abort(403, 'This action is only available to super administrators.');
        }
    }
}
