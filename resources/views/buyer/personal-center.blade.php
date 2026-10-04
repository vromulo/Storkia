@extends('layouts.app')

@section('content')
<div class="bg-surface min-h-[calc(100vh-250px)] py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Breadcrumb Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold font-serif text-primary-dark">
                Personal Center
            </h1>
        </div>

        <!-- Two-column Body: Left Sidebar + Right Content Area -->
        <div class="flex flex-col lg:flex-row items-start bg-surface">
            <!-- Collapsible Sidebar Component -->
            <x-buyer.account-sidebar />

            <!-- Content Area for Options -->
            <main class="flex-1 w-full bg-surface rounded-none border-0 shadow-none pt-0 px-6 pb-6 sm:px-10 sm:pb-10 lg:border-l lg:border-border-subtle">
                @yield('option-content')
            </main>
        </div>
    </div>
</div>
@endsection