@extends('layouts.seller')

@section('content')
<div class="w-full flex bg-white rounded-3xl border border-border-subtle shadow-xs overflow-hidden"
     style="height: calc(100vh - 140px);"
     x-data='sellerChatComponent({{ auth()->id() }}, @json($contacts))'>

    <!-- Sidebar Contacts -->
    <div class="w-full max-w-[300px] md:max-w-[350px] border-r border-gray-200 flex flex-col bg-white h-full shrink-0 z-20">
        <div class="p-4 sm:p-5 border-b border-gray-100 bg-white/95 backdrop-blur-sm sticky top-0 shrink-0 space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-extrabold text-gray-900 tracking-tight">Customer Chat</h2>
                <span class="px-2.5 py-1 rounded-full bg-primary/10 text-primary text-xs font-bold" x-text="contacts.length + ' Buyers'"></span>
            </div>
            <input type="text"
                   x-model="contactSearch"
                   placeholder="Search customer..."
                   class="w-full px-3.5 py-2 rounded-xl bg-gray-100 focus:bg-white border border-transparent focus:border-primary text-xs outline-none">
        </div>

        <div class="flex-1 overflow-y-auto [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:bg-gray-200 [&::-webkit-scrollbar-thumb]:rounded-full">
            <template x-for="contact in sortedContacts" :key="contact.id">
                <button
                    @click="selectContact(contact)"
                    :class="activeContactId === contact.id ? 'bg-primary/5' : 'hover:bg-gray-50'"
                    class="w-full flex items-center px-4 sm:px-5 py-3.5 border-b border-gray-50 text-left transition-colors focus:outline-none group relative cursor-pointer">

                    <div class="absolute left-0 top-0 bottom-0 w-1 bg-primary transition-opacity" :class="activeContactId === contact.id ? 'opacity-100' : 'opacity-0'"></div>

                    <div class="relative shrink-0">
                        <div class="w-11 h-11 rounded-full flex items-center justify-center font-bold text-base transition-colors uppercase"
                             :class="activeContactId === contact.id ? 'bg-primary text-white' : 'bg-primary/10 text-primary'"
                             x-text="(contact.first_name || 'B').charAt(0)">
                        </div>
                        <span class="absolute bottom-0 right-0 w-3 h-3 border-2 border-white rounded-full"
                              :class="contact.online_status === 'online' ? 'bg-green-500' : 'bg-gray-300'"></span>
                    </div>

                    <div class="ml-3.5 overflow-hidden flex-1">
                        <div class="flex justify-between items-baseline mb-0.5">
                            <p class="text-sm truncate transition-colors"
                               :class="contact.unread_count > 0 ? 'text-black font-extrabold' : 'text-gray-900 font-bold'"
                               x-text="contact.first_name + ' ' + contact.last_name"></p>
                            <template x-if="contact.latest_message_at">
                                <span class="text-[10px] font-medium shrink-0 ml-2"
                                      :class="contact.unread_count > 0 ? 'text-primary font-bold' : 'text-gray-400'"
                                      x-text="formatTimeAgo(contact.latest_message_at)"></span>
                            </template>
                        </div>
                        <div class="flex justify-between items-center gap-2">
                            <p class="text-xs text-gray-500 truncate font-medium" x-text="contact.latest_message_preview || 'Customer'"></p>
                            <template x-if="contact.unread_count > 0">
                                <span class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shrink-0" x-text="contact.unread_count"></span>
                            </template>
                        </div>
                    </div>
                </button>
            </template>

            <div x-show="sortedContacts.length === 0" class="flex flex-col items-center justify-center h-40 text-gray-400" x-cloak>
                <p class="text-sm font-medium">No customer messages yet</p>
            </div>
        </div>
    </div>

    <!-- Chat Area -->
    <div class="flex-1 flex flex-col bg-[#F7F7F9] h-full relative border-l border-gray-100 min-w-0">
        <!-- Header -->
        <div class="px-6 py-3.5 bg-white/95 backdrop-blur-md border-b border-gray-200 flex items-center justify-between z-10 shrink-0" x-show="activeContactId" x-cloak>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center font-bold text-sm shadow-sm uppercase">
                    <span x-text="(activeContactName || 'C').charAt(0)"></span>
                </div>
                <div>
                    <p class="text-base font-bold text-gray-900 leading-tight" x-text="activeContactName"></p>
                    <p class="text-xs font-medium flex items-center gap-1.5 mt-0.5"
                       :class="activeContactOnline === 'online' ? 'text-green-600' : 'text-gray-500'">
                        <span class="w-2 h-2 rounded-full inline-block"
                              :class="activeContactOnline === 'online' ? 'bg-green-500' : 'bg-gray-400'"></span>
                        <span x-text="activeContactOnline === 'online' ? 'Online now' : (activeContactLastSeen || 'Offline')"></span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Messages List -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-5 [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:bg-gray-300 [&::-webkit-scrollbar-thumb]:rounded-full" id="seller-messages-container">
            <div x-show="!activeContactId" class="h-full flex flex-col items-center justify-center text-gray-400">
                <p class="text-base font-semibold text-gray-600">Customer Messages</p>
                <p class="text-sm font-medium mt-1">Select a customer on the left to view product inquiries and reply.</p>
            </div>

            <template x-for="msg in messages" :key="msg.id">
                <div class="flex w-full" :class="msg.user_id == authId ? 'justify-end' : 'justify-start'">
                    <div class="flex flex-col max-w-[85%] md:max-w-[68%] space-y-1.5" :class="msg.user_id == authId ? 'items-end' : 'items-start'">

                        <!-- Interactive Product Card -->
                        <template x-if="msg.product_card">
                            <a :href="msg.product_card.url"
                               target="_blank"
                               class="flex items-center gap-3 p-3 rounded-2xl bg-white border border-primary/30 shadow-xs hover:border-primary transition-all max-w-xs w-full text-left">
                                <template x-if="msg.product_card.image_url">
                                    <img :src="msg.product_card.image_url" class="w-14 h-14 rounded-xl object-cover border border-gray-100 shrink-0" alt="Product">
                                </template>
                                <div class="min-w-0 flex-1">
                                    <span class="text-[9px] font-extrabold uppercase tracking-wider text-primary block">Customer Inquired About</span>
                                    <p class="text-xs font-bold text-gray-900 truncate" x-text="msg.product_card.name"></p>
                                    <div class="flex items-baseline gap-1.5 mt-0.5">
                                        <span class="text-xs font-extrabold text-primary" x-text="'₱' + Number(msg.product_card.effective_price).toLocaleString('en-US', {minimumFractionDigits: 2})"></span>
                                        <span class="text-[10px] text-gray-400" x-text="msg.product_card.stock_quantity + ' in stock'"></span>
                                    </div>
                                </div>
                            </a>
                        </template>

                        <!-- Photo Attachment -->
                        <template x-if="msg.attachment_url">
                            <div class="rounded-2xl overflow-hidden border border-gray-200 bg-white p-1 shadow-xs max-w-xs">
                                <img :src="msg.attachment_url"
                                     @click="lightboxUrl = msg.attachment_url"
                                     class="max-h-56 w-auto rounded-xl object-contain cursor-zoom-in"
                                     alt="Chat Attachment">
                            </div>
                        </template>

                        <!-- Text Bubble -->
                        <template x-if="msg.message">
                            <div class="px-4 py-2.5 shadow-xs text-sm leading-relaxed break-words"
                                 :class="msg.user_id == authId ? 'bg-primary text-white rounded-2xl rounded-tr-sm' : 'bg-white text-gray-800 rounded-2xl rounded-tl-sm border border-gray-100'">
                                <p x-text="msg.message" class="whitespace-pre-wrap"></p>
                            </div>
                        </template>

                        <span class="text-[11px] text-gray-400 font-medium px-1 flex items-center gap-1" :class="msg.user_id == authId ? 'flex-row-reverse' : ''">
                            <span x-text="formatTimeAgo(msg.created_at)"></span>
                        </span>
                    </div>
                </div>
            </template>
        </div>

        <!-- Quick Replies & Input Area -->
        <div class="p-3.5 sm:p-4 bg-white border-t border-gray-200 shrink-0 space-y-2.5" x-show="activeContactId" x-cloak>
            <div class="max-w-5xl mx-auto flex items-center gap-1.5 overflow-x-auto pb-1 [&::-webkit-scrollbar]:hidden text-[11px]">
                <span class="text-gray-400 font-bold shrink-0 mr-1">Quick Reply:</span>
                <template x-for="chip in ['Yes po, this item is available and ready to ship!', 'We pack and hand over orders within 24 hours.', 'Don\'t forget to claim our Store Voucher before checkout!']" :key="chip">
                    <button type="button"
                            @click="newMessage = chip; $nextTick(() => $refs.textarea.focus())"
                            class="px-3 py-1 rounded-full bg-gray-100 hover:bg-primary/10 text-gray-700 hover:text-primary font-semibold whitespace-nowrap transition-colors cursor-pointer">
                        <span x-text="chip"></span>
                    </button>
                </template>
            </div>

            <template x-if="selectedFileName || errorBanner">
                <div class="max-w-5xl mx-auto flex items-center justify-between px-3.5 py-2 rounded-xl text-xs"
                     :class="errorBanner ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-blue-50 text-blue-800 border border-blue-200'">
                    <span class="font-bold truncate" x-text="errorBanner ? errorBanner : ('Attached Image: ' + selectedFileName)"></span>
                    <button type="button" @click="clearAttachment(); errorBanner = ''" class="ml-2 font-extrabold cursor-pointer">&times;</button>
                </div>
            </template>

            <div class="flex gap-2.5 items-end max-w-5xl mx-auto">
                <input type="file" x-ref="fileInput" accept="image/jpeg,image/png" class="hidden" @change="onSelectAttachment($event)">
                <button type="button"
                        @click="$refs.fileInput.click()"
                        title="Attach JPG/PNG Image (Max 5MB)"
                        class="w-11 h-11 rounded-full text-gray-500 hover:bg-gray-100 hover:text-primary flex items-center justify-center transition-colors shrink-0 cursor-pointer">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </button>

                <div class="flex-1 relative">
                    <textarea x-model="newMessage"
                              rows="1"
                              maxlength="1000"
                              placeholder="Type your reply (max 1,000 chars)..."
                              class="w-full bg-gray-100 border border-transparent focus:bg-white focus:border-primary rounded-2xl py-3 px-4 outline-none text-gray-800 text-sm transition-all resize-none overflow-hidden block"
                              @keydown.enter.prevent="sendMessage()"
                              @input="resizeTextarea"
                              x-ref="textarea"
                              style="min-height: 46px; max-height: 120px;"></textarea>
                    <span class="absolute right-3 bottom-1.5 text-[10px] text-gray-400" x-text="(newMessage || '').length + '/1000'"></span>
                </div>

                <button type="button"
                        @click="sendMessage()"
                        :disabled="((!newMessage || !newMessage.trim()) && !selectedFile) || isSending"
                        class="w-11 h-11 rounded-full bg-primary text-white flex items-center justify-center hover:bg-primary-dark disabled:opacity-50 transition-all shrink-0 cursor-pointer">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 ml-0.5">
                        <path d="M3.478 2.404a.75.75 0 0 0-.926.941l2.432 7.905H13.5a.75.75 0 0 1 0 1.5H4.984l-2.432 7.905a.75.75 0 0 0 .926.94 60.519 60.519 0 0 0 18.445-8.986.75.75 0 0 0 0-1.218A60.517 60.517 0 0 0 3.478 2.404Z" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div x-show="lightboxUrl" x-cloak @click="lightboxUrl = ''" class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4 cursor-zoom-out">
        <img :src="lightboxUrl" class="max-w-full max-h-[85vh] rounded-2xl shadow-2xl object-contain" alt="Preview">
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('sellerChatComponent', (authId, initialContacts) => ({
            authId: authId,
            activeContactId: null,
            activeContactName: '',
            activeContactOnline: 'offline',
            activeContactLastSeen: '',
            contactSearch: '',
            contacts: initialContacts || [],
            messages: [],
            newMessage: '',
            selectedFile: null,
            selectedFileName: '',
            errorBanner: '',
            lightboxUrl: '',
            isSending: false,
            now: new Date(),

            get sortedContacts() {
                const q = (this.contactSearch || '').trim().toLowerCase();
                return this.contacts
                    .filter(c => {
                        if (!q) return true;
                        const name = ((c.first_name || '') + ' ' + (c.last_name || '')).toLowerCase();
                        return name.includes(q);
                    })
                    .sort((a, b) => {
                        let dateA = a.latest_message_at ? new Date(a.latest_message_at).getTime() : 0;
                        let dateB = b.latest_message_at ? new Date(b.latest_message_at).getTime() : 0;
                        return dateB - dateA;
                    });
            },

            init() {
                setInterval(() => { this.now = new Date(); }, 30000);
                setInterval(() => {
                    this.refreshContactsSilently();
                    if (this.activeContactId) this.fetchMessagesSilently();
                }, 3000);
            },

            selectContact(contact) {
                this.activeContactId = parseInt(contact.id);
                this.activeContactName = contact.first_name + ' ' + contact.last_name;
                this.activeContactOnline = contact.online_status || 'offline';
                this.activeContactLastSeen = contact.last_seen_label || 'Offline';
                contact.unread_count = 0;
                this.fetchMessages();
            },

            onSelectAttachment(event) {
                this.errorBanner = '';
                const file = event.target.files[0];
                if (!file) return;
                if (!['image/jpeg', 'image/png'].includes(file.type)) {
                    this.errorBanner = 'Only JPG and PNG images are allowed.';
                    event.target.value = '';
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    this.errorBanner = 'Attachment size cannot exceed 5 MB.';
                    event.target.value = '';
                    return;
                }
                this.selectedFile = file;
                this.selectedFileName = file.name;
            },

            clearAttachment() {
                this.selectedFile = null;
                this.selectedFileName = '';
                if (this.$refs.fileInput) this.$refs.fileInput.value = '';
            },

            fetchMessages() {
                fetch(`/seller/messages/fetch/${this.activeContactId}`, {
                    headers: { 'Accept': 'application/json' }
                })
                .then(res => res.json())
                .then(data => {
                    this.messages = data;
                    this.scrollToBottom();
                });
            },

            fetchMessagesSilently() {
                if (!this.activeContactId) return;
                fetch(`/seller/messages/fetch/${this.activeContactId}`, {
                    headers: { 'Accept': 'application/json' }
                })
                .then(res => res.json())
                .then(data => {
                    if (this.messages.length !== data.length) {
                        this.messages = data;
                        this.scrollToBottom();
                        this.markAsRead(this.activeContactId);
                    }
                });
            },

            refreshContactsSilently() {
                fetch(`/seller/messages/contacts`, {
                    headers: { 'Accept': 'application/json' }
                })
                .then(res => res.json())
                .then(data => {
                    this.contacts = data;
                });
            },

            markAsRead(contactId) {
                const tokenMeta = document.querySelector('meta[name="csrf-token"]');
                const csrfToken = tokenMeta ? tokenMeta.getAttribute('content') : '';
                fetch(`/seller/messages/${contactId}/read`, {
                    method: 'PATCH',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
                });
            },

            sendMessage() {
                this.errorBanner = '';
                const text = this.newMessage ? this.newMessage.trim() : '';
                if ((!text && !this.selectedFile) || !this.activeContactId || this.isSending) return;
                if (text.length > 1000) {
                    this.errorBanner = 'Chat message cannot exceed 1,000 characters.';
                    return;
                }

                this.isSending = true;
                const formData = new FormData();
                formData.append('receiver_id', this.activeContactId);
                if (text) formData.append('message', text);
                if (this.selectedFile) formData.append('attachment', this.selectedFile);

                const backupText = this.newMessage;
                this.newMessage = '';
                if (this.$refs.textarea) this.$refs.textarea.style.height = '46px';

                const tokenMeta = document.querySelector('meta[name="csrf-token"]');
                const csrfToken = tokenMeta ? tokenMeta.getAttribute('content') : '';

                fetch('/seller/messages', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: formData
                })
                .then(async response => {
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        const firstError = data.errors ? Object.values(data.errors)[0][0] : (data.message || data.error || 'Failed to send message.');
                        throw new Error(firstError);
                    }
                    return data;
                })
                .then(data => {
                    this.messages.push(data);
                    this.clearAttachment();
                    this.scrollToBottom();
                    let contact = this.contacts.find(c => c.id === this.activeContactId);
                    if (contact) contact.latest_message_at = data.created_at;
                })
                .catch(error => {
                    this.errorBanner = error.message;
                    this.newMessage = backupText;
                })
                .finally(() => {
                    this.isSending = false;
                });
            },

            formatTimeAgo(dateString) {
                if (!dateString) return '';
                const date = new Date(dateString);
                if (isNaN(date.getTime())) return dateString;
                const seconds = Math.floor((this.now - date) / 1000);
                if (seconds < 60) return 'Just now';
                const minutes = Math.floor(seconds / 60);
                if (minutes < 60) return `${minutes}m ago`;
                const hours = Math.floor(minutes / 60);
                if (hours < 24) return `${hours}h ago`;
                const days = Math.floor(hours / 24);
                if (days < 7) return `${days}d ago`;
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            },

            resizeTextarea(e) {
                e.target.style.height = '46px';
                e.target.style.height = Math.min(e.target.scrollHeight, 120) + 'px';
            },

            scrollToBottom() {
                setTimeout(() => {
                    const container = document.getElementById('seller-messages-container');
                    if (container) container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' });
                }, 50);
            }
        }));
    });
</script>
@endsection