@props([
    'config',
    'height' => 'calc(100vh - 140px)',
    'framed' => true,
])

{{--
    Shared chat window used by every chat in Storkia (buyer, seller, logistics,
    Help Center for members and the admin Help Center inbox).

    This file is markup only. All behaviour lives in resources/js/chat/chat-window.js
    (registered as Alpine.data('chatWindow') in resources/js/app.js).

    Usage:
        <x-chat.window :config="$chatConfig" />
        <x-chat.window :config="$chatConfig" height="calc(100vh - 220px)" :framed="false" />
--}}
<div x-data="chatWindow(@js($config))"
     x-on:keydown.escape.window="lightboxUrl = ''"
     {{ $attributes->class([
         'w-full flex bg-white overflow-hidden',
         'rounded-3xl border border-border-subtle shadow-xs' => $framed,
     ]) }}
     style="height: {{ $height }};">

    {{-- ============================================================ --}}
    {{-- Contact list                                                  --}}
    {{-- ============================================================ --}}
    <aside class="w-full md:max-w-[350px] md:w-[350px] border-r border-gray-200 flex-col bg-white h-full shrink-0 z-20"
           :class="single ? 'hidden' : (mobileChatOpen ? 'hidden md:flex' : 'flex')">

        <div class="p-4 sm:p-5 border-b border-gray-100 bg-white/95 backdrop-blur-sm shrink-0 space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-xl font-extrabold text-gray-900 tracking-tight truncate" x-text="labels.title"></h2>
                <div class="flex items-center gap-2 shrink-0">
                    <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider"
                          :class="echoConnected ? 'text-green-600' : 'text-gray-400'"
                          :title="echoConnected ? 'Live updates are on' : 'Checking for new messages every few seconds'">
                        <span class="w-1.5 h-1.5 rounded-full" :class="echoConnected ? 'bg-green-500' : 'bg-gray-300'"></span>
                        <span x-text="echoConnected ? 'Live' : 'Auto'"></span>
                    </span>
                    <span class="px-2.5 py-1 rounded-full bg-primary/10 text-primary text-xs font-bold"
                          x-text="contacts.length + ' ' + labels.countNoun"></span>
                </div>
            </div>

            <input type="text"
                   x-model="contactSearch"
                   :placeholder="labels.search"
                   class="w-full px-3.5 py-2 rounded-xl bg-gray-100 focus:bg-white border border-transparent focus:border-primary text-xs outline-none">

            {{-- Group tabs (All / Customers / Logistics ...) --}}
            <div x-show="hasGroups" x-cloak class="flex items-center gap-1.5 overflow-x-auto [&::-webkit-scrollbar]:hidden">
                <template x-for="group in cfg.groups" :key="group.key">
                    <button type="button"
                            @click="activeGroup = group.key"
                            :class="activeGroup === group.key ? 'bg-primary text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            class="px-3 py-1 rounded-full text-[11px] font-bold whitespace-nowrap transition-colors cursor-pointer flex items-center gap-1.5">
                        <span x-text="group.label"></span>
                        <template x-if="groupUnread(group.key) > 0">
                            <span class="min-w-[16px] h-4 px-1 rounded-full bg-red-500 text-white text-[9px] font-bold flex items-center justify-center"
                                  x-text="groupUnread(group.key)"></span>
                        </template>
                    </button>
                </template>
            </div>

            {{-- Status chips (admin Help Center only) --}}
            <div x-show="hasStatusFilters" x-cloak class="flex items-center gap-1.5">
                <template x-for="status in cfg.statusFilters" :key="status.key">
                    <button type="button"
                            @click="statusFilter = status.key"
                            :class="statusFilter === status.key ? 'border-primary text-primary bg-primary/5' : 'border-gray-200 text-gray-500 hover:bg-gray-50'"
                            class="px-3 py-1 rounded-full border text-[11px] font-bold transition-colors cursor-pointer"
                            x-text="status.label"></button>
                </template>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:bg-gray-200 [&::-webkit-scrollbar-thumb]:rounded-full">
            <template x-for="contact in filteredContacts" :key="contact.id">
                <button type="button"
                        @click="selectContact(contact)"
                        :class="isActive(contact) ? 'bg-primary/5' : 'hover:bg-gray-50'"
                        class="w-full flex items-center px-4 sm:px-5 py-3.5 border-b border-gray-50 text-left transition-colors focus:outline-none relative cursor-pointer">

                    <div class="absolute left-0 top-0 bottom-0 w-1 bg-primary transition-opacity"
                         :class="isActive(contact) ? 'opacity-100' : 'opacity-0'"></div>

                    <div class="relative shrink-0">
                        <template x-if="contact.logo_url">
                            <img :src="contact.logo_url" alt="" class="w-11 h-11 rounded-full object-cover border border-gray-100">
                        </template>
                        <template x-if="!contact.logo_url">
                            <div class="w-11 h-11 rounded-full flex items-center justify-center font-bold text-base transition-colors uppercase"
                                 :class="isActive(contact) ? 'bg-primary text-white' : 'bg-primary/10 text-primary'"
                                 x-text="initial(contact)"></div>
                        </template>
                        <span class="absolute bottom-0 right-0 w-3 h-3 border-2 border-white rounded-full"
                              :class="contact.online_status === 'online' ? 'bg-green-500' : 'bg-gray-300'"></span>
                    </div>

                    <div class="ml-3.5 overflow-hidden flex-1">
                        <div class="flex justify-between items-baseline mb-0.5 gap-2">
                            <p class="text-sm truncate transition-colors"
                               :class="contact.unread_count > 0 ? 'text-black font-extrabold' : 'text-gray-900 font-bold'"
                               x-text="contact.display_name"></p>
                            <template x-if="contact.latest_message_at">
                                <span class="text-[10px] font-medium shrink-0"
                                      :class="contact.unread_count > 0 ? 'text-primary font-bold' : 'text-gray-400'"
                                      x-text="formatTimeAgo(contact.latest_message_at)"></span>
                            </template>
                        </div>

                        <div class="flex items-center gap-1.5 mb-0.5">
                            <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-400" x-text="contact.role_label"></span>
                            <template x-if="contact.is_on_vacation">
                                <span class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-px rounded bg-amber-100 text-amber-700">On vacation</span>
                            </template>
                            <template x-if="isAdminMode && contact.status === 'resolved'">
                                <span class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-px rounded bg-green-100 text-green-700">Resolved</span>
                            </template>
                        </div>

                        <div class="flex justify-between items-center gap-2">
                            <p class="text-xs text-gray-500 truncate font-medium"
                               x-text="contact.latest_message_preview || 'No messages yet'"></p>
                            <template x-if="contact.unread_count > 0">
                                <span class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shrink-0"
                                      x-text="contact.unread_count"></span>
                            </template>
                        </div>
                    </div>
                </button>
            </template>

            <div x-show="filteredContacts.length === 0" x-cloak class="flex flex-col items-center justify-center h-40 px-6 text-center text-gray-400">
                <p class="text-sm font-medium" x-text="labels.emptyList"></p>
            </div>
        </div>
    </aside>

    {{-- ============================================================ --}}
    {{-- Conversation                                                  --}}
    {{-- ============================================================ --}}
    <section class="flex-1 flex-col bg-[#F7F7F9] h-full relative md:border-l border-gray-100 min-w-0"
             :class="(!single && !mobileChatOpen) ? 'hidden md:flex' : 'flex'">

        {{-- Header --}}
        <template x-if="activeContact">
            <header class="px-4 sm:px-6 py-3.5 bg-white/95 backdrop-blur-md border-b border-gray-200 flex items-center justify-between gap-3 z-10 shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <button type="button" x-show="!single" @click="backToList()"
                            class="md:hidden -ml-1 w-8 h-8 rounded-full text-gray-500 hover:bg-gray-100 flex items-center justify-center cursor-pointer"
                            aria-label="Back to conversations">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>

                    <template x-if="activeContact.logo_url">
                        <img :src="activeContact.logo_url" alt="" class="w-10 h-10 rounded-full object-cover border border-gray-100 shrink-0">
                    </template>
                    <template x-if="!activeContact.logo_url">
                        <div class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center font-bold text-sm shadow-sm uppercase shrink-0"
                             x-text="initial(activeContact)"></div>
                    </template>

                    <div class="min-w-0">
                        <p class="text-base font-bold text-gray-900 leading-tight truncate" x-text="activeContact.display_name"></p>

                        <p x-show="mode === 'direct'" class="text-xs font-medium flex items-center gap-1.5 mt-0.5 truncate"
                           :class="activeContact.online_status === 'online' ? 'text-green-600' : 'text-gray-500'">
                            <span class="w-2 h-2 rounded-full inline-block shrink-0"
                                  :class="activeContact.online_status === 'online' ? 'bg-green-500' : 'bg-gray-400'"></span>
                            <span class="truncate"
                                  x-text="activeContact.online_status === 'online' ? 'Online now' : (activeContact.last_seen_label || 'Offline')"></span>
                        </p>

                        <p x-show="isAdminMode" class="text-xs font-medium text-gray-500 mt-0.5 truncate"
                           x-text="[activeContact.role_label, activeContact.email].filter(Boolean).join(' · ')"></p>

                        <p x-show="isSupportUser" class="text-xs font-medium text-gray-500 mt-0.5 truncate"
                           x-text="labels.defaultSubtitle"></p>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <span x-show="isAdminMode" x-cloak
                          class="hidden sm:inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold"
                          :class="isResolved ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'"
                          x-text="isResolved ? 'Resolved' : 'Open'"></span>

                    <button type="button" x-show="canResolve" x-cloak
                            @click="toggleStatus()"
                            :disabled="statusBusy"
                            class="px-3.5 py-1.5 rounded-full text-xs font-bold border transition-colors disabled:opacity-50 cursor-pointer"
                            :class="isResolved ? 'border-gray-300 text-gray-700 hover:bg-gray-50' : 'border-green-600 text-green-700 hover:bg-green-50'"
                            x-text="statusBusy ? 'Saving...' : (isResolved ? 'Reopen' : 'Mark resolved')"></button>
                </div>
            </header>
        </template>

        {{-- Messages --}}
        <div x-ref="messagesBox"
             class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-5 [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:bg-gray-300 [&::-webkit-scrollbar-thumb]:rounded-full">

            <div x-show="!activeContact" class="h-full flex flex-col items-center justify-center text-center text-gray-400 px-6">
                <p class="text-base font-semibold text-gray-600" x-text="labels.emptyTitle"></p>
                <p class="text-sm font-medium mt-1 max-w-sm" x-text="labels.emptyHint"></p>
            </div>

            <div x-show="activeContact && loadingMessages && messages.length === 0" x-cloak
                 class="h-full flex items-center justify-center text-sm font-medium text-gray-400">
                Loading messages...
            </div>

            <div x-show="activeContact && !loadingMessages && messages.length === 0" x-cloak
                 class="h-full flex flex-col items-center justify-center text-center text-gray-400 px-6">
                <p class="text-base font-semibold text-gray-600" x-text="labels.emptyTitle"></p>
                <p class="text-sm font-medium mt-1 max-w-sm" x-text="labels.emptyHint"></p>
            </div>

            <template x-for="msg in messages" :key="msg.id">
                <div class="flex w-full" :class="isMine(msg) ? 'justify-end' : 'justify-start'">
                    <div class="flex flex-col max-w-[85%] md:max-w-[68%] space-y-1.5" :class="isMine(msg) ? 'items-end' : 'items-start'">

                        <template x-if="authorLabel(msg)">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-primary px-1" x-text="authorLabel(msg)"></span>
                        </template>

                        {{-- Product card --}}
                        <template x-if="msg.product_card">
                            <a :href="msg.product_card.url || null"
                               target="_blank" rel="noopener"
                               class="flex items-center gap-3 p-3 rounded-2xl bg-white border border-primary/30 shadow-xs hover:border-primary transition-all max-w-xs w-full text-left">
                                <template x-if="msg.product_card.image_url">
                                    <img :src="msg.product_card.image_url" class="w-14 h-14 rounded-xl object-cover border border-gray-100 shrink-0" alt="Product">
                                </template>
                                <div class="min-w-0 flex-1">
                                    <span class="text-[9px] font-extrabold uppercase tracking-wider text-primary block">Product Inquiry</span>
                                    <p class="text-xs font-bold text-gray-900 truncate" x-text="msg.product_card.name"></p>
                                    <div class="flex items-baseline gap-1.5 mt-0.5">
                                        <span class="text-xs font-extrabold text-primary" x-text="formatPrice(msg.product_card.effective_price)"></span>
                                        <span class="text-[10px] text-gray-400" x-text="msg.product_card.stock_quantity + ' in stock'"></span>
                                    </div>
                                </div>
                            </a>
                        </template>

                        {{-- Shipment card --}}
                        <template x-if="msg.shipment_card">
                            <a :href="msg.shipment_card.url || null"
                               :target="msg.shipment_card.url ? '_blank' : null" rel="noopener"
                               class="flex items-center gap-3 p-3 rounded-2xl bg-white border border-primary/30 shadow-xs hover:border-primary transition-all max-w-xs w-full text-left">
                                <div class="w-12 h-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <span class="text-[9px] font-extrabold uppercase tracking-wider text-primary block">Shipment Inquiry</span>
                                    <p class="text-xs font-bold text-gray-900 truncate" x-text="msg.shipment_card.tracking_number"></p>
                                    <div class="flex items-baseline gap-1.5 mt-0.5 flex-wrap">
                                        <span class="text-[10px] font-bold text-gray-600" x-text="msg.shipment_card.status_label"></span>
                                        <template x-if="msg.shipment_card.destination">
                                            <span class="text-[10px] text-gray-400 truncate" x-text="msg.shipment_card.destination"></span>
                                        </template>
                                        <template x-if="msg.shipment_card.cod_amount > 0">
                                            <span class="text-[10px] font-bold text-primary" x-text="'COD ' + formatPrice(msg.shipment_card.cod_amount)"></span>
                                        </template>
                                    </div>
                                </div>
                            </a>
                        </template>

                        {{-- Photo attachment --}}
                        <template x-if="msg.attachment_url">
                            <div x-data="{ failed: false }"
                                 class="rounded-2xl overflow-hidden border border-gray-200 bg-white p-1 shadow-xs max-w-xs">
                                <img x-show="!failed"
                                     :src="msg.attachment_url"
                                     @click="lightboxUrl = msg.attachment_url"
                                     x-on:error="failed = true"
                                     class="max-h-56 w-auto rounded-xl object-contain cursor-zoom-in"
                                     alt="">
                                <div x-show="failed" x-cloak class="px-3 py-2 text-xs font-medium text-gray-400">Photo unavailable</div>
                            </div>
                        </template>

                        {{-- Text bubble (placeholder texts are already blanked server-side) --}}
                        <template x-if="msg.message">
                            <div class="px-4 py-2.5 shadow-xs text-sm leading-relaxed break-words"
                                 :class="isMine(msg) ? 'bg-primary text-white rounded-2xl rounded-tr-sm' : 'bg-white text-gray-800 rounded-2xl rounded-tl-sm border border-gray-100'">
                                <p x-text="msg.message" class="whitespace-pre-wrap"></p>
                            </div>
                        </template>

                        <span class="text-[11px] text-gray-400 font-medium px-1 flex items-center gap-1.5"
                              :class="isMine(msg) ? 'flex-row-reverse' : ''"
                              :title="fullDate(msg.created_at)">
                            <span x-text="formatTimeAgo(msg.created_at)"></span>
                            <template x-if="isMine(msg)">
                                <span :class="msg.is_read ? 'text-primary font-bold' : ''" x-text="msg.is_read ? 'Seen' : 'Sent'"></span>
                            </template>
                        </span>
                    </div>
                </div>
            </template>
        </div>

        {{-- Composer --}}
        <template x-if="activeContact">
            <div class="p-3.5 sm:p-4 bg-white border-t border-gray-200 shrink-0 space-y-2.5">

                {{-- Resolved notice for members --}}
                <div x-show="isSupportUser && isResolved" x-cloak
                     class="max-w-5xl mx-auto px-3.5 py-2 rounded-xl text-xs font-medium bg-green-50 text-green-800 border border-green-200">
                    This conversation was marked as resolved. Send a new message to reopen it.
                </div>

                {{-- Quick replies --}}
                <div x-show="quickReplies.length > 0" x-cloak
                     class="max-w-5xl mx-auto flex items-center gap-1.5 overflow-x-auto pb-1 [&::-webkit-scrollbar]:hidden text-[11px]">
                    <span class="text-gray-400 font-bold shrink-0 mr-1">Quick Reply:</span>
                    <template x-for="chip in quickReplies" :key="chip">
                        <button type="button"
                                @click="useQuickReply(chip)"
                                class="px-3 py-1 rounded-full bg-gray-100 hover:bg-primary/10 text-gray-700 hover:text-primary font-semibold whitespace-nowrap transition-colors cursor-pointer"
                                x-text="chip"></button>
                    </template>
                </div>

                {{-- Pending product / shipment card --}}
                <template x-if="draft">
                    <div class="max-w-5xl mx-auto flex items-center gap-3 p-3 rounded-2xl bg-primary/5 border border-primary/30">
                        <template x-if="draft.type === 'product' && draft.data.image_url">
                            <img :src="draft.data.image_url" class="w-12 h-12 rounded-xl object-cover border border-gray-100 shrink-0" alt="Product">
                        </template>
                        <template x-if="draft.type === 'shipment'">
                            <div class="w-12 h-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            </div>
                        </template>
                        <div class="min-w-0 flex-1">
                            <span class="text-[9px] font-extrabold uppercase tracking-wider text-primary block"
                                  x-text="draft.type === 'product' ? 'Attached to your next message: product' : 'Attached to your next message: shipment'"></span>
                            <p class="text-xs font-bold text-gray-900 truncate"
                               x-text="draft.type === 'product' ? draft.data.name : draft.data.tracking_number"></p>
                            <p class="text-[11px] text-gray-500 truncate"
                               x-text="draft.type === 'product'
                                    ? (formatPrice(draft.data.effective_price) + ' · ' + draft.data.stock_quantity + ' in stock')
                                    : [draft.data.status_label, draft.data.destination].filter(Boolean).join(' · ')"></p>
                        </div>
                        <button type="button" @click="clearDraft()" title="Remove"
                                class="w-7 h-7 rounded-full text-gray-400 hover:bg-white hover:text-gray-700 flex items-center justify-center text-lg font-bold cursor-pointer shrink-0">&times;</button>
                    </div>
                </template>

                {{-- Shipment picker --}}
                <template x-if="shipmentPickerOpen">
                    <div class="max-w-5xl mx-auto rounded-2xl border border-gray-200 bg-white shadow-xs max-h-56 overflow-y-auto">
                        <p x-show="loadingShipments" class="px-4 py-3 text-xs font-medium text-gray-400">Loading shared shipments...</p>
                        <p x-show="!loadingShipments && shipmentOptions.length === 0" x-cloak class="px-4 py-3 text-xs font-medium text-gray-400">
                            No shared shipments with this contact yet.
                        </p>
                        <template x-for="card in shipmentOptions" :key="card.id">
                            <button type="button" @click="pickShipment(card)"
                                    class="w-full flex items-center justify-between gap-3 px-4 py-2.5 border-b border-gray-50 last:border-b-0 hover:bg-primary/5 text-left cursor-pointer">
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-gray-900 truncate" x-text="card.tracking_number"></p>
                                    <p class="text-[11px] text-gray-500 truncate"
                                       x-text="[card.status_label, card.destination].filter(Boolean).join(' · ')"></p>
                                </div>
                                <template x-if="card.cod_amount > 0">
                                    <span class="text-[11px] font-bold text-primary shrink-0" x-text="'COD ' + formatPrice(card.cod_amount)"></span>
                                </template>
                            </button>
                        </template>
                    </div>
                </template>

                {{-- Attachment / error banner --}}
                <template x-if="selectedFileName || errorBanner">
                    <div class="max-w-5xl mx-auto flex items-center justify-between px-3.5 py-2 rounded-xl text-xs"
                         :class="errorBanner ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-blue-50 text-blue-800 border border-blue-200'">
                        <span class="font-bold truncate" x-text="errorBanner ? errorBanner : ('Attached Image: ' + selectedFileName)"></span>
                        <button type="button" @click="clearAttachment(); errorBanner = ''" class="ml-2 font-extrabold cursor-pointer">&times;</button>
                    </div>
                </template>

                <div class="flex gap-2.5 items-end max-w-5xl mx-auto">
                    <input type="file" x-ref="fileInput" accept="image/jpeg,image/png,image/webp" class="hidden" @change="onSelectAttachment($event)">

                    <button type="button"
                            @click="$refs.fileInput.click()"
                            title="Attach JPG, PNG or WEBP image (max 5MB)"
                            class="w-11 h-11 rounded-full text-gray-500 hover:bg-gray-100 hover:text-primary flex items-center justify-center transition-colors shrink-0 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </button>

                    <button type="button" x-show="canAttachShipment" x-cloak
                            @click="toggleShipmentPicker()"
                            title="Attach a shared shipment"
                            :class="shipmentPickerOpen ? 'bg-primary/10 text-primary' : 'text-gray-500 hover:bg-gray-100 hover:text-primary'"
                            class="w-11 h-11 rounded-full flex items-center justify-center transition-colors shrink-0 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    </button>

                    <div class="flex-1 relative">
                        <textarea x-model="newMessage"
                                  x-ref="textarea"
                                  rows="1"
                                  maxlength="1000"
                                  :placeholder="labels.placeholder"
                                  class="w-full bg-gray-100 border border-transparent focus:bg-white focus:border-primary rounded-2xl py-3 px-4 pr-14 outline-none text-gray-800 text-sm transition-all resize-none overflow-hidden block"
                                  @keydown.enter="if (!$event.shiftKey && !$event.isComposing) { $event.preventDefault(); sendMessage(); }"
                                  @input="resizeTextarea($event)"
                                  style="min-height: 46px; max-height: 120px;"></textarea>
                        <span class="absolute right-3 bottom-1.5 text-[10px] text-gray-400" x-text="(newMessage || '').length + '/1000'"></span>
                    </div>

                    <button type="button"
                            @click="sendMessage()"
                            :disabled="!canSend"
                            title="Send"
                            class="w-11 h-11 rounded-full bg-primary text-white flex items-center justify-center hover:bg-primary-dark disabled:opacity-50 transition-all shrink-0 cursor-pointer">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 ml-0.5">
                            <path d="M3.478 2.404a.75.75 0 0 0-.926.941l2.432 7.905H13.5a.75.75 0 0 1 0 1.5H4.984l-2.432 7.905a.75.75 0 0 0 .926.94 60.519 60.519 0 0 0 18.445-8.986.75.75 0 0 0 0-1.218A60.517 60.517 0 0 0 3.478 2.404Z"/>
                        </svg>
                    </button>
                </div>
            </div>
        </template>
    </section>

    {{-- Image lightbox --}}
    <div x-show="lightboxUrl" x-cloak @click="lightboxUrl = ''"
         class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4 cursor-zoom-out">
        <img :src="lightboxUrl" class="max-w-full max-h-[85vh] rounded-2xl shadow-2xl object-contain" alt="Preview">
    </div>
</div>