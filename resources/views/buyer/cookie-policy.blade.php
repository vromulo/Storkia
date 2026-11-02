@extends('layouts.app', ['title' => 'Cookie Policy'])

@section('content')
@php
    /*
     * Edit these three values before publishing.
     * - $effectiveDate is fixed on purpose: change it only when the policy text changes.
     */
    $effectiveDate = 'November 2026';
    $legalName     = '[Company Legal Name]';
    $privacyEmail  = '[privacy@your-domain]';

    $card  = 'p-6 sm:p-8 rounded-3xl bg-surface border border-border-subtle shadow-xs space-y-6 scroll-mt-28';
    $badge = 'px-2.5 py-0.5 rounded-full bg-primary/10 text-primary text-[10px] font-extrabold uppercase tracking-wider';
    $h2    = 'text-xl sm:text-2xl font-serif font-bold text-primary-dark mt-1';
    $h3    = 'font-bold text-primary-dark text-sm sm:text-base mb-1.5';
    $code  = 'px-1.5 py-0.5 rounded bg-surface-subtle text-primary-dark text-[11px] font-mono';
    $th    = 'px-4 py-3 text-[10px] font-extrabold uppercase tracking-wider text-text-muted';
    $td    = 'px-4 py-3 align-top';
@endphp

<div class="bg-surface-subtle/50 min-h-screen py-8 sm:py-12 font-sans antialiased text-text-main">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

        <!-- Breadcrumbs -->
        <x-ui.breadcrumbs :items="['Cookie Policy']" />

        <!-- Hero Header Banner -->
        <div class="relative rounded-3xl bg-gradient-to-r from-primary-dark via-primary to-secondary text-white p-6 sm:p-10 shadow-md overflow-hidden">
            <div class="relative z-10 flex flex-col md:flex-row md:items-end justify-between gap-6">
                <div class="space-y-2 max-w-2xl">
                    <span class="inline-block px-3 py-1 rounded-full bg-white/20 text-[10px] font-extrabold uppercase tracking-widest">
                        Cookies &amp; Similar Technologies
                    </span>
                    <h1 class="text-2xl sm:text-4xl font-serif font-bold tracking-tight">
                        Storkia Cookie Policy
                    </h1>
                    <p class="text-xs sm:text-sm text-white/90 leading-relaxed">
                        What small files and browser storage Storkia uses to keep you signed in, protect your account and remember simple display choices, and the few outside services our pages load. Read together with our Privacy Policy under the Philippine Data Privacy Act of 2012 (RA 10173).
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                    <span class="px-3.5 py-2 rounded-xl bg-black/25 text-white text-xs font-bold border border-white/20">
                        Effective: {{ $effectiveDate }}
                    </span>
                    <a href="{{ route('privacy') }}"
                       class="px-4 py-2 rounded-xl bg-black/25 hover:bg-black/40 text-white text-xs font-bold border border-white/20 transition-colors">
                        View Privacy Policy
                    </a>
                    <a href="{{ route('terms') }}"
                       class="px-4 py-2 rounded-xl bg-white text-primary-dark hover:bg-brand-light text-xs font-bold shadow-xs transition-colors">
                        View Terms &amp; Conditions
                    </a>
                </div>
            </div>
        </div>

        <!-- Jump links -->
        <nav aria-label="On this page" class="flex flex-wrap gap-2">
            @foreach ([
                'overview'    => '1. Overview',
                'cookies'     => '2. Cookies we set',
                'storage'     => '3. Browser storage',
                'related'     => '4. Search counts & live features',
                'mobile'      => '5. Mobile apps',
                'third-party' => '6. Outside services',
                'choices'     => '7. Your choices',
                'contact'     => '8. Changes & contact',
            ] as $anchor => $label)
                <a href="#{{ $anchor }}"
                   class="px-3.5 py-2 rounded-xl bg-surface border border-border-subtle text-xs font-semibold text-text-muted hover:text-text-main transition-colors">
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <!-- ================================================================= -->
        <!-- 1. OVERVIEW                                                        -->
        <!-- ================================================================= -->
        <section id="overview" class="{{ $card }}">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-4 border-b border-border-subtle">
                <div>
                    <span class="{{ $badge }}">Section 1</span>
                    <h2 class="{{ $h2 }}">Overview</h2>
                </div>
                <span class="text-xs font-bold text-text-muted">Applies to every Storkia portal</span>
            </div>

            <div class="space-y-5 text-xs sm:text-sm leading-relaxed text-text-main">
                <div>
                    <h3 class="{{ $h3 }}">1.1 What these technologies are</h3>
                    <p>
                        A cookie is a small text file that a website saves in your browser. Browsers also offer
                        <strong>browser storage</strong> (localStorage and sessionStorage), which works in a similar way but is not sent to the website with every request. In our mobile apps, a <strong>sign-in token</strong> kept on your phone does the same job a login cookie does in a browser. This policy covers all of these, which we call &ldquo;cookies and similar technologies&rdquo;.
                    </p>
                </div>

                <div>
                    <h3 class="{{ $h3 }}">1.2 Who this applies to</h3>
                    <p>
                        {{ $legalName }} (&ldquo;Storkia&rdquo;, &ldquo;we&rdquo;) operates the Storkia website and portals used by Buyers, Seller Merchants, Logistics Sorting Hubs, First-Mile Couriers, Last-Mile Delivery Riders and Storkia staff. This policy applies to all of them. Where a rule differs by portal, we say so.
                    </p>
                </div>

                <div class="rounded-2xl bg-primary/5 border border-primary/15 p-4 sm:p-5 space-y-1.5">
                    <h3 class="font-bold text-primary-dark text-sm sm:text-base">1.3 Our approach: strictly necessary only</h3>
                    <p>
                        Storkia uses only the cookies and storage that the site needs to work and to keep accounts secure. We do <strong>not</strong> use advertising cookies, analytics or visitor-tracking cookies, social-media plug-in cookies, or cross-site tracking of any kind.
                    </p>
                    <p>
                        Because every cookie we set is strictly necessary, we do not show a cookie consent banner. If that ever changes, we will update this policy first and ask for your consent before using any optional cookie.
                    </p>
                </div>
            </div>
        </section>

        <!-- ================================================================= -->
        <!-- 2. COOKIES WE SET                                                  -->
        <!-- ================================================================= -->
        <section id="cookies" class="{{ $card }}">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-4 border-b border-border-subtle">
                <div>
                    <span class="{{ $badge }}">Section 2</span>
                    <h2 class="{{ $h2 }}">Cookies We Set</h2>
                </div>
                <span class="text-xs font-bold text-text-muted">All first-party &bull; All strictly necessary</span>
            </div>

            <div class="space-y-5 text-xs sm:text-sm leading-relaxed text-text-main">
                <p>
                    These are set by Storkia itself, not by an outside company. None of them is used to follow you across other websites.
                </p>

                <div class="overflow-x-auto rounded-2xl border border-border-subtle">
                    <table class="w-full min-w-[640px] text-left text-xs sm:text-sm">
                        <caption class="sr-only">Cookies set by Storkia</caption>
                        <thead class="bg-surface-subtle">
                            <tr>
                                <th scope="col" class="{{ $th }}">Cookie</th>
                                <th scope="col" class="{{ $th }}">What it does</th>
                                <th scope="col" class="{{ $th }}">How long it lasts</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-subtle">
                            <tr>
                                <td class="{{ $td }}"><code class="{{ $code }}">storkia-session</code></td>
                                <td class="{{ $td }}">
                                    Keeps you signed in as you move between pages and holds short-lived information needed to finish what you are doing, such as the item chosen for &ldquo;Buy Now&rdquo; checkout, steps of a sign-in or password check, and one-time notices. The cookie contains only a random ID; the details stay on our server. Sent only over HTTPS on our live site, and not readable by page scripts.
                                </td>
                                <td class="{{ $td }}">Ends after a period of inactivity (see the table below), then you are signed out.</td>
                            </tr>
                            <tr>
                                <td class="{{ $td }}"><code class="{{ $code }}">XSRF-TOKEN</code></td>
                                <td class="{{ $td }}">
                                    Protects forms and in-page actions (such as placing an order or changing settings) against requests forged from another website. Our own page scripts read it so they can attach it to requests.
                                </td>
                                <td class="{{ $td }}">Same as <code class="{{ $code }}">storkia-session</code>.</td>
                            </tr>
                            <tr>
                                <td class="{{ $td }}">
                                    <code class="{{ $code }}">remember_web_&hellip;</code><br>
                                    <code class="{{ $code }}">remember_rider_&hellip;</code><br>
                                    <code class="{{ $code }}">remember_courier_&hellip;</code>
                                </td>
                                <td class="{{ $td }}">
                                    Set <strong>only if you tick &ldquo;Remember me&rdquo;</strong> when you sign in. It keeps you signed in on that device after your session would otherwise end. Buyers, Sellers and Logistics hubs use the <code class="{{ $code }}">web</code> version, Riders the <code class="{{ $code }}">rider</code> version and Couriers the <code class="{{ $code }}">courier</code> version. The staff admin portal does not use it. Not readable by page scripts.
                                </td>
                                <td class="{{ $td }}">Up to 400 days, or until you sign out or your password is changed or reset, whichever comes first.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div>
                    <h3 class="{{ $h3 }}">2.1 How long you stay signed in, by portal</h3>
                    <div class="overflow-x-auto rounded-2xl border border-border-subtle">
                        <table class="w-full min-w-[480px] text-left text-xs sm:text-sm">
                            <caption class="sr-only">Sign-in inactivity limits by portal</caption>
                            <thead class="bg-surface-subtle">
                                <tr>
                                    <th scope="col" class="{{ $th }}">Portal</th>
                                    <th scope="col" class="{{ $th }}">Signed out after inactivity of</th>
                                    <th scope="col" class="{{ $th }}">Why</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-subtle">
                                <tr>
                                    <td class="{{ $td }}">Buyers, Sellers, Logistics hubs</td>
                                    <td class="{{ $td }}">2 hours</td>
                                    <td class="{{ $td }}">Standard account protection.</td>
                                </tr>
                                <tr>
                                    <td class="{{ $td }}">Riders and Couriers</td>
                                    <td class="{{ $td }}">8 hours</td>
                                    <td class="{{ $td }}">A delivery can take hours before proof is submitted.</td>
                                </tr>
                                <tr>
                                    <td class="{{ $td }}">Storkia admin staff</td>
                                    <td class="{{ $td }}">30 minutes</td>
                                    <td class="{{ $td }}">Extra protection for administrative access.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-text-muted">
                        We may also sign you out earlier if your account is suspended, archived or blocked, or if your password is changed.
                    </p>
                </div>
            </div>
        </section>

        <!-- ================================================================= -->
        <!-- 3. BROWSER STORAGE                                                 -->
        <!-- ================================================================= -->
        <section id="storage" class="{{ $card }}">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-4 border-b border-border-subtle">
                <div>
                    <span class="{{ $badge }}">Section 3</span>
                    <h2 class="{{ $h2 }}">Browser Storage</h2>
                </div>
                <span class="text-xs font-bold text-text-muted">Stays on your device</span>
            </div>

            <div class="space-y-5 text-xs sm:text-sm leading-relaxed text-text-main">
                <p>
                    These items only remember how a page looks for you. They hold no personal information and are not sent to our servers.
                </p>

                <div class="overflow-x-auto rounded-2xl border border-border-subtle">
                    <table class="w-full min-w-[640px] text-left text-xs sm:text-sm">
                        <caption class="sr-only">Browser storage items used by Storkia</caption>
                        <thead class="bg-surface-subtle">
                            <tr>
                                <th scope="col" class="{{ $th }}">Name</th>
                                <th scope="col" class="{{ $th }}">Where</th>
                                <th scope="col" class="{{ $th }}">What it remembers</th>
                                <th scope="col" class="{{ $th }}">How long</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-subtle">
                            <tr>
                                <td class="{{ $td }}"><code class="{{ $code }}">storkiaCarouselSlide</code></td>
                                <td class="{{ $td }}">Sign-in, registration and password-recovery pages</td>
                                <td class="{{ $td }}">Which picture of the side slideshow you were on, so it does not jump back to the first one when you move between these pages.</td>
                                <td class="{{ $td }}">Until you close the tab (sessionStorage).</td>
                            </tr>
                            <tr>
                                <td class="{{ $td }}">
                                    <code class="{{ $code }}">sellerSidebarOpen</code><br>
                                    <code class="{{ $code }}">sidebarOpen</code><br>
                                    <code class="{{ $code }}">adminSidebarOpen</code>
                                </td>
                                <td class="{{ $td }}">Seller portal, Logistics portal, and staff admin portal (in that order)</td>
                                <td class="{{ $td }}">Whether the dashboard side menu is open or collapsed.</td>
                                <td class="{{ $td }}">Until you clear your browser data (localStorage).</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ================================================================= -->
        <!-- 4. SEARCH COUNTS & LIVE FEATURES                                   -->
        <!-- ================================================================= -->
        <section id="related" class="{{ $card }}">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-4 border-b border-border-subtle">
                <div>
                    <span class="{{ $badge }}">Section 4</span>
                    <h2 class="{{ $h2 }}">Search Counts &amp; Live Features</h2>
                </div>
                <span class="text-xs font-bold text-text-muted">Related technologies</span>
            </div>

            <div class="space-y-5 text-xs sm:text-sm leading-relaxed text-text-main">
                <div>
                    <h3 class="{{ $h3 }}">4.1 Popular searches</h3>
                    <p>
                        To show &ldquo;Top Searches&rdquo; and the Trends page, we keep a log of what shoppers search for. We store the cleaned-up search words, and we skip searches that look personal, such as an email address, phone or card number, or web address. To count how many <em>different</em> shoppers searched without knowing who they are, each search is saved with a one-way scrambled key made from your account, your browser session or your device details. That key cannot be turned back into your identity, and we do not save your user ID, IP address or session ID alongside the search. Search records are deleted after 90 days.
                    </p>
                </div>

                <div>
                    <h3 class="{{ $h3 }}">4.2 Chat and live updates</h3>
                    <p>
                        Buyer&ndash;Seller chat and live notifications use a real-time connection (WebSocket) that is not a cookie. It uses your existing <code class="{{ $code }}">storkia-session</code> cookie to confirm you are allowed to join your own conversations, and does not set any additional cookie.
                    </p>
                </div>
            </div>
        </section>

        <!-- ================================================================= -->
        <!-- 5. MOBILE APPS                                                     -->
        <!-- ================================================================= -->
        <section id="mobile" class="{{ $card }}">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-4 border-b border-border-subtle">
                <div>
                    <span class="{{ $badge }}">Section 5</span>
                    <h2 class="{{ $h2 }}">Mobile Apps</h2>
                </div>
                <span class="text-xs font-bold text-text-muted">Where you use a Storkia app</span>
            </div>

            <div class="space-y-3 text-xs sm:text-sm leading-relaxed text-text-main">
                <p>
                    Storkia mobile apps do not use cookies. When you sign in, the app stores a <strong>sign-in token</strong> on your phone so you do not have to enter your password for every action. Each token:
                </p>
                <ul class="list-disc pl-5 space-y-1">
                    <li>works only for the kind of account it was issued for (Buyer, Rider or Courier);</li>
                    <li>expires after 30 days;</li>
                    <li>is limited to your 5 most recent devices &mdash; signing in on a sixth device signs out the oldest one; and</li>
                    <li>is deleted from our records when you sign out in the app.</li>
                </ul>
            </div>
        </section>

        <!-- ================================================================= -->
        <!-- 6. OUTSIDE SERVICES                                                -->
        <!-- ================================================================= -->
        <section id="third-party" class="{{ $card }}">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-4 border-b border-border-subtle">
                <div>
                    <span class="{{ $badge }}">Section 6</span>
                    <h2 class="{{ $h2 }}">Outside Services Our Pages Load</h2>
                </div>
                <span class="text-xs font-bold text-text-muted">No advertising or tracking</span>
            </div>

            <div class="space-y-5 text-xs sm:text-sm leading-relaxed text-text-main">
                <p>
                    Some pages download fonts or open-source code libraries directly from outside providers. When this happens, your browser contacts the provider and shares standard request details such as your IP address and browser type. We use these services only to display and run our pages, not for advertising or tracking.
                </p>

                <div class="overflow-x-auto rounded-2xl border border-border-subtle">
                    <table class="w-full min-w-[640px] text-left text-xs sm:text-sm">
                        <caption class="sr-only">Outside services loaded by Storkia pages</caption>
                        <thead class="bg-surface-subtle">
                            <tr>
                                <th scope="col" class="{{ $th }}">Provider</th>
                                <th scope="col" class="{{ $th }}">Where it is used</th>
                                <th scope="col" class="{{ $th }}">What it provides</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-subtle">
                            <tr>
                                <td class="{{ $td }}">
                                    <strong>Google Fonts</strong><br>
                                    <span class="text-text-muted">fonts.googleapis.com, fonts.gstatic.com</span>
                                </td>
                                <td class="{{ $td }}">Storefront pages and the staff admin portal</td>
                                <td class="{{ $td }}">The typefaces used for headings and text.</td>
                            </tr>
                            <tr>
                                <td class="{{ $td }}">
                                    <strong>jsDelivr</strong><br>
                                    <span class="text-text-muted">cdn.jsdelivr.net</span>
                                </td>
                                <td class="{{ $td }}">Staff admin sign-in and verification pages; the Seller shipping-label page</td>
                                <td class="{{ $td }}">Open-source scripts for page interactivity and for drawing QR codes.</td>
                            </tr>
                            <tr>
                                <td class="{{ $td }}">
                                    <strong>unpkg</strong><br>
                                    <span class="text-text-muted">unpkg.com</span>
                                </td>
                                <td class="{{ $td }}">Rider and Courier dashboards</td>
                                <td class="{{ $td }}">An open-source script that scans package QR codes with the phone camera.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="text-text-muted">
                    How each provider handles the information it receives is explained in its own privacy policy.
                </p>
            </div>
        </section>

        <!-- ================================================================= -->
        <!-- 7. YOUR CHOICES                                                    -->
        <!-- ================================================================= -->
        <section id="choices" class="{{ $card }}">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-4 border-b border-border-subtle">
                <div>
                    <span class="{{ $badge }}">Section 7</span>
                    <h2 class="{{ $h2 }}">Your Choices</h2>
                </div>
                <span class="text-xs font-bold text-text-muted">Republic Act No. 10173</span>
            </div>

            <div class="space-y-5 text-xs sm:text-sm leading-relaxed text-text-main">
                <div>
                    <h3 class="{{ $h3 }}">7.1 Staying signed in</h3>
                    <p>
                        &ldquo;Remember me&rdquo; is optional. Leave it unticked on a shared or public device. Signing out removes the sign-in cookie from that device. If you lose a device, change your password and your other sessions will be signed out.
                    </p>
                </div>

                <div>
                    <h3 class="{{ $h3 }}">7.2 Browser settings</h3>
                    <p>
                        You can block or delete cookies and clear stored site data in your browser settings at any time. Because the cookies in Section 2 are strictly necessary, blocking them will stop sign-in, checkout and secure forms from working. Clearing the items in Section 3 only resets those display choices.
                    </p>
                </div>

                <div>
                    <h3 class="{{ $h3 }}">7.3 Why we do not ask for consent</h3>
                    <p>
                        We set these cookies because they are needed to provide the service you asked for and to keep the platform and your account secure. Your rights over personal data, including access, correction, objection and erasure, are described in
                        <a href="{{ route('privacy', ['section' => 'security']) }}" class="font-semibold text-primary hover:underline">Section 4.3 of our Privacy Policy</a>.
                    </p>
                </div>
            </div>
        </section>

        <!-- ================================================================= -->
        <!-- 8. CHANGES & CONTACT                                               -->
        <!-- ================================================================= -->
        <section id="contact" class="{{ $card }}">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-4 border-b border-border-subtle">
                <div>
                    <span class="{{ $badge }}">Section 8</span>
                    <h2 class="{{ $h2 }}">Changes &amp; Contact</h2>
                </div>
                <span class="text-xs font-bold text-text-muted">Effective: {{ $effectiveDate }}</span>
            </div>

            <div class="space-y-5 text-xs sm:text-sm leading-relaxed text-text-main">
                <div>
                    <h3 class="{{ $h3 }}">8.1 Changes to this policy</h3>
                    <p>
                        If we add, remove or change a cookie or similar technology, we will update this page and its effective date. Where a change affects how your personal data is used, we may also notify you through the site or by email.
                    </p>
                </div>

                <div>
                    <h3 class="{{ $h3 }}">8.2 Questions</h3>
                    <p>
                        For questions about this policy, contact Storkia Data Privacy Support at
                        <strong>{{ $privacyEmail }}</strong>, or write to {{ $legalName }}.
                    </p>
                </div>
            </div>
        </section>

    </div>
</div>
@endsection