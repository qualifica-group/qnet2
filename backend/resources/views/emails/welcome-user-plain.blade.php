{{ __('Welcome to :app', ['app' => $appName]) }}

{{ __('Hello :name,', ['name' => $name]) }}

{{ __('An account has been created for you.') }}

@if ($isInvite)
{{ __('Click the button below to choose your password and complete your first access.') }}
@else
{{ __('Your temporary password will be communicated to you by the administrator. On first access you will be asked to choose a new one.') }}
@endif

{{ $isInvite ? __('Set your password') : __('Sign in') }}: {!! $url !!}
@if ($isInvite)

{{ __('This link will expire in :count hours.', ['count' => $expireHours]) }}
@endif
