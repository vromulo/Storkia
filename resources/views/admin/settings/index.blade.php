@extends('layouts.admin', ['title' => 'Storkia Admin - Platform & Hero Banner Settings'])

@section('content')
<div class="p-8 lg:p-10 max-w-7xl mx-auto space-y-8 select-none">

    <div>
        <h1 class="text-3xl font-bold text-primary-dark">Platform Settings, Storefront Hero Carousel &amp; Policies</h1>
        <p class="text-sm text-text-muted mt-1">
            Customize the Hero Carousel slides displayed on the Buyer Home Page (<code>/shop</code>) and publish official platform announcements and policy updates.
        </p>
    </div>

    @if(session('success'))
        <div class="p-4 rounded-2xl bg-green-50 border border-green-200 text-green-700 text-xs sm:text-sm font-bold">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-xs sm:text-sm space-y-1">
            @foreach($errors->all() as $err)
                <p class="font-semibold">{{ $err }}</p>
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

        <!-- Left: Hero Banner Creator & List -->
        <div class="lg:col-span-7 space-y-6">
            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4">
                <div class="border-b border-border-subtle pb-3">
                    <h2 class="text-base font-bold text-primary-dark">1. Add Storefront Hero Carousel Slide (Connected to Home Page)</h2>
                    <p class="text-xs text-text-muted">Slides added here dynamically power the Hero Carousel on <code>home.blade.php</code>.</p>
                </div>

                <form action="{{ route('admin.settings.hero-banners.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4 text-xs">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                        <div class="sm:col-span-2">
                            <label class="block font-bold text-text-muted uppercase mb-1">Headline Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" required maxlength="150" placeholder="e.g. Mid-Year Super Deals Up to 60% Off" class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-bold">
                        </div>
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Badge Pill Text</label>
                            <input type="text" name="badge_text" value="Limited Offer" maxlength="60" class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface">
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Subtitle Description</label>
                        <input type="text" name="subtitle" maxlength="255" placeholder="Fast delivery. Exclusive deals from verified stores right to your doorstep." class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Button Label <span class="text-danger">*</span></label>
                            <input type="text" name="cta_text" value="Shop Now" required maxlength="60" class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-bold">
                        </div>
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Button Target URL <span class="text-danger">*</span></label>
                            <input type="text" name="cta_url" value="#products-grid" required maxlength="255" class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-mono">
                        </div>
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Slide Order <span class="text-danger">*</span></label>
                            <input type="number" name="sort_order" value="1" min="1" max="20" required class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-bold">
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Background Banner Image (JPG/PNG/WebP, Max 5MB)</label>
                        <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="w-full p-2 rounded-xl border border-border-subtle bg-surface">
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white font-bold shadow-xs cursor-pointer">
                            Publish Hero Slide
                        </button>
                    </div>
                </form>
            </div>

            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4">
                <h3 class="text-sm font-bold text-primary-dark">Configured Hero Carousel Slides ({{ $heroBanners->count() }})</h3>
                @if($heroBanners->isEmpty())
                    <p class="text-xs text-text-muted">Using default built-in Storkia Hero Carousel slides. Add a slide above to customize the storefront carousel.</p>
                @else
                    <div class="divide-y divide-border-subtle text-xs">
                        @foreach($heroBanners as $hb)
                            <div class="py-3.5 flex items-center justify-between gap-4">
                                <div class="flex items-center gap-3 min-w-0">
                                    @if($hb->image_path)
                                        <img src="{{ asset('storage/' . $hb->image_path) }}" class="w-16 h-11 rounded-xl object-cover border border-border-subtle shrink-0" alt="Slide">
                                    @endif
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono font-bold text-primary-dark">#{{ $hb->sort_order }}</span>
                                            <span class="font-bold text-text-main truncate">{{ $hb->title }}</span>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $hb->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                                {{ $hb->is_active ? 'Active' : 'Hidden' }}
                                            </span>
                                        </div>
                                        <p class="text-text-muted truncate">{{ $hb->subtitle }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <form action="{{ route('admin.settings.hero-banners.toggle', $hb->id) }}" method="POST" class="m-0">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="px-3 py-1.5 rounded-xl border border-border-subtle font-bold cursor-pointer">
                                            {{ $hb->is_active ? 'Hide' : 'Show' }}
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.settings.hero-banners.destroy', $hb->id) }}" method="POST" class="m-0">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="px-3 py-1.5 rounded-xl bg-red-50 text-danger font-bold cursor-pointer">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <!-- Right: Platform Announcements & Policies -->
        <div class="lg:col-span-5 space-y-6">
            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4">
                <div class="border-b border-border-subtle pb-3">
                    <h2 class="text-base font-bold text-primary-dark">2. Post Platform Announcement / Policy Update</h2>
                    <p class="text-xs text-text-muted">Publish platform-wide announcements, policy revisions, or maintenance notices.</p>
                </div>

                <form action="{{ route('admin.settings.announcements.store') }}" method="POST" class="space-y-4 text-xs">
                    @csrf
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" required maxlength="150" placeholder="e.g. Updated 3-Strike COD Abandonment & Return Policy" class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-bold">
                    </div>

                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Category</label>
                        <select name="category" class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-semibold">
                            <option value="announcement">Platform Announcement</option>
                            <option value="policy_update">Platform Policy Update</option>
                            <option value="maintenance">System Maintenance Notice</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Content / Policy Body <span class="text-danger">*</span></label>
                        <textarea name="body" rows="4" required minlength="10" maxlength="3000" placeholder="Enter announcement or policy details..." class="w-full p-3 rounded-xl border border-border-subtle bg-surface"></textarea>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-primary-dark hover:bg-text-main text-white font-bold cursor-pointer">
                            Publish Announcement
                        </button>
                    </div>
                </form>
            </div>

            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4">
                <h3 class="text-sm font-bold text-primary-dark">Published Announcements &amp; Policies ({{ $announcements->count() }})</h3>
                @if($announcements->isEmpty())
                    <p class="text-xs text-text-muted">No announcements posted yet.</p>
                @else
                    <div class="divide-y divide-border-subtle text-xs">
                        @foreach($announcements as $ann)
                            <div class="py-3 space-y-1.5">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-bold text-text-main">{{ $ann->title }}</span>
                                    <form action="{{ route('admin.settings.announcements.toggle', $ann->id) }}" method="POST" class="m-0">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase cursor-pointer {{ $ann->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                            {{ $ann->is_active ? 'Active' : 'Archived' }}
                                        </button>
                                    </form>
                                </div>
                                <p class="text-text-muted leading-relaxed">{{ $ann->body }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

    </div>

</div>
@endsection