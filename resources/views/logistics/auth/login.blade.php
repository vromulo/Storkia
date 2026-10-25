@extends('layouts.auth-split', [
    'title' => 'Storkia - Logistics Login',
    'mobilePrompt' => 'You are accessing the Logistics Operations Portal. Please log in from a computer to access this interface.'
])

@section('content')
    <x-auth.login-form 
        title="Logistics Operations" 
        subtitle="Sign in to your courier dashboard"
        expectedRole="Logistics"
        :registerRoute="route('logistics.register')"
        registerText="Apply for Logistics Hub"
        :showRegister="true"
        :secondaryRegisterRoute="route('rider.register')"
        secondaryRegisterPrompt="Want to be a rider?"
        secondaryRegisterText="Apply as a Delivery Rider"
        :showSecondaryRegister="true"
        :showRiderAppButton="true"
    />
@endsection