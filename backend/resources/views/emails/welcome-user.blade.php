@extends('emails.layout')

@section('content')
    <h1>{{ __('Welcome to :app', ['app' => $appName]) }}</h1>

    <p>{{ __('Hello :name,', ['name' => $name]) }}</p>

    <p>{{ __('An account has been created for you.') }}</p>

    <p>
        {{ __('Email') }}: <strong>{{ $email }}</strong><br>
        {{ __('Password') }}: <strong>{{ $password }}</strong>
    </p>

    <p>{{ __('On first access you will be asked to choose a new password.') }}</p>

    <p>
        <a href="{{ $url }}" class="button" target="_blank" rel="noopener">
            {{ __('Sign in') }}
        </a>
    </p>

    <p class="muted">{{ __('If the button above does not work, copy and paste this URL into your browser:') }}</p>
    <p class="muted break"><a href="{{ $url }}">{{ $url }}</a></p>
@endsection
