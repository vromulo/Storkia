@props([
    'defaultTab' => 'all',
    'compact' => false,
])

<div x-data="{
        activeRoleTab: '{{ $defaultTab }}',
        setRoleTab(tab) {
            this.activeRoleTab = tab;
        }
     }"
     class="w-full space-y-6 font-sans text-text-main select-none">

    <!-- Role Switcher Tabs -->
    <div class="sticky top-0 z-20 bg-surface/95 backdrop-blur-md py-3 border-b border-border-subtle">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="inline-flex flex-wrap items-center gap-1.5 p-1.5 bg-surface-subtle border border-border-subtle rounded-2xl w-full sm:w-auto">
                <button type="button"
                        @click="setRoleTab('all')"
                        :class="activeRoleTab === 'all' ? 'bg-primary-dark text-white font-bold shadow-xs' : 'text-text-muted hover:text-text-main font-semibold'"
                        class="flex-1 sm:flex-initial px-4 py-2 rounded-xl text-xs transition-all cursor-pointer whitespace-nowrap">
                    All Unified Terms
                </button>
                <button type="button"
                        @click="setRoleTab('buyer')"
                        :class="activeRoleTab === 'buyer' ? 'bg-primary text-white font-bold shadow-xs' : 'text-text-muted hover:text-text-main font-semibold'"
                        class="flex-1 sm:flex-initial px-4 py-2 rounded-xl text-xs transition-all cursor-pointer whitespace-nowrap">
                    1. General &amp; Buyer Terms
                </button>
                <button type="button"
                        @click="setRoleTab('seller')"
                        :class="activeRoleTab === 'seller' ? 'bg-primary text-white font-bold shadow-xs' : 'text-text-muted hover:text-text-main font-semibold'"
                        class="flex-1 sm:flex-initial px-4 py-2 rounded-xl text-xs transition-all cursor-pointer whitespace-nowrap">
                    2. Seller Merchant Terms
                </button>
                <button type="button"
                        @click="setRoleTab('logistics')"
                        :class="activeRoleTab === 'logistics' ? 'bg-primary text-white font-bold shadow-xs' : 'text-text-muted hover:text-text-main font-semibold'"
                        class="flex-1 sm:flex-initial px-4 py-2 rounded-xl text-xs transition-all cursor-pointer whitespace-nowrap">
                    3. Logistics Hub &amp; Fleet Terms
                </button>
            </div>

            <span class="text-[11px] font-semibold text-text-muted hidden lg:inline-block">
                Republic Act No. 11967 (ITA) &amp; RA 10173 (DPA) Compliant
            </span>
        </div>
    </div>

    <!-- ================================================================= -->
    <!-- PART I: GENERAL & BUYER MARKETPLACE TERMS                         -->
    <!-- ================================================================= -->
    <div x-show="activeRoleTab === 'all' || activeRoleTab === 'buyer'"
         x-transition.opacity
         class="space-y-6">

        <div class="p-6 sm:p-8 rounded-3xl bg-surface border border-border-subtle shadow-xs space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-4 border-b border-border-subtle">
                <div>
                    <span class="px-2.5 py-0.5 rounded-full bg-primary/10 text-primary text-[10px] font-extrabold uppercase tracking-wider">
                        Part I &bull; General &amp; Buyer Agreement
                    </span>
                    <h2 class="text-xl sm:text-2xl font-serif font-bold text-primary-dark mt-1">
                        General Platform Rules &amp; Buyer Terms of Service
                    </h2>
                </div>
                <span class="text-xs font-bold text-text-muted">Applies to All Users &amp; Shoppers</span>
            </div>

            <div class="space-y-5 text-xs sm:text-sm leading-relaxed text-text-main">
                <div>
                    <h3 class="font-bold text-primary-dark text-sm sm:text-base mb-1.5">
                        1.1 Platform Scope, Role Separation &amp; Age Eligibility
                    </h3>
                    <p class="text-text-muted">
                        Storkia operates a multi-portal e-commerce and localized logistics ecosystem connecting verified <strong>Buyers</strong>, <strong>Sellers (Merchants)</strong>, <strong>Logistics Sorting Hubs (Main &amp; Branch Hubs)</strong>, <strong>First-Mile Couriers</strong>, and <strong>Last-Mile Delivery Riders</strong>. By registering an account or transacting on Storkia, you affirm that you are at least <strong>eighteen (18) years of age</strong> (and no older than 100 years in date-of-birth verification) with full legal capacity to enter into binding contracts under Philippine law. Each account is strictly bound to its registered role portal; cross-portal access using unauthorized credentials is automatically blocked.
                    </p>
                </div>

                <div>
                    <h3 class="font-bold text-primary-dark text-sm sm:text-base mb-1.5">
                        1.2 Account Verification, Security &amp; Credential Protection
                    </h3>
                    <ul class="list-disc list-inside space-y-1.5 text-text-muted">
                        <li><strong>Email OTP Verification:</strong> Registration and sensitive credential changes (email address updates and password resets) require a 6-digit One-Time Password (OTP) subject to a 60-second resend cooldown and a maximum of five (5) verification attempts.</li>
                        <li><strong>Password Security Standards:</strong> Passwords must be at least 8 characters long and contain at least one (1) uppercase letter, one (1) number, and one (1) special symbol.</li>
                        <li><strong>Buyer Profile &amp; Address Book:</strong> Buyers receive an auto-generated unique username upon registration (editable once every 7 days) and may store up to ten (10) Philippine Standard Geographic Code (PSGC) verified delivery addresses. Your default delivery address cannot be deleted without assigning a new default address first.</li>
                        <li><strong>Government ID Verification:</strong> Buyers may submit a Valid Government ID (Driver's License, Passport, or National Identity Card) via file upload or live camera capture. An approved Valid Government ID is <strong>strictly required</strong> before a Buyer can file a post-delivery Return &amp; Refund dispute.</li>
                    </ul>
                </div>

                <div>
                    <h3 class="font-bold text-primary-dark text-sm sm:text-base mb-1.5">
                        1.3 Product Pricing, Super Deals, Bundles &amp; Voucher Mechanics
                    </h3>
                    <ul class="list-disc list-inside space-y-1.5 text-text-muted">
                        <li><strong>Checkout Price Integrity:</strong> Checkout totals are protected by a cryptographic price signature (`price_signature`) and idempotency key. If a product's price, stock, or promotion changes while you are on the checkout screen, Storkia will halt submission and refresh the updated totals for your confirmation.</li>
                        <li><strong>Super Deals &amp; Split-Line Pricing:</strong> When a product is featured in both a time-slot <em>Super Deal</em> and a <em>Store Sale</em>, the Super Deal price applies up to the remaining flash stock limit (`flash_quantity`). Any additional units ordered beyond the remaining Super Deal stock in the same checkout are automatically split onto a separate order line at the Store Sale or regular price.</li>
                        <li><strong>1-Time Store Follower Welcome Voucher (`FLW...`):</strong> Following an official store for the first time automatically issues a 1-time <strong>₱30.00 OFF</strong> welcome voucher with <strong>₱0.00 minimum spend</strong> and <strong>7-day validity</strong>. Follower welcome vouchers are strictly limited to first-time customers of that store, redeemable once per buyer, non-stackable with other vouchers or bundle deals, and are not re-issued if you unfollow and re-follow the store.</li>
                        <li><strong>Store, Platform &amp; Free Shipping Vouchers:</strong> Claimable wallet vouchers and secret code-only vouchers are subject to minimum spend thresholds, validity windows, total usage limits, and per-buyer redemption caps. If an order is cancelled or rejected prior to completion, redeemed vouchers are automatically restored to the Buyer's wallet (unless expired).</li>
                    </ul>
                </div>

                <div>
                    <h3 class="font-bold text-primary-dark text-sm sm:text-base mb-1.5">
                        1.4 Shipping Zones, Weight Tiers &amp; Unserviceable Areas
                    </h3>
                    <p class="text-text-muted">
                        Shipping fees are calculated per Seller package based on origin-to-destination geographic tiers and chargeable parcel weight: <strong>₱45.00</strong> (Same Municipality), <strong>₱65.00</strong> (Same Province), or <strong>₱100.00</strong> (Inter-Province / Provincial), plus <strong>₱10.00 per excess kilogram</strong> (rounded up) above the first 1.00 kg, or the assigned Logistics Hub's active zone rate table. Remote or unserviceable island zones (including Batanes and Kalayaan) are automatically blocked at checkout.
                    </p>
                </div>

                <div>
                    <h3 class="font-bold text-primary-dark text-sm sm:text-base mb-1.5">
                        1.5 Payment Methods: Cash on Delivery (COD) &amp; Direct-to-Seller GCash
                    </h3>
                    <ul class="list-disc list-inside space-y-1.5 text-text-muted">
                        <li><strong>Cash on Delivery (COD):</strong> Buyers must pay the exact payable order total in cash to the assigned Last-Mile Delivery Rider upon doorstep delivery.</li>
                        <li><strong>Direct-to-Seller GCash Online Payment:</strong> Available when the Seller has configured an active GCash account. Buyers must transfer the exact package total to the Seller's registered GCash number or QR code and upload a valid payment screenshot (max 5 MB), 11-digit sender GCash number, payment timestamp, unique Reference Number, and Reference Code.</li>
                        <li><strong>Suspicious Payment Flagging &amp; Buyer Response:</strong> If a Seller flags a submitted GCash receipt as unverifiable or suspicious prior to shipment, the order moves to <em>Pending Buyer Response</em>. The Buyer may either switch the order to <strong>Cash on Delivery (COD)</strong> to continue fulfillment or cancel the order immediately.</li>
                    </ul>
                </div>

                <div>
                    <h3 class="font-bold text-primary-dark text-sm sm:text-base mb-1.5">
                        1.6 Pre-Shipment Cancellation Policy &amp; 7-Day Anti-Sabotage Quotas
                    </h3>
                    <p class="text-text-muted">
                        Buyers may cancel an order only <strong>before</strong> the Seller generates a shipment / marks the order <em>Ready for Pickup</em> (`pending_seller_acceptance`, `pending_buyer_response`, `accepted`, or `preparing`). Once a parcel is handed over or assigned to Logistics, buyer-initiated cancellation is locked. To protect merchants from inventory hoarding and sabotage, Buyer cancellations within any rolling <strong>7-day window</strong> are strictly limited to:
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2">
                        <div class="p-3.5 rounded-2xl bg-surface-subtle border border-border-subtle">
                            <span class="text-[10px] font-extrabold uppercase text-primary block">Per Product Limit</span>
                            <strong class="text-sm text-text-main">Max 2 Cancellations / 7 Days</strong>
                        </div>
                        <div class="p-3.5 rounded-2xl bg-surface-subtle border border-border-subtle">
                            <span class="text-[10px] font-extrabold uppercase text-primary block">Per Store Limit</span>
                            <strong class="text-sm text-text-main">Max 3 Cancellations / 7 Days</strong>
                        </div>
                        <div class="p-3.5 rounded-2xl bg-surface-subtle border border-border-subtle">
                            <span class="text-[10px] font-extrabold uppercase text-primary block">Total Account Limit</span>
                            <strong class="text-sm text-text-main">Max 5 Cancellations / 7 Days</strong>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="font-bold text-danger text-sm sm:text-base mb-1.5">
                        1.7 Strict 3-Strike COD Parcel Abandonment &amp; Ban Policy
                    </h3>
                    <p class="text-text-muted">
                        Unjustified refusal to accept a COD parcel at the doorstep, or failing three (3) consecutive delivery attempts, constitutes <strong>COD Parcel Abandonment</strong>. Each abandoned COD order triggers an automatic Return-to-Seller (RTS) and logs one (1) formal strike against the Buyer's account:
                    </p>
                    <ul class="list-disc list-inside space-y-1.5 text-text-muted mt-2">
                        <li><strong>Strike 1 &amp; Strike 2:</strong> Formal account warning notification.</li>
                        <li><strong>Strike 3 (First Ban Offense):</strong> Immediate <strong>7-Day Temporary Account Suspension</strong>. Once the 7-day countdown finishes, the Buyer must submit a formal written reactivation appeal (with optional supporting evidence) for Admin review.</li>
                        <li><strong>Probation &amp; Second Ban Offense (Permanent Ban):</strong> Reinstated Buyers are placed on permanent probation (`ban_level = 1`). Any subsequent COD parcel abandonment after reinstatement results in an <strong>Immediate Permanent Account Ban (`permanently_banned`)</strong> with no eligibility for appeal.</li>
                    </ul>
                </div>

                <div>
                    <h3 class="font-bold text-primary-dark text-sm sm:text-base mb-1.5">
                        1.8 72-Hour Delivery Confirmation, Returns, Refunds &amp; Ratings
                    </h3>
                    <ul class="list-disc list-inside space-y-1.5 text-text-muted">
                        <li><strong>72-Hour Confirmation Window:</strong> Upon delivery (backed by the Rider's mandatory Proof of Delivery photo), Buyers have <strong>72 hours (3 days)</strong> to inspect the parcel and click <em>Confirm Order Received</em> or file a <em>Return / Refund Dispute</em>. If no dispute is filed within 72 hours, the order automatically completes and releases escrowed funds.</li>
                        <li><strong>Return &amp; Refund Requirements:</strong> To request a return/refund within the 72-hour window, the Buyer must have a <strong>Verified Government ID</strong>, select an issue category (Damaged, Wrong Item, Missing Quantity, or Defective), upload 1 to 5 photo proofs (max 5 MB each), and specify a refund destination (GCash or Storkia Wallet).</li>
                        <li><strong>3-Day Seller Response &amp; Admin Escalation:</strong> Sellers have 72 hours (3 days) to approve or reject a return request; failure to respond auto-approves the refund. If a Seller rejects the request, the Buyer may escalate the dispute to Storkia Admin for final binding resolution.</li>
                        <li><strong>5-Point Rating System:</strong> Completed orders unlock a 5-part rating form (Product Quality, Parcel Condition, Seller Service, Logistics Hub, and Delivery Rider) and allow a one-time review edit.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================= -->
    <!-- PART II: SELLER MERCHANT TERMS                                    -->
    <!-- ================================================================= -->
    <div x-show="activeRoleTab === 'all' || activeRoleTab === 'seller'"
         x-transition.opacity
         class="space-y-6">

        <div class="p-6 sm:p-8 rounded-3xl bg-surface border border-border-subtle shadow-xs space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-4 border-b border-border-subtle">
                <div>
                    <span class="px-2.5 py-0.5 rounded-full bg-primary/10 text-primary text-[10px] font-extrabold uppercase tracking-wider">
                        Part II &bull; Merchant Agreement
                    </span>
                    <h2 class="text-xl sm:text-2xl font-serif font-bold text-primary-dark mt-1">
                        Seller Onboarding, Catalog Compliance &amp; Settlement Terms
                    </h2>
                </div>
                <span class="text-xs font-bold text-text-muted">Applies to Registered Sellers</span>
            </div>

            <div class="space-y-5 text-xs sm:text-sm leading-relaxed text-text-main">
                <div>
                    <h3 class="font-bold text-primary-dark text-sm sm:text-base mb-1.5">
                        2.1 Merchant Onboarding &amp; Business Verification
                    </h3>
                    <p class="text-text-muted">
                        To operate a storefront on Storkia, Seller applicants must submit a valid Government ID, a current Business / DTI / Mayor's Permit, a verified pickup/return facility address, and a primary Line of Business category. Catalog publishing, voucher creation, and order tools remain locked until Storkia Admin approves the application. Once approved, your registered Business Address, Line of Business, and legal documents are locked as read-only for marketplace compliance.
                    </p>
                </div>

                <div>
                    <h3 class="font-bold text-primary-dark text-sm sm:text-base mb-1.5">
                        2.2 Product Catalog &amp; Variant Compliance Review
                    </h3>
                    <ul class="list-disc list-inside space-y-1.5 text-text-muted">
                        <li><strong>Pre-Publication Admin Approval:</strong> Every new product listing—and any newly added variant or sub-variant on an already approved product—is placed in <em>Pending</em> status and hidden from public view until approved by Storkia Admin.</li>
                        <li><strong>Pricing &amp; Inventory Integrity:</strong> Variant and sub-variant prices must be at least <strong>₱20.00</strong> (up to ₱9,999,999.99) with accurate kilogram weights. Products or variants with active, unfinished orders cannot be archived or permanently deleted.</li>
                        <li><strong>Violations vs. Warnings:</strong> Listings rejected for prohibited content or misrepresentation are flagged as a <em>Violation</em> or <em>Warning</em>. Sellers must correct the cited issues before resubmitting. Sellers are also strictly prohibited from following their own store or purchasing their own listings.</li>
                    </ul>
                </div>

                <div>
                    <h3 class="font-bold text-primary-dark text-sm sm:text-base mb-1.5">
                        2.3 Order Fulfillment SLAs, Vacation Mode &amp; Handover
                    </h3>
                    <ul class="list-disc list-inside space-y-1.5 text-text-muted">
                        <li><strong>24-Hour Seller Acceptance SLA:</strong> Sellers must accept or reject incoming orders within <strong>24 hours</strong> of placement. Orders not accepted within 24 hours are automatically cancelled by the system, reserved stock and vouchers are restored, and any prepaid GCash payment is flagged for full buyer refund.</li>
                        <li><strong>3-Strike SLA Auto-Vacation Guard:</strong> Accumulating three (3) operational SLA strikes automatically switches the Seller's store into <strong>Vacation Mode</strong>, pausing Add to Cart, Buy Now, and Checkout on all store listings until re-opened.</li>
                        <li><strong>Logistics Hub Assignment &amp; Manifests:</strong> Upon preparing an order, the Seller must confirm the package count (1 to 10 packages), select either <em>Option A: Courier Pickup</em> or <em>Option B: Seller Self Drop-Off</em> at an eligible approved Logistics Hub, and attach the official Storkia QR Shipping Label (`TRK-...` / `PKG-...`) and Packing Slip.</li>
                    </ul>
                </div>

                <div>
                    <h3 class="font-bold text-primary-dark text-sm sm:text-base mb-1.5">
                        2.4 90% / 10% Split-Base Commission, E-Wallet &amp; Direct-GCash Arrears
                    </h3>
                    <ul class="list-disc list-inside space-y-1.5 text-text-muted">
                        <li><strong>Merchandise Split (90% Seller / 10% Admin):</strong> Sellers earn <strong>90%</strong> of the Commissionable Product Subtotal (Original Merchandise Subtotal minus Seller-sponsored Store Vouchers/Bundles). Storkia retains a <strong>10% Platform Commission</strong>. Admin-sponsored Platform Vouchers (`PLAT...`) and Admin Free Shipping Vouchers are 100% subsidized by Storkia Admin and never reduce the Seller's commissionable base.</li>
                        <li><strong>Direct-to-Seller GCash Auto-Offset &amp; ₱1,000.00 Arrears Cap:</strong> Because Buyers send 100% of Merchandise + Shipping directly to the Seller's personal GCash on online-paid orders, Storkia automatically offsets the owed <strong>10% Admin Commission + 100% Shipping Fee</strong> against the Seller's E-Wallet upon order completion. If a Seller's platform commission arrears reach <strong>₱1,000.00</strong>, GCash online checkout is automatically disabled for that store until the Seller remits the arrears via the Seller E-Wallet portal.</li>
                        <li><strong>Payout Withdrawals:</strong> Realized earnings move from Pending Escrow to Available E-Wallet Balance upon Buyer confirmation (or 72-hour auto-completion). Sellers may request a payout withdrawal to GCash or Bank once their Available Balance is at least <strong>₱100.00</strong> (one pending payout request at a time).</li>
                        <li><strong>RTS Receipt &amp; Damaged/Lost Claims:</strong> When an abandoned or undelivered parcel returns to the Seller (`returned_to_seller`), the Seller must confirm physical receipt to restore inventory stock, or file an RTS Damaged/Lost Reimbursement Claim with photo evidence for Admin review.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================= -->
    <!-- PART III: LOGISTICS HUB, COURIER & RIDER FLEET TERMS              -->
    <!-- ================================================================= -->
    <div x-show="activeRoleTab === 'all' || activeRoleTab === 'logistics'"
         x-transition.opacity
         class="space-y-6">

        <div class="p-6 sm:p-8 rounded-3xl bg-surface border border-border-subtle shadow-xs space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-4 border-b border-border-subtle">
                <div>
                    <span class="px-2.5 py-0.5 rounded-full bg-primary/10 text-primary text-[10px] font-extrabold uppercase tracking-wider">
                        Part III &bull; Logistics &amp; Fleet Agreement
                    </span>
                    <h2 class="text-xl sm:text-2xl font-serif font-bold text-primary-dark mt-1">
                        Logistics Sorting Hubs, First-Mile Couriers &amp; Last-Mile Riders Terms
                    </h2>
                </div>
                <span class="text-xs font-bold text-text-muted">Applies to Hubs, Couriers &amp; Riders</span>
            </div>

            <div class="space-y-5 text-xs sm:text-sm leading-relaxed text-text-main">
                <div>
                    <h3 class="font-bold text-primary-dark text-sm sm:text-base mb-1.5">
                        3.1 Main Hubs, Branch Hubs &amp; Internal `@storkia.shop` Credentials
                    </h3>
                    <p class="text-text-muted">
                        Logistics Sorting Centers must be approved by Storkia Admin with a verified facility address, Government ID, and Business/Mayor's Permit. Approved <strong>Main Logistics Accounts</strong> may create Municipality <strong>Branch Hub Accounts</strong>, <strong>First-Mile Couriers</strong>, and <strong>Last-Mile Delivery Riders</strong> using official internal <code>@storkia.shop</code> login emails. Branch Hub accounts are strictly scoped to shipments and employees assigned to their designated municipality hub.
                    </p>
                </div>

                <div>
                    <h3 class="font-bold text-primary-dark text-sm sm:text-base mb-1.5">
                        3.2 Fleet Licensing, Vehicle Capacity &amp; Mandatory Chain-of-Custody Verification
                    </h3>
                    <ul class="list-disc list-inside space-y-1.5 text-text-muted">
                        <li><strong>First-Mile Couriers:</strong> Must hold a valid <strong>Professional Driver's License</strong> with appropriate LTO DL restriction codes (`B`/`B2` for Vans 100–1,000 kg; `C` for 4-Wheeler/6-Wheeler Trucks up to 4,000–10,000 kg), valid Vehicle OR/CR, and NBI Clearance.</li>
                        <li><strong>Last-Mile Riders:</strong> Must hold a valid Driver's License and Vehicle OR/CR and are assigned to specific Barangay coverages within their hub's municipality.</li>
                        <li><strong>Mandatory 2-Step QR / Reference Verification:</strong> No parcel may advance in status without explicit QR token (`STORK-QR-...`) or Package Reference (`PKG-...`) lookup verification and manifest inspection at every handover stage: (1) Courier Pickup from Seller, (2) Sorting Hub Physical Arrival / Seller Self Drop-Off, (3) Rider Hub Collection, and (4) Doorstep Delivery Completion.</li>
                    </ul>
                </div>

                <div>
                    <h3 class="font-bold text-primary-dark text-sm sm:text-base mb-1.5">
                        3.3 2-Hour Dispatch SLA, Doorstep Proof of Delivery &amp; COD Remittance
                    </h3>
                    <ul class="list-disc list-inside space-y-1.5 text-text-muted">
                        <li><strong>2-Hour Assignment Acceptance SLA:</strong> Couriers and Riders must accept and initiate assigned pickup/delivery dispatches within <strong>two (2) hours</strong>. Unaccepted overdue tasks are automatically unassigned, and the employee receives an SLA strike. Three (3) SLA strikes result in automatic account suspension, returning any uncollected assignments to the hub's unassigned queue.</li>
                        <li><strong>Mandatory Proof of Delivery (POD) &amp; Exact COD Collection:</strong> Riders must capture a clear doorstep Proof of Delivery photo, record the recipient's name, and collect the <strong>exact COD amount</strong> shown on the manifest before marking an order <em>Delivered</em>.</li>
                        <li><strong>COD Cash Remittance &amp; Shortfall Audits:</strong> Riders must remit collected COD cash to their Logistics Hub for verification. Any remittance below the expected COD amount is automatically flagged in the Short COD Remittance Reconciliation audit log.</li>
                    </ul>
                </div>

                <div>
                    <h3 class="font-bold text-primary-dark text-sm sm:text-base mb-1.5">
                        3.4 Shipping Fee Split (90% Hub / Distance-Scaled Fleet Pay) &amp; Audit Trail
                    </h3>
                    <ul class="list-disc list-inside space-y-1.5 text-text-muted">
                        <li><strong>Logistics Hub Share (90% of Original Shipping Fee):</strong> Logistics Hubs earn <strong>10% Logistics Commission + 80% Operational Revenue (90% total)</strong> calculated on the full Original Shipping Fee (protected even when Free Shipping vouchers are used).</li>
                        <li><strong>Courier &amp; Rider Distance-Scaled Earnings:</strong> First-Mile Couriers earn a base pickup pay of <strong>₱30.00</strong> and Last-Mile Riders earn a base delivery pay of <strong>₱50.00</strong> (or 10% shipping share, whichever is higher), plus distance tier bonuses (+₱0 Same Barangay, +₱15 Same Municipality, +₱35 Same Province, +₱60 Inter-Provincial) and +₱5.00 per excess kg over 1 kg on successful completions. Failed attempts pay ₱0.00.</li>
                        <li><strong>Immutable Platform Audit Logs:</strong> Every manual shipment status override, Lost/Damaged parcel exception (which requires a mandatory photo for damaged claims), shipping zone rate modification, and employee suspension requires a 10–300 character reason and is permanently recorded with actor identity, IP address, user-agent, and before/after state in Storkia's immutable Security Audit Trail.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================= -->
    <!-- PART IV: DATA PRIVACY, GOVERNING LAW & LIABILITY                  -->
    <!-- ================================================================= -->
    <div class="p-6 sm:p-8 rounded-3xl bg-surface-subtle border border-border-subtle space-y-3 text-xs sm:text-sm leading-relaxed">
        <h3 class="font-bold text-primary-dark text-sm sm:text-base">
            4. Data Privacy, Intellectual Property &amp; Governing Law (Republic of the Philippines)
        </h3>
        <p class="text-text-muted">
            All personal data, government identification documents, business permits, vehicle records, payment screenshots, and delivery proofs uploaded to Storkia are processed strictly for identity verification, order fulfillment, escrow settlement, fraud prevention, and legal compliance in accordance with the <strong>Philippine Data Privacy Act of 2012 (Republic Act No. 10173)</strong>, the <strong>Internet Transactions Act of 2023 (Republic Act No. 11967)</strong>, and the <strong>Consumer Act of the Philippines (Republic Act No. 7394)</strong>. Any disputes not resolved through Storkia's internal dispute and escalation mechanisms shall be governed by the laws of the Republic of the Philippines.
        </p>
    </div>
</div>