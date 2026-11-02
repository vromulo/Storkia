@extends('layouts.auth-split', [
    'title' => 'Storkia - Forgot Password',
    'restrictMobile' => false
])

@section('content')
    <x-auth.recovery-panel
        title="Forgot your password?"
        subtitle="Enter the email you signed up with and we'll send you a link to choose a new one."
    >
        @if (session('status'))
            <div class="mb-6 p-3 bg-success/10 border-l-2 border-success rounded-r-lg text-left">
                <p class="text-xs font-bold text-success">{{ session('status') }}</p>
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="space-y-6">
            @csrf

            <div>
                <label for="email" class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5 text-left">
                    Email Address
                </label>
                <div class="relative flex items-center border-b {{ $errors->has('email') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="email"
                        placeholder="name@example.com"
                        class="w-full py-3 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40"
                    >
                </div>
                @error('email')
                    <p class="text-danger text-xs mt-1.5 text-left font-medium">{{ $message }}</p>
                @enderror
            </div>

            <div class="pt-2">
                <button
                    type="submit"
                    class="w-full py-3.5 px-6 bg-primary-dark hover:bg-text-main text-surface text-sm font-semibold rounded-full shadow-xs hover:shadow-md transition-all duration-200 cursor-pointer active:scale-[0.99]"
                >
                    Send Reset Link
                </button>
            </div>
        </form>

        <div class="mt-8 pt-8 border-t border-border-subtle/50 text-center space-y-2.5">
            <p class="text-xs text-text-muted">
                Remembered it?
                <a href="{{ $loginUrl }}" class="ml-1 font-bold text-primary hover:text-primary-dark underline underline-offset-4 transition-colors cursor-pointer">
                    Back to sign in
                </a>
            </p>
        </div>
    </x-auth.recovery-panel>
@endsection