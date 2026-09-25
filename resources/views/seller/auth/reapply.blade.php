@extends('layouts.auth-split', [
    'title' => 'Storkia - Re-apply for Seller',
    'mobilePrompt' => 'You are accessing the Seller Re-application. Please log in from a computer to access this interface.'
])

@section('content')
    <div class="w-full h-full flex flex-col justify-center items-center p-6 overflow-y-auto bg-surface-subtle">
        <livewire:seller.reapply-application />
    </div>
@endsection