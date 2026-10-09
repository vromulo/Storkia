@extends('layouts.auth-split', [
    'title' => 'Storkia - Sign in',
    'restrictMobile' => false
])

@section('content')
    <x-auth.login-form 
        title="Welcome Back" 
        subtitle="Sign in to your customer account"
        expectedRole="Buyer"
        :registerRoute="route('register')"
        registerText="Create an account"
    />
@endsection