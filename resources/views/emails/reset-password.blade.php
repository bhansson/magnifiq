@component('mail::message')
# {{ __('Reset Your Password') }}

{{ __('Hi :name,', ['name' => $user->name]) }}

{{ __('You are receiving this email because we received a password reset request for your account.') }}

@component('mail::button', ['url' => $url])
{{ __('Reset Password') }}
@endcomponent

{{ __('This password reset link will expire in :count minutes.', ['count' => $count]) }}

{{ __('If you did not request a password reset, no further action is required.') }}

@component('mail::subcopy')
{{ __('If you\'re having trouble clicking the "Reset Password" button, copy and paste the URL below into your web browser:') }}
<span class="break-all">[{{ $url }}]({{ $url }})</span>
@endcomponent
@endcomponent
