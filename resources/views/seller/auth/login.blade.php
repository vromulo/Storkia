@extends('layouts.auth-split', [
    'title' => 'Storkia - Seller Login',
    'mobilePrompt' => 'You are accessing the Seller Portal. Please log in from a computer to access this interface.'
])

@section('content')
    <x-auth.login-form 
        title="Seller Portal" 
        subtitle="Manage your store and orders"
        expectedRole="Seller"
        :registerRoute="route('seller.register')"
        registerText="Apply as a Seller"
    />
@endsection