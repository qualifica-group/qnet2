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

    @include('emails.partials.button', ['url' => $url, 'label' => __('Sign in')])

    @include('emails.partials.link-fallback', ['url' => $url])
@endsection
