<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Access Forbidden | Storkia</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="m-0 p-0 h-screen w-screen flex flex-col items-center justify-center bg-surface text-text-main font-sans antialiased px-6 select-none">
    <div class="max-w-md w-full text-center space-y-6">
        <div class="w-20 h-20 mx-auto rounded-3xl bg-danger/10 text-danger flex items-center justify-center shadow-inner">
            <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
        </div>

        <div>
            <span class="text-xs uppercase tracking-widest font-extrabold text-danger">403 Forbidden</span>
            <h1 class="text-3xl font-serif text-primary-dark font-bold mt-1">Access Restricted</h1>
            <p class="text-sm text-text-muted mt-2 leading-relaxed">
                {{ $exception->getMessage() ?: 'You do not have administrative or portal permissions to view this resource.' }}
            </p>
        </div>

        <div class="pt-4 flex flex-col sm:flex-row items-center justify-center gap-3">
            @auth
                @php
                    $dashboardUrl = match(auth()->user()->role) {
                        'Seller'    => route('seller.seller-dashboard'),
                        'Logistics' => route('logistics.logistics-dashboard'),
                        default     => route('home'),
                    };
                @endphp
                <a href="{{ $dashboardUrl }}" class="w-full sm:w-auto px-6 py-3 bg-primary text-white text-xs font-bold rounded-xl shadow-md hover:bg-primary-dark transition-colors cursor-pointer">
                    Return to Your Portal
                </a>
            @else
                <a href="/" class="w-full sm:w-auto px-6 py-3 bg-primary text-white text-xs font-bold rounded-xl shadow-md hover:bg-primary-dark transition-colors cursor-pointer">
                    Return to Homepage
                </a>
            @endauth
        </div>
    </div>
</body>
</html>