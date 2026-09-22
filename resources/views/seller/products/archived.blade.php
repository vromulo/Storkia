<!DOCTYPE html>
<html lang="en">
<!-- (Head omitted for brevity, use identical head from index.blade.php) -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storkia - Archived Products</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style> [x-cloak] { display: none !important; } .custom-scrollbar::-webkit-scrollbar { width: 4px; } </style>
</head>
<body class="m-0 p-0 h-screen w-screen font-sans antialiased text-text-main overflow-hidden bg-gradient-to-b from-surface via-surface to-brand-light/30">
    <div x-data="{ sidebarOpen: localStorage.getItem('sellerSidebarOpen') !== 'false' }" class="h-screen w-full relative flex">
        @include('components.seller-components.seller-sidebar')
        <main class="flex-1 h-screen overflow-y-auto custom-scrollbar relative" x-data="{ showContent: false }" x-init="setTimeout(() => showContent = true, 50)">
            <div class="p-8 lg:p-12" x-show="showContent" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" x-cloak>
                <div class="max-w-7xl mx-auto">
                    <h1 class="text-3xl font-bold text-primary-dark mb-8">Archived Products</h1>
                    
                    <div class="bg-white rounded-2xl shadow-sm border border-border-subtle overflow-hidden">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-brand-light/30 text-text-muted text-sm border-b border-border-subtle">
                                    <th class="p-4 font-bold">Product Name</th>
                                    <th class="p-4 font-bold">Date Archived</th>
                                    <th class="p-4 font-bold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($products as $product)
                                <tr class="border-b border-border-subtle hover:bg-brand-light/10 transition-colors text-sm">
                                    <td class="p-4 font-medium text-text-muted">{{ $product->name }}</td>
                                    <td class="p-4 text-text-muted">{{ $product->deleted_at->format('M d, Y') }}</td>
                                    <td class="p-4 text-right space-x-4">
                                        <form action="{{ route('seller.products.unarchive', $product->id) }}" method="POST" class="inline">
                                            @csrf @method('PATCH')
                                            <button class="text-primary hover:text-primary-dark font-medium cursor-pointer">Unarchive</button>
                                        </form>
                                        <form action="{{ route('seller.products.forceDelete', $product->id) }}" method="POST" class="inline">
                                            @csrf @method('DELETE')
                                            <button class="text-red-500 hover:text-red-700 font-medium cursor-pointer" onclick="return confirm('Are you sure? This cannot be undone.')">Delete Permanently</button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="3" class="p-8 text-center text-text-muted">No archived products found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>