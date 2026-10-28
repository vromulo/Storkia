@extends('layouts.auth-split', [
    'title' => 'Storkia - Logistics Login',
    'mobilePrompt' => 'You are accessing the Logistics Operations Portal. Please log in from a computer to access this interface.'
])

@section('content')
    <x-auth.login-form 
        title="Logistics Operations" 
        subtitle="Sign in to your Main Hub, Branch Hub, Courier, or Rider account"
        expectedRole="Logistics"
        :registerRoute="route('logistics.register')"
        registerText="Apply for Main Logistics Hub"
        :showRegister="true"
        :showSecondaryRegister="false"
    />
@endsection