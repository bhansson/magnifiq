@component('mail::message')
# {{ __('Welcome to :app!', ['app' => config('app.name')]) }}

{{ __('Hi :name,', ['name' => $user->name]) }}

{{ __('Thanks for signing up! Please verify your email address by clicking the button below.') }}

@component('mail::button', ['url' => $url])
{{ __('Verify Email Address') }}
@endcomponent

{{ __('This verification link will expire in :minutes minutes.', ['minutes' => config('auth.verification.expire', 60)]) }}

{{ __('If you did not create an account, no further action is required.') }}

@component('mail::subcopy')
{{ __('If you\'re having trouble clicking the "Verify Email Address" button, copy and paste the URL below into your web browser:') }}
<span class="break-all">[{{ $url }}]({{ $url }})</span>
@endcomponent
@endcomponent
