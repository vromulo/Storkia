@extends('layouts.app')

@section('content')
<div class="bg-surface-subtle min-h-[calc(100vh-250px)] py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Breadcrumb Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold font-serif text-primary-dark">Personal Center</h1>
        </div>

        <!-- Two-column Body: Left Sidebar + Right Content Area -->
        <div class="flex flex-col lg:flex-row gap-6 items-start">
            <!-- Collapsible Sidebar Component -->
            <x-buyer.account-sidebar />

            <!-- Content Area for Options -->
            <main class="flex-1 w-full bg-surface rounded-2xl sm:rounded-3xl border border-border-subtle p-6 sm:p-8 shadow-xs">
                @yield('option-content')
            </main>
        </div>
    </div>
</div>
@endsection