{{ __('Reset your password') }}

{{ __('Hello :name,', ['name' => $name]) }}

{{ __('You are receiving this email because we received a password reset request for your account.') }}

{{ __('Reset password') }}: {!! $url !!}

{{ __('This link will expire in :count minutes.', ['count' => $expireMinutes]) }}

{{ __('If you did not request a password reset, no further action is required.') }}
