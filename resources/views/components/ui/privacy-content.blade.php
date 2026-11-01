@props([
    'defaultTab' => 'all',
    'compact' => false,
])

<div x-data="{
        activeSectionTab: '{{ $defaultTab }}',
        setSectionTab(tab) {
            this.activeSectionTab = tab;
        }
     }"
     class="w-full space-y-6 font-sans text-text-main select-none">

    <!-- Section Switcher Tabs -->
    <div class="sticky top-0 z-20 bg-surface/95 backdrop-blur-md py-3 border-b border-border-subtle">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="inline-flex flex-wrap items-center gap-1.5 p-1.5 bg-surface-subtle border border-border-subtle rounded-2xl w-full sm:w-auto">
                <button type="button"
                        @click="setSectionTab('all')"
                        :class="activeSectionTab === 'all' ? 'bg-primary-dark text-white font-bold shadow-xs' : 'text-text-muted hover:text-text-main font-semibold'"
                        class="flex-1 sm:flex-initial px-3.5 py-2 rounded-xl text-xs transition-all cursor-pointer whitespace-nowrap">
                    All Privacy Sections
                </button>
                <button type="button"
                        @click="setSectionTab('buyer')"
                        :class="activeSectionTab === 'buyer' ? 'bg-primary text-white font-bold shadow-xs' : 'text-text-muted hover:text-text-main font-semibold'"
                        class="flex-1 sm:flex-initial px-3.5 py-2 rounded-xl text-xs transition-all cursor-pointer whitespace-nowrap">
                    1. Buyer &amp; ID Privacy
                </button>
                <button type="button"
                        @click="setSectionTab('seller')"
                        :class="activeSectionTab === 'seller' ? 'bg-primary text-white font-bold shadow-xs' : 'text-text-muted hover:text-text-main font-semibold'"
                        class="flex-1 sm:flex-initial px-3.5 py-2 rounded-xl text-xs transition-all cursor-pointer whitespace-nowrap">
                    2. Seller Merchant Privacy
                </button>
                <button type="button"
                        @click="setSectionTab('logistics')"
                        :class="activeSectionTab === 'logistics' ? 'bg-primary text-white font-bold shadow-xs' : 'text-text-muted hover:text-text-main font-semibold'"
                        class="flex-1 sm:flex-initial px-3.5 py-2 rounded-xl text-xs transition-all cursor-pointer whitespace-nowrap">
                    3. Logistics &amp; Fleet Privacy
                </button>
                <button type="button"
                        @click="setSectionTab('security')"
                        :class="activeSectionTab === 'security' ? 'bg-primary text-white font-bold shadow-xs' : 'text-text-muted hover:text-text-main font-semibold'"
                        class="flex-1 sm:flex-initial px-3.5 py-2 rounded-xl text-xs transition-all cursor-pointer whitespace-nowrap">
                    4. Security, Audit &amp; DPA Rights
                </button>
            </div>

            <span class="text-[11px] font-semibold text-text-muted hidden xl:inline-block">
                Republic Act No. 10173 (Data Privacy Act of 2012)
            </span>
        </div>
    </div>

    <!-- ================================================================= -->
    <!-- PART I: BUYER, SHOPPER & IDENTITY VERIFICATION PRIVACY            -->
    <!-- ================================================================= -->
    <div x-show="activeSectionTab === 'all' || activeSectionTab === 'buyer'"
         x-transition.opacity
         class="space-y-6">

        <div class="p-6 sm:p-8 rounded-3xl bg-surface border border-border-subtle shadow-xs space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-4 border-b border-border-subtle">
                <div>
                    <span class="px-2.5 py-0.5 rounded-full bg-primary/10 text-primary text-[10px] font-extrabold uppercase tracking-wider">
                        Part I &bull; Buyer &amp; Identity Data
                    </span>
                    <h2 class="text-xl sm:text-2xl font-serif font-bold text-primary-dark mt-1">
                        Buyer Personal Data, Address Book &amp; Government ID Verification
                    </h2>
                </div>
                <span class="text-xs font-bold text-text-muted">Applies to All Shoppers &amp; Buyers</span>
            </div>

            <div class="space-y-5 text-xs sm:text-sm leading-relaxed text-text-main">
                <div>
                    <h3 class="font-bold text-primary-dark text-sm sm:text-base mb-1.5">
                        1.1 Personal Information Collected at Registration &amp; Profile Management
                    </h3>
                    <p class="text-text-muted">
                        When you create a Buyer account on Storkia, we collect your <strong>First Name, Middle Initial, Last Name</strong> (which must match your government-issued ID), <strong>Sex</strong>, <strong>Date of Birth</strong> (to verify that you are at least 18 years old), <strong>Email Address</strong>, and optional <strong>Philippine Mobile Number (+63)</strong>. Storkia automatically generates a unique public username (`username`) and allows you to upload a profile avatar, which is re-encoded into WebP format. In Account Management, your email address and phone number are partially masked (e.g., `j****e@domain.com`, `63917****123`) to protect against shoulder-surfing and unauthorized viewing.
                    </p>
                </div>

                <div>
                    <h3 class="font-bold text-primary-dark text-sm sm:text-base mb-1.5">
                        1.2 How Buyer Government ID Verification Works (`identity_verifications`)
                    </h3>
                    <p class="text-text-muted">
                        To protect the marketplace against fraudulent return/refund disputes, Buyers may submit a <strong>Driver's License</strong> (front and back), <strong>National Identity Card</strong> (front and back), or <strong>Passport</strong> (front data page) via file upload or live browser camera capture (`navigator.mediaDevices.getUserMedia`):
                    </p>
                    <ul class="list-disc list-inside space-y-1.5 text-text-muted mt-2">
                        <li><strong>Strict Administrative Access Only:</strong> Your uploaded Government ID images are stored in restricted storage and are accessible <strong>exclusively to authorized Storkia Administrators</strong> via authenticated document-streaming routes for identity verification.</li>
                        <li><strong>Never Shared with Sellers or Riders:</strong> Your Government ID is <strong>never</strong> shared with Seller stores, Logistics hubs, Couriers, Riders, or other shoppers. Sellers only see a boolean badge (`Valid ID Verified`) when reviewing a Return &amp; Refund request.</li>
                        <li><strong>Live Camera Stream Privacy:</strong> When you select <em>Take a Photo</em>, camera frames are processed locally in your browser canvas and transmitted over HTTPS only when you click <em>Capture</em>. The camera stream is immediately terminated upon capture or cancellation.</li>
                    </ul>
                </div>

                <div>
                    <h3 class="font-bold text-primary-dark text-sm sm:text-base mb-1.5">
                        1.3 Delivery Address Book &amp; Order Address Snapshots
                    </h3>
                    <p class="text-text-muted">
                        Buyers may store up to ten (10) delivery addresses validated against Philippine Standard Geographic Code (PSGC) Province, Municipality/City, and Barangay records. When you place an order, Storkia records an immutable `delivery_address_snapshot` (recipient name, mobile number, barangay, municipality, province, and street/building details) on the order and shipment manifest so your selected Seller, Logistics Sorting Hub, and Last-Mile Delivery Rider can fulfill and deliver your parcel—even if you later edit or delete that address in your Address Book.
                    </p>
                </div>

                <div>
                    <h3 class="font-bold text-primary-dark text-sm sm:text-base mb-1.5">
                        1.4 Direct-to-Seller GCash Payment Proofs &amp; Refund Details
                    </h3>
                    <p class="text-text-muted">
                        When you pay via <strong>Online Payment (GCash)</strong>, Storkia collects your 11-digit Sender GCash Number, payment timestamp, unique Reference Number, Reference Code, and uploaded payment receipt screenshot (`order_payment_submissions`). This proof is shared strictly with the specific Seller fulfilling your package (and Storkia Admin in the event of a payment or refund dispute) to verify payment authenticity. When filing a Return &amp; Refund request, your designated refund payout details (GCash Account Name and Mobile Number, or Storkia Wallet) are recorded solely to disburse approved refunds.
                    </p>
                </div>

                <div>
                    <h3 class="font-bold text-primary-dark text-sm sm:text-base mb-1.5">
                        1.5 Buyer–Seller Chat, Wishlist, Store Follows &amp; Masked Product Reviews
                    </h3>
                    <ul class="list-disc list-inside space-y-1.5 text-text-muted">
                        <li><strong>Real-Time Chat &amp; Online Presence:</strong> Messages, product inquiry cards, and JPG/PNG photo attachments sent between Buyers and Sellers (`messages`) are transmitted via encrypted WebSocket channels (Laravel Reverb) and stored so both parties have a complete transaction conversation history. Your online activity timestamp (`last_seen_at`) is used to display active responsiveness status in chat.</li>
                        <li><strong>Public Review Anonymization:</strong> When you submit product and service ratings (`order_item_reviews`, `order_service_ratings`), your first name is automatically masked on public product pages (e.g., `J***`) to protect your identity while displaying verified buyer feedback and review photos.</li>
                        <li><strong>Personalization Data:</strong> Your cart items, wishlist (`buyer_wishlists`), followed stores (`seller_store_follows`), and claimed vouchers (`buyer_voucher_claims`) are used to curate <em>You May Also Like</em> recommendations and issue 1-time Follower Welcome Vouchers (`FLW...`).</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================= -->
    <!-- PART II: SELLER MERCHANT PRIVACY                                  -->
    <!-- ================================================================= -->
    <div x-show="activeSectionTab === 'all' || activeSectionTab === 'seller'"
         x-transition.opacity
         class="space-y-6">

        <div class="p-6 sm:p-8 rounded-3xl bg-surface border border-border-subtle shadow-xs space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-4 border-b border-border-subtle">
                <div>
                    <span class="px-2.5 py-0.5 rounded-full bg-primary/10 text-primary text-[10px] font-extrabold uppercase tracking-wider">
                        Part II &bull; Seller Merchant Data
                    </span>
                    <h2 class="text-xl sm:text-2xl font-serif font-bold text-primary-dark mt-1">
                        Merchant Onboarding Documents, GCash Checkout Display &amp; Financial Records
                    </h2>
                </div>
                <span class="text-xs font-bold text-text-muted">Applies to Seller Applicants &amp; Stores</span>
            </div>

            <div class="space-y-5 text-xs sm:text-sm leading-relaxed text-text-main">
                <div>
                    <h3 class="font-bold text-primary-dark text-sm sm:text-base mb-1.5">
                        2.1 Merchant Onboarding &amp; Business Compliance Documents (`seller_applications`)
                    </h3>
                    <p class="text-text-muted">
                        During Seller registration and re-application, we collect the store owner's personal details, contact number, registered business pickup/return address, store name, primary line of business, <strong>Valid Government ID</strong>, and <strong>Business / DTI / Mayor's Permit</strong>. Your legal documents are accessible only to Storkia Administrators for merchant verification and to you via your authenticated Store Settings document previewer. Your public storefront displays only your Store Name, Store Logo, Line of Business, Municipality/Province location, and store performance metrics.
                    </p>
                </div>

                <div>
                    <h3 class="font-bold text-primary-dark text-sm sm:text-base mb-1.5">
                        2.2 Seller GCash Account Disclosure at Checkout (`seller_gcash_accounts`)
                    </h3>
                    <p class="text-text-muted">
                        If a Seller enables Online Payment (GCash) in Store Settings, the Seller's registered <strong>GCash Account Name</strong>, <strong>11-digit GCash Mobile Number</strong>, and optional <strong>GCash QR Code image</strong> are displayed to authenticated Buyers during Checkout Phase 1 for packages purchased from that store. Sellers may disable GCash checkout or remove their uploaded QR image at any time.
                    </p>
                </div>

                <div>
                    <h3 class="font-bold text-primary-dark text-sm sm:text-base mb-1.5">
                        2.3 Order Fulfillment Access, E-Wallet Ledgers &amp; Tax/Audit Statements
                    </h3>
                    <p class="text-text-muted">
                        Sellers receive access to Buyer recipient names, delivery addresses, contact numbers, and order notes strictly for order preparation, packing slip/shipping label generation, and return handling. Sellers must not use Buyer contact data for unsolicited marketing outside Storkia. All 90%/10% commission calculations, Direct-GCash arrears remittances, and payout withdrawal requests (`GCASH` or `BANK` account details) are logged in `commission_records`, `role_wallets`, and `wallet_transactions` to generate formal printable financial statements.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================= -->
    <!-- PART III: LOGISTICS HUB, COURIER & RIDER FLEET PRIVACY            -->
    <!-- ================================================================= -->
    <div x-show="activeSectionTab === 'all' || activeSectionTab === 'logistics'"
         x-transition.opacity
         class="space-y-6">

        <div class="p-6 sm:p-8 rounded-3xl bg-surface border border-border-subtle shadow-xs space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-4 border-b border-border-subtle">
                <div>
                    <span class="px-2.5 py-0.5 rounded-full bg-primary/10 text-primary text-[10px] font-extrabold uppercase tracking-wider">
                        Part III &bull; Logistics &amp; Fleet Data
                    </span>
                    <h2 class="text-xl sm:text-2xl font-serif font-bold text-primary-dark mt-1">
                        Sorting Hubs, Courier &amp; Rider Credentials, QR Scans &amp; Proof of Delivery
                    </h2>
                </div>
                <span class="text-xs font-bold text-text-muted">Applies to Hubs, Couriers &amp; Riders</span>
            </div>

            <div class="space-y-5 text-xs sm:text-sm leading-relaxed text-text-main">
                <div>
                    <h3 class="font-bold text-primary-dark text-sm sm:text-base mb-1.5">
                        3.1 Logistics Hub &amp; Fleet Personnel Credentials (`logistics_profiles`, `couriers`, `riders`)
                    </h3>
                    <ul class="list-disc list-inside space-y-1.5 text-text-muted">
                        <li><strong>Sorting Center Hubs:</strong> We collect the hub manager's identity, contact number, sorting facility address, Valid Government ID, and Hub Business Permit, as well as Municipality Branch Hub account records (`logistics_hubs`).</li>
                        <li><strong>First-Mile Couriers &amp; Last-Mile Riders:</strong> Logistics companies register Couriers and Riders using internal `@storkia.shop` credentials and upload their <strong>Driver's License</strong> (number, type, DL restriction codes, expiry date, and document scan), <strong>Vehicle OR/CR</strong>, <strong>Vehicle Type &amp; Plate Number</strong>, <strong>Load Capacity</strong>, and <strong>NBI Clearance</strong> (for Couriers). These documents are restricted to the employing Logistics company and Storkia Admin.</li>
                    </ul>
                </div>

                <div>
                    <h3 class="font-bold text-primary-dark text-sm sm:text-base mb-1.5">
                        3.2 Chain-of-Custody QR Scans &amp; Doorstep Proof of Delivery (`delivery_proofs`)
                    </h3>
                    <ul class="list-disc list-inside space-y-1.5 text-text-muted">
                        <li><strong>QR &amp; Package Verification Logs:</strong> Every QR token (`STORK-QR-...`) or manual package reference scan (`shipment_verifications`) records the actor role (`Courier`, `Logistics`, `Rider`), actor ID, hub ID, verification stage, and timestamp to maintain an auditable chain of custody.</li>
                        <li><strong>Doorstep Proof of Delivery (POD) Photos:</strong> When a Rider completes a delivery, the uploaded Proof of Delivery photo (optimized to WebP), recipient name, relationship, and COD collection amount are stored in `delivery_proofs` and made viewable only to the Buyer, Seller, assigned Logistics Hub, Rider, and Storkia Admin for delivery verification and dispute resolution.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================= -->
    <!-- PART IV: SECURITY, IMMUTABLE AUDIT LOGS & RA 10173 DPA RIGHTS     -->
    <!-- ================================================================= -->
    <div x-show="activeSectionTab === 'all' || activeSectionTab === 'security'"
         x-transition.opacity
         class="space-y-6">

        <div class="p-6 sm:p-8 rounded-3xl bg-surface border border-border-subtle shadow-xs space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-4 border-b border-border-subtle">
                <div>
                    <span class="px-2.5 py-0.5 rounded-full bg-primary/10 text-primary text-[10px] font-extrabold uppercase tracking-wider">
                        Part IV &bull; Security, Audit &amp; Legal Rights
                    </span>
                    <h2 class="text-xl sm:text-2xl font-serif font-bold text-primary-dark mt-1">
                        Data Security, Immutable Security Logs &amp; Your Rights Under RA 10173
                    </h2>
                </div>
                <span class="text-xs font-bold text-text-muted">Data Protection &amp; Compliance</span>
            </div>

            <div class="space-y-5 text-xs sm:text-sm leading-relaxed text-text-main">
                <div>
                    <h3 class="font-bold text-primary-dark text-sm sm:text-base mb-1.5">
                        4.1 Technical &amp; Organizational Security Measures
                    </h3>
                    <ul class="list-disc list-inside space-y-1.5 text-text-muted">
                        <li><strong>Cryptographic Hashing &amp; OTP Guards:</strong> All user passwords and 6-digit verification codes (`registration_otps`, `email_change_requests`, session password unlock tokens) are stored using one-way cryptographic hashing (`Hash::make`). Admin Portal logins require Two-Factor Email OTP authentication.</li>
                        <li><strong>Image Sanitization &amp; WebP Re-Encoding:</strong> Uploaded product images, avatars, store logos, and delivery proofs are validated for authentic MIME types and re-encoded into WebP format via Intervention Image, stripping potentially harmful embedded payloads and EXIF metadata.</li>
                        <li><strong>Session &amp; CSRF Protection:</strong> All state-changing web requests are protected by Laravel CSRF tokens, role-isolation middleware (`EnsureUserRole`, `EnsureSellerIsApproved`, `EnsureLogisticsIsApproved`), and Sanctum token authentication on mobile API routes.</li>
                    </ul>
                </div>

                <div>
                    <h3 class="font-bold text-primary-dark text-sm sm:text-base mb-1.5">
                        4.2 Immutable Security Audit Logs &amp; Anti-Fraud Monitoring (`platform_audit_logs`)
                    </h3>
                    <p class="text-text-muted">
                        For platform security, financial integrity, and regulatory compliance, Storkia automatically records an immutable audit trail (`platform_audit_logs`) whenever sensitive administrative or operational actions occur—including account suspensions/reactivations, shipment status overrides, shipping zone rate updates, and dispute resolutions. Each log entry captures the <strong>Actor Type &amp; ID</strong>, <strong>IP Address (`ip_address`)</strong>, <strong>Browser User-Agent (`user_agent`)</strong>, <strong>Old and New JSON States</strong>, <strong>Reason</strong>, and <strong>Timestamp</strong>. Storkia also maintains rolling 7-day cancellation logs (`buyer_cancellation_logs`) and 3-Strike COD abandonment sanction records (`buyer_account_sanctions`) to prevent marketplace abuse.
                    </p>
                </div>

                <div>
                    <h3 class="font-bold text-primary-dark text-sm sm:text-base mb-1.5">
                        4.3 Your Data Subject Rights Under the Philippine Data Privacy Act of 2012 (RA 10173)
                    </h3>
                    <p class="text-text-muted mb-2">
                        In accordance with <strong>Republic Act No. 10173 (Data Privacy Act of 2012)</strong> and its Implementing Rules and Regulations issued by the National Privacy Commission (NPC), you are entitled to exercise the following rights:
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div class="p-3.5 rounded-2xl bg-surface-subtle border border-border-subtle">
                            <strong class="text-primary-dark block text-xs sm:text-sm">1. Right to be Informed &amp; Right to Access</strong>
                            <p class="text-xs text-text-muted mt-0.5">You have the right to know how your personal data is processed and to view your profile, order history, address book, verified documents, and wallet ledgers at any time.</p>
                        </div>
                        <div class="p-3.5 rounded-2xl bg-surface-subtle border border-border-subtle">
                            <strong class="text-primary-dark block text-xs sm:text-sm">2. Right to Rectification</strong>
                            <p class="text-xs text-text-muted mt-0.5">You may update your name, email address (via OTP), mobile number, delivery addresses, store/hub profile, and GCash details directly through your Account or Portal Settings.</p>
                        </div>
                        <div class="p-3.5 rounded-2xl bg-surface-subtle border border-border-subtle">
                            <strong class="text-primary-dark block text-xs sm:text-sm">3. Right to Object &amp; Right to Erasure / Blocking</strong>
                            <p class="text-xs text-text-muted mt-0.5">You may remove saved addresses, wishlist items, avatars, or GCash QR codes, and request account deactivation, subject to retention of completed transaction, tax, and audit records required by law.</p>
                        </div>
                        <div class="p-3.5 rounded-2xl bg-surface-subtle border border-border-subtle">
                            <strong class="text-primary-dark block text-xs sm:text-sm">4. Right to Data Portability &amp; Redress</strong>
                            <p class="text-xs text-text-muted mt-0.5">Sellers, Logistics Hubs, and Riders may export formal printable financial and operational statements (`RPT-...`), and all users may contact Storkia Data Privacy Support regarding any privacy concern.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>