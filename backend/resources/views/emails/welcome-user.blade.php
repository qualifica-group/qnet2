@extends('emails.layout')

@section('content')
    <h1>{{ __('Welcome to :app', ['app' => $appName]) }}</h1>

    <p>{{ __('Hello :name,', ['name' => $name]) }}</p>

    <p>{{ __('An account has been created for you.') }}</p>

    @if ($isInvite)
        <p>{{ __('Click the button below to choose your password and complete your first access.') }}</p>
    @else
        <p>{{ __('Your temporary password will be communicated to you by the administrator. On first access you will be asked to choose a new one.') }}</p>
    @endif

    <p>
        <a href="{{ $url }}" class="button" target="_blank" rel="noopener">
            {{ $isInvite ? __('Set your password') : __('Sign in') }}
        </a>
    </p>

    @if ($isInvite)
        <p>{{ __('This link will expire in :count hours.', ['count' => $expireHours]) }}</p>
    @endif

    <p class="muted">{{ __('If the button above does not work, copy and paste this URL into your browser:') }}</p>
    <p class="muted break"><a href="{{ $url }}">{{ $url }}</a></p>
@endsection
