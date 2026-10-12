@extends('layouts.app')

@section('content')
<div class="w-full flex bg-white border-t border-gray-200 overflow-hidden" style="height: calc(100vh - 135px);" x-data='chatComponent({{ auth()->id() }}, @json(request("contact", "")), @json($contacts))'>
    
    <!-- Sidebar Contacts -->
    <div class="w-full max-w-[320px] md:max-w-[380px] border-r border-gray-200 flex flex-col bg-white h-full shrink-0 z-20">
        <div class="p-5 border-b border-gray-100 bg-white/95 backdrop-blur-sm sticky top-0 shrink-0 flex items-center justify-between">
            <h2 class="text-xl font-extrabold text-gray-900 tracking-tight">Messages</h2>
            <div class="p-2 bg-gray-50 rounded-full cursor-pointer hover:bg-gray-100 transition">
                <svg class="w-5 h-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            </div>
        </div>
        
        <div class="flex-1 overflow-y-auto [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:bg-gray-200 [&::-webkit-scrollbar-thumb]:rounded-full hover:[&::-webkit-scrollbar-thumb]:bg-gray-300">
            <template x-for="contact in sortedContacts" :key="contact.id">
                <button 
                    :id="'contact-btn-' + contact.id"
                    @click="selectContact(contact.id, contact.first_name + ' ' + contact.last_name)"
                    :class="activeContactId === contact.id ? 'bg-primary/5' : 'hover:bg-gray-50'"
                    class="w-full flex items-center px-5 py-4 border-b border-gray-50 text-left transition-colors focus:outline-none group relative">
                    
                    <div class="absolute left-0 top-0 bottom-0 w-1 bg-primary transition-opacity" :class="activeContactId === contact.id ? 'opacity-100' : 'opacity-0'"></div>
                    
                    <div class="relative shrink-0">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center font-bold text-lg transition-colors uppercase"
                             :class="activeContactId === contact.id ? 'bg-primary text-white' : 'bg-primary-light/20 text-primary group-hover:bg-primary-light/30'"
                             x-text="contact.first_name.charAt(0)">
                        </div>
                        <div class="absolute bottom-0.5 right-0.5 w-3 h-3 bg-green-500 border-2 border-white rounded-full"></div>
                    </div>
                    
                    <div class="ml-4 overflow-hidden flex-1">
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
                        <div class="flex justify-between items-center">
                            <p class="text-xs text-gray-500 truncate font-medium">Official Store</p>
                            <template x-if="contact.unread_count > 0">
                                <span class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full" x-text="contact.unread_count"></span>
                            </template>
                        </div>
                    </div>
                </button>
            </template>
            <div x-show="contacts.length === 0" class="flex flex-col items-center justify-center h-40 text-gray-400" x-cloak>
                <svg class="w-10 h-10 mb-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                <p class="text-sm font-medium">No active conversations</p>
            </div>
        </div>
    </div>

    <!-- Chat Area -->
    <div class="flex-1 flex flex-col bg-[#F7F7F9] h-full relative border-l border-gray-100">
        <!-- Header -->
        <div class="px-6 py-4 bg-white/90 backdrop-blur-md border-b border-gray-200 flex items-center justify-between z-10 shrink-0" x-show="activeContactId" x-cloak>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center font-bold text-sm shadow-sm uppercase">
                    <span x-text="activeContactName.charAt(0)"></span>
                </div>
                <div>
                    <p class="text-base font-bold text-gray-900 leading-tight" x-text="activeContactName"></p>
                    <p class="text-xs text-green-600 font-medium flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span> Active now
                    </p>
                </div>
            </div>
        </div>

        <!-- Messages List -->
        <div class="flex-1 overflow-y-auto p-6 space-y-6 [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:bg-gray-300 [&::-webkit-scrollbar-thumb]:rounded-full hover:[&::-webkit-scrollbar-thumb]:bg-gray-400" id="messages-container">
            <div x-show="!activeContactId" class="h-full flex flex-col items-center justify-center text-gray-400">
                <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4 shadow-inner">
                    <svg class="w-12 h-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                </div>
                <p class="text-base font-semibold text-gray-600">Your Messages</p>
                <p class="text-sm font-medium mt-1">Select a store to start chatting.</p>
            </div>

            <!-- Chat Bubbles -->
            <template x-for="msg in messages" :key="msg.id">
                <div class="flex w-full" :class="msg.user_id == authId ? 'justify-end' : 'justify-start'">
                    <div class="flex flex-col max-w-[85%] md:max-w-[70%]" :class="msg.user_id == authId ? 'items-end' : 'items-start'">
                        <div class="px-5 py-3 shadow-sm text-[15px] leading-relaxed break-words"
                             :class="msg.user_id == authId ? 'bg-primary text-white rounded-2xl rounded-tr-sm' : 'bg-white text-gray-800 rounded-2xl rounded-tl-sm border border-gray-100'">
                            <p x-text="msg.message" class="whitespace-pre-wrap"></p>
                        </div>
                        <span class="text-[11px] text-gray-400 mt-1.5 font-medium px-1 flex items-center gap-1" :class="msg.user_id == authId ? 'flex-row-reverse' : ''">
                            <span x-text="formatTimeAgo(msg.created_at)"></span>
                            <template x-if="msg.user_id == authId">
                                <svg class="w-3 h-3 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            </template>
                        </span>
                    </div>
                </div>
            </template>
        </div>

        <!-- Input Area -->
        <div class="p-4 bg-white border-t border-gray-200 shrink-0" x-show="activeContactId" x-cloak>
            <div class="flex gap-3 items-end max-w-5xl mx-auto">
                <button type="button" class="w-12 h-12 rounded-full text-gray-400 hover:bg-gray-100 hover:text-primary flex items-center justify-center transition-colors shrink-0 focus:outline-none">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                </button>
                <div class="flex-1 relative">
                    <textarea x-model="newMessage" rows="1" placeholder="Type a message..." 
                           class="w-full bg-gray-100 border-transparent focus:bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 rounded-3xl py-3.5 px-6 outline-none text-gray-800 text-[15px] transition-all resize-none overflow-hidden block"
                           @keydown.enter.prevent="sendMessage"
                           @input="resizeTextarea"
                           x-ref="textarea"
                           style="min-height: 52px; max-height: 120px;"></textarea>
                </div>
                <button type="button" @click="sendMessage" :disabled="!newMessage || !newMessage.trim() || isSending" 
                        class="w-12 h-12 rounded-full bg-primary text-white flex items-center justify-center hover:bg-primary-dark hover:shadow-lg disabled:opacity-50 transition-all shrink-0 focus:outline-none cursor-pointer">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 ml-1">
                        <path d="M3.478 2.404a.75.75 0 0 0-.926.941l2.432 7.905H13.5a.75.75 0 0 1 0 1.5H4.984l-2.432 7.905a.75.75 0 0 0 .926.94 60.519 60.519 0 0 0 18.445-8.986.75.75 0 0 0 0-1.218A60.517 60.517 0 0 0 3.478 2.404Z" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('chatComponent', (authId, initialContactId, initialContacts) => ({
            authId: authId,
            activeContactId: null,
            activeContactName: '',
            contacts: initialContacts,
            messages: [],
            newMessage: '',
            isSending: false,
            now: new Date(),
            
            get sortedContacts() {
                return this.contacts.sort((a, b) => {
                    let dateA = a.latest_message_at ? new Date(a.latest_message_at).getTime() : 0;
                    let dateB = b.latest_message_at ? new Date(b.latest_message_at).getTime() : 0;
                    return dateB - dateA;
                });
            },

            init() {
                setInterval(() => { this.now = new Date(); }, 60000); 

                // Automatic initialization routing hook
                if (initialContactId && initialContactId.toString().trim() !== '') {
                    this.$nextTick(() => {
                        let parsedId = parseInt(initialContactId);
                        let found = this.contacts.find(c => c.id === parsedId);
                        if(found) {
                            this.selectContact(found.id, found.first_name + ' ' + found.last_name);
                        }
                    });
                }

                // Fallback Real-time Poller (Runs in background to guarantee real-time even if websockets fail)
                let pollInterval = setInterval(() => {
                    this.refreshContactsSilently();
                    if (this.activeContactId) this.fetchMessagesSilently();
                }, 3000);

                if (typeof window.Echo !== 'undefined') {
                    window.Echo.private('chat.' + authId)
                        .subscribed(() => {
                            console.log('WebSocket successfully connected to chat.' + authId);
                            clearInterval(pollInterval); // WebSockets are working, disable fallback polling
                        })
                        .listen('.MessageSent', (e) => {
                            const msg = e.message;
                            
                            // Realtime injection
                            if (this.activeContactId === msg.user_id) {
                                this.messages.push(msg);
                                this.scrollToBottom();
                                this.markAsRead(msg.user_id);
                            }
                            
                            // Sidebar reactivity
                            let contact = this.contacts.find(c => c.id === msg.user_id);
                            if (contact) {
                                contact.latest_message_at = msg.created_at;
                                if (this.activeContactId !== msg.user_id) {
                                    contact.unread_count = (contact.unread_count || 0) + 1;
                                }
                            } else {
                                this.refreshContactsSilently();
                            }
                        })
                        .error((error) => {
                            console.error('WebSocket Error:', error);
                        });
                }
            },

            selectContact(id, name) {
                this.activeContactId = parseInt(id);
                this.activeContactName = name;
                
                let contact = this.contacts.find(c => c.id === this.activeContactId);
                if(contact) contact.unread_count = 0;

                this.fetchMessages();
            },

            fetchMessages() {
                fetch(`/user/messages/fetch/${this.activeContactId}`, {
                    headers: { 'Accept': 'application/json' }
                })
                .then(res => res.json())
                .then(data => {
                    this.messages = data;
                    this.scrollToBottom();
                })
                .catch(err => console.error("Fetch error:", err));
            },

            fetchMessagesSilently() {
                if (!this.activeContactId) return;
                fetch(`/user/messages/fetch/${this.activeContactId}`, {
                    headers: { 'Accept': 'application/json' }
                })
                .then(res => res.json())
                .then(data => {
                    if (this.messages.length !== data.length) {
                        this.messages = data;
                        this.scrollToBottom();
                        this.markAsRead(this.activeContactId);
                    }
                })
                .catch(err => console.error("Silent fetch error:", err));
            },

            refreshContactsSilently() {
                fetch(`/user/messages/contacts`, {
                    headers: { 'Accept': 'application/json' }
                })
                .then(res => res.json())
                .then(data => {
                    // Update only if data actually changed to prevent DOM jitter
                    if (JSON.stringify(this.contacts) !== JSON.stringify(data)) {
                        this.contacts = data;
                    }
                });
            },

            markAsRead(contactId) {
                const tokenMeta = document.querySelector('meta[name="csrf-token"]');
                const csrfToken = tokenMeta ? tokenMeta.getAttribute('content') : '';

                fetch(`/user/messages/${contactId}/read`, {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    }
                }).catch(err => console.error("Mark read error:", err));
            },

            sendMessage() {
                const text = this.newMessage ? this.newMessage.trim() : '';
                if (!text || !this.activeContactId || this.isSending) return;

                this.isSending = true;
                const msgPayload = { receiver_id: this.activeContactId, message: text };

                const backupText = text;
                this.newMessage = '';
                if(this.$refs.textarea) this.$refs.textarea.style.height = '52px';

                const tokenMeta = document.querySelector('meta[name="csrf-token"]');
                const csrfToken = tokenMeta ? tokenMeta.getAttribute('content') : '';

                fetch('/user/messages', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(msgPayload)
                })
                .then(async response => {
                    if (!response.ok) {
                        const err = await response.json().catch(() => ({}));
                        throw new Error(err.error || err.message || 'Server Error');
                    }
                    return response.json();
                })
                .then(data => {
                    this.messages.push(data);
                    this.scrollToBottom();
                    
                    let contact = this.contacts.find(c => c.id === this.activeContactId);
                    if (contact) contact.latest_message_at = data.created_at;
                })
                .catch(error => {
                    console.error("Message Send Error:", error);
                    alert("Failed to send: " + error.message);
                    this.newMessage = backupText; 
                })
                .finally(() => {
                    this.isSending = false;
                    if(this.$refs.textarea) this.$refs.textarea.focus();
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
                e.target.style.height = '52px';
                e.target.style.height = Math.min(e.target.scrollHeight, 120) + 'px';
            },

            scrollToBottom() {
                setTimeout(() => {
                    const container = document.getElementById('messages-container');
                    if(container) container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' });
                }, 50);
            }
        }));
    });
</script>
@endsection