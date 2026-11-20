@extends('emails.layout')

@section('content')
    <h1>{{ __('Reset your password') }}</h1>

    <p>{{ __('Hello :name,', ['name' => $name]) }}</p>

    <p>{{ __('You are receiving this email because we received a password reset request for your account.') }}</p>

    @include('emails.partials.button', ['url' => $url, 'label' => __('Reset password')])

    <p>{{ __('This link will expire in :count minutes.', ['count' => $expireMinutes]) }}</p>

    <p>{{ __('If you did not request a password reset, no further action is required.') }}</p>

    @include('emails.partials.link-fallback', ['url' => $url])
@endsection
