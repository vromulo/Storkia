/**
 * Shared chat window (Alpine component) used by every chat in Storkia:
 *
 *   mode "direct"        Buyer <-> Seller, Seller <-> Logistics (messages table)
 *   mode "support-user"  a Buyer / Seller / Logistics member talking to Storkia Support
 *   mode "support-admin" the admin Help Center inbox
 *
 * The page does not hold any chat logic itself: the controller builds one
 * config object (see HandlesUserChat::chatConfig and the Help Center
 * controllers) and the markup in components/chat/window.blade.php binds to the
 * state and methods below.
 *
 * Real-time: when Laravel Echo is available the component listens on a private
 * channel and only polls slowly as a safety net. Without Echo it polls every
 * few seconds, exactly like the original chat did.
 *
 * Ids are always compared as strings because the Help Center uses the id
 * "support" for the member's single conversation.
 */

const MESSAGE_LIMIT = 1000;
const MAX_FILE_BYTES = 5 * 1024 * 1024;
const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const POLL_FAST_MS = 4000;
const POLL_SLOW_MS = 20000;

const DEFAULT_LABELS = {
    title: 'Messages',
    countNoun: 'Chats',
    search: 'Search conversations...',
    emptyList: 'No conversations yet',
    emptyTitle: 'Your Messages',
    emptyHint: 'Select a conversation on the left.',
    placeholder: 'Type a message (max 1,000 chars)...',
    defaultSubtitle: '',
};

function normalizeConfig(config) {
    const cfg = config || {};

    return {
        mode: cfg.mode || 'direct',
        single: !!cfg.single,
        authId: cfg.authId,
        selfKey: cfg.selfKey || '',
        endpoints: Object.assign(
            { contacts: null, messages: '', send: '', read: null, shipments: null, status: null },
            cfg.endpoints || {}
        ),
        realtime: cfg.realtime || null,
        requestedContactId: cfg.requestedContactId ?? null,
        draft: cfg.draft || null,
        supportStatus: cfg.supportStatus || null,
        serverSearch: !!cfg.serverSearch,
        contacts: Array.isArray(cfg.contacts) ? cfg.contacts : [],
        labels: Object.assign({}, DEFAULT_LABELS, cfg.labels || {}),
        groups: Array.isArray(cfg.groups) ? cfg.groups : [],
        statusFilters: Array.isArray(cfg.statusFilters) ? cfg.statusFilters : [],
        defaultStatus: cfg.defaultStatus || 'all',
        quickReplies: cfg.quickReplies || {},
        allowShipment: !!cfg.allowShipment,
    };
}

function normalizeContact(contact) {
    return Object.assign({ unread_count: 0 }, contact);
}

function timeOf(contact) {
    return contact.latest_message_at ? new Date(contact.latest_message_at).getTime() : 0;
}

export default function chatWindow(config = {}) {
    const cfg = normalizeConfig(config);

    return {
        cfg,
        authId: cfg.authId,
        selfKey: cfg.selfKey,
        mode: cfg.mode,
        single: cfg.single,
        labels: cfg.labels,

        contacts: cfg.contacts.map(normalizeContact),
        activeContact: null,
        messages: [],
        loadingMessages: false,

        contactSearch: '',
        activeGroup: 'all',
        statusFilter: cfg.defaultStatus,
        supportStatus: cfg.supportStatus,
        statusBusy: false,

        draft: cfg.draft,
        newMessage: '',
        selectedFile: null,
        selectedFileName: '',
        errorBanner: '',
        lightboxUrl: '',
        isSending: false,
        mobileChatOpen: false,

        shipmentPickerOpen: false,
        shipmentOptions: [],
        loadingShipments: false,

        echoConnected: false,
        now: new Date(),

        _tickTimer: null,
        _pollTimer: null,
        _searchTimer: null,
        _echoTries: 0,
        _echoChannel: null,
        _echoSubscribed: false,
        _destroyed: false,

        // ------------------------------------------------------------------
        // Derived state
        // ------------------------------------------------------------------

        get isAdminMode() {
            return this.mode === 'support-admin';
        },

        get isSupportUser() {
            return this.mode === 'support-user';
        },

        get hasGroups() {
            return !this.single && this.cfg.groups.length > 1;
        },

        get hasStatusFilters() {
            return this.isAdminMode && this.cfg.statusFilters.length > 0;
        },

        get filteredContacts() {
            const q = (this.contactSearch || '').trim().toLowerCase();

            return this.contacts
                .filter((c) => {
                    if (this.hasGroups && this.activeGroup !== 'all' && c.group !== this.activeGroup) return false;
                    if (this.hasStatusFilters && this.statusFilter !== 'all' && c.status !== this.statusFilter) return false;
                    if (q) {
                        const haystack = [c.display_name, c.first_name, c.last_name, c.email]
                            .filter(Boolean)
                            .join(' ')
                            .toLowerCase();
                        if (!haystack.includes(q)) return false;
                    }
                    return true;
                })
                .sort((a, b) => timeOf(b) - timeOf(a) || String(a.display_name || '').localeCompare(String(b.display_name || '')));
        },

        get activeStatus() {
            if (this.isSupportUser) return this.supportStatus;
            return this.activeContact ? (this.activeContact.status || null) : null;
        },

        get isResolved() {
            return this.activeStatus === 'resolved';
        },

        get canResolve() {
            return this.isAdminMode && !!this.cfg.endpoints.status && !!this.activeContact;
        },

        get canAttachShipment() {
            return !!(this.cfg.allowShipment && this.cfg.endpoints.shipments && this.activeContact);
        },

        get canSend() {
            return !!this.activeContact
                && !this.isSending
                && (((this.newMessage || '').trim() !== '') || !!this.selectedFile || !!this.draft);
        },

        get quickReplies() {
            const all = this.cfg.quickReplies || {};
            const group = this.activeContact ? this.activeContact.group : null;

            return all[group] || all.default || [];
        },

        // ------------------------------------------------------------------
        // Lifecycle
        // ------------------------------------------------------------------

        init() {
            this._tickTimer = setInterval(() => { this.now = new Date(); }, 30000);

            if (this.cfg.serverSearch) {
                const reload = () => this.refreshContacts();
                this.$watch('contactSearch', () => {
                    clearTimeout(this._searchTimer);
                    this._searchTimer = setTimeout(reload, 300);
                });
                this.$watch('statusFilter', reload);
                this.$watch('activeGroup', reload);
            }

            if (this.single) {
                if (this.contacts.length) this.selectContact(this.contacts[0]);
            } else if (this.cfg.requestedContactId !== null && this.cfg.requestedContactId !== undefined) {
                const requested = this.findContact(this.cfg.requestedContactId);
                if (requested) {
                    // A deep-linked resolved conversation must stay visible in the list.
                    if (this.hasStatusFilters && requested.status === 'resolved') this.statusFilter = 'all';
                    this.selectContact(requested);
                }
            }

            this.subscribe();
            this.schedulePoll();
        },

        destroy() {
            this._destroyed = true;
            clearInterval(this._tickTimer);
            clearTimeout(this._pollTimer);
            clearTimeout(this._searchTimer);

            if (this._echoChannel && window.Echo) {
                try { window.Echo.leave(this._echoChannel); } catch (e) { /* ignore */ }
            }
        },

        // ------------------------------------------------------------------
        // Real-time (Echo) with polling fallback
        // ------------------------------------------------------------------

        subscribe() {
            const rt = this.cfg.realtime;
            if (!rt || !rt.channel || this._destroyed) return;

            if (!window.Echo) {
                // The Echo bundle may finish loading a moment after Alpine starts.
                if (this._echoTries++ < 5) setTimeout(() => this.subscribe(), 1500);
                return;
            }

            try {
                const channel = window.Echo.private(rt.channel);
                channel.listen(rt.event, (e) => this.handleIncoming(e));

                if (typeof channel.subscribed === 'function') {
                    channel.subscribed(() => { this._echoSubscribed = true; this.updateEchoState(); });
                }
                if (typeof channel.error === 'function') {
                    channel.error(() => { this._echoSubscribed = false; this.updateEchoState(); });
                }

                this._echoChannel = rt.channel;

                const connection = this.echoConnection();
                if (connection) connection.bind('state_change', () => this.updateEchoState());
                this.updateEchoState();
            } catch (e) {
                this.echoConnected = false;
            }
        },

        echoConnection() {
            return (window.Echo && window.Echo.connector && window.Echo.connector.pusher
                && window.Echo.connector.pusher.connection) || null;
        },

        updateEchoState() {
            const connection = this.echoConnection();
            this.echoConnected = this._echoSubscribed && (!connection || connection.state === 'connected');
        },

        schedulePoll() {
            if (this._destroyed) return;

            const delay = this.echoConnected ? POLL_SLOW_MS : POLL_FAST_MS;
            this._pollTimer = setTimeout(async () => {
                if (!document.hidden) {
                    await this.refreshContacts();
                    if (this.activeContact) await this.loadMessages(false);
                }
                this.schedulePoll();
            }, delay);
        },

        handleIncoming(e) {
            const msg = e && e.payload;
            if (!msg) return;

            let contactId;
            if (this.mode === 'direct') contactId = msg.user_id;
            else if (this.isSupportUser) contactId = 'support';
            else contactId = e.conversation_id;

            if (this.isAdminMode && e.contact) this.mergeContact(e.contact);

            const contact = this.findContact(contactId);
            if (!contact) {
                // Unknown sender (first message from a new contact): reload the list.
                this.refreshContacts();
                return;
            }

            const viewing = this.activeContact
                && String(this.activeContact.id) === String(contactId);

            if (viewing) {
                this.appendMessage(msg);
                this.scrollToBottom();
                if (!document.hidden) this.markRead();
            } else {
                contact.unread_count = (contact.unread_count || 0) + (this.isAdminMode ? 0 : 1);
            }

            if (!this.isAdminMode || !e.contact) {
                contact.latest_message_at = msg.created_at;
                contact.latest_message_preview = this.previewFor(msg);
            }
        },

        // ------------------------------------------------------------------
        // Network
        // ------------------------------------------------------------------

        csrf() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        },

        url(template, id) {
            return String(template || '').replace('{id}', encodeURIComponent(String(id)));
        },

        async request(url, options = {}) {
            const method = String(options.method || 'GET').toUpperCase();
            const headers = Object.assign(
                { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                options.headers || {}
            );

            if (method !== 'GET') headers['X-CSRF-TOKEN'] = this.csrf();

            // fetch() does not add the socket id the way axios does; without it
            // the server's ->toOthers() would echo our own message back to us.
            const socketId = window.Echo && typeof window.Echo.socketId === 'function' ? window.Echo.socketId() : null;
            if (socketId) headers['X-Socket-ID'] = socketId;

            const response = await fetch(url, Object.assign({}, options, { method, headers, credentials: 'same-origin' }));

            let data = null;
            try { data = await response.json(); } catch (e) { data = null; }

            if (!response.ok) {
                const firstError = data && data.errors ? (Object.values(data.errors)[0] || [])[0] : null;
                throw new Error(firstError || (data && data.message) || 'Something went wrong. Please try again.');
            }

            return data;
        },

        async refreshContacts() {
            const url = this.cfg.endpoints.contacts;
            if (!url) return;

            const params = new URLSearchParams();
            if (!this.isAdminMode && this.cfg.requestedContactId !== null && this.cfg.requestedContactId !== undefined) {
                params.set('contact', this.cfg.requestedContactId);
            }
            if (this.cfg.serverSearch) {
                const q = (this.contactSearch || '').trim();
                if (q) params.set('q', q);
                if (this.hasStatusFilters && this.statusFilter !== 'all') params.set('status', this.statusFilter);
                if (this.hasGroups && this.activeGroup !== 'all') {
                    params.set('role', this.activeGroup.charAt(0).toUpperCase() + this.activeGroup.slice(1));
                }
            }

            try {
                const query = params.toString();
                this.applyContacts(await this.request(url + (query ? '?' + query : '')));
            } catch (e) {
                // Silent: the next poll will try again.
            }
        },

        applyContacts(list) {
            const activeId = this.activeContact ? String(this.activeContact.id) : null;
            this.contacts = (Array.isArray(list) ? list : []).map(normalizeContact);

            if (activeId) {
                const fresh = this.findContact(activeId);
                if (fresh) {
                    fresh.unread_count = 0; // we are looking at this conversation
                    this.activeContact = fresh;
                }
            }
        },

        async loadMessages(initial = false) {
            const contact = this.activeContact;
            if (!contact) return;

            const id = String(contact.id);
            if (initial) this.loadingMessages = true;

            try {
                const data = await this.request(this.url(this.cfg.endpoints.messages, id));

                // The user switched conversation while this request was in flight.
                if (!this.activeContact || String(this.activeContact.id) !== id) return;

                const list = Array.isArray(data) ? data : ((data && data.messages) || []);

                if (data && !Array.isArray(data)) {
                    if (this.isSupportUser && data.status !== undefined) this.supportStatus = data.status;
                    if (data.contact) this.mergeContact(data.contact);
                }

                const signature = (rows) => rows.map((m) => m.id + (m.is_read ? 'r' : 'u')).join(',');
                if (initial || signature(list) !== signature(this.messages)) {
                    this.messages = list;
                    this.scrollToBottom(initial);
                }

                if (this.activeContact) this.activeContact.unread_count = 0;
            } catch (e) {
                if (initial) this.errorBanner = e.message;
            } finally {
                if (initial) this.loadingMessages = false;
            }
        },

        markRead() {
            const contact = this.activeContact;
            if (!contact) return;

            contact.unread_count = 0;
            if (!this.cfg.endpoints.read) return;

            this.request(this.url(this.cfg.endpoints.read, contact.id), { method: 'PATCH' }).catch(() => {});
        },

        // ------------------------------------------------------------------
        // Contacts
        // ------------------------------------------------------------------

        findContact(id) {
            return this.contacts.find((c) => String(c.id) === String(id)) || null;
        },

        isActive(contact) {
            return !!this.activeContact && String(this.activeContact.id) === String(contact.id);
        },

        mergeContact(raw) {
            const contact = normalizeContact(raw);
            const isActive = this.activeContact && String(this.activeContact.id) === String(contact.id);

            if (isActive) contact.unread_count = 0;

            const index = this.contacts.findIndex((c) => String(c.id) === String(contact.id));
            if (index >= 0) this.contacts.splice(index, 1, contact);
            else this.contacts.push(contact);

            if (isActive) this.activeContact = this.findContact(contact.id);
        },

        selectContact(contact) {
            this.activeContact = contact;
            this.messages = [];
            this.errorBanner = '';
            this.shipmentPickerOpen = false;
            this.mobileChatOpen = true;
            contact.unread_count = 0;

            // A pending product / shipment card belongs to the deep-linked contact only.
            const requested = this.cfg.requestedContactId;
            const isRequested = requested !== null && requested !== undefined && String(requested) === String(contact.id);
            if (!isRequested) this.draft = null;
            if (this.draft && !(this.newMessage || '').trim()) this.newMessage = this.draftGreeting();

            this.loadMessages(true);
        },

        backToList() {
            this.mobileChatOpen = false;
        },

        groupUnread(key) {
            return this.contacts
                .filter((c) => key === 'all' || c.group === key)
                .reduce((sum, c) => sum + (c.unread_count || 0), 0);
        },

        initial(contact) {
            return String((contact && (contact.display_name || contact.first_name)) || '?').trim().charAt(0).toUpperCase() || '?';
        },

        // ------------------------------------------------------------------
        // Sending
        // ------------------------------------------------------------------

        draftGreeting() {
            if (!this.draft) return '';
            if (this.draft.type === 'product') return 'Hi! Is "' + this.draft.data.name + '" still available?';
            if (this.draft.type === 'shipment') return 'Hi! Regarding shipment ' + this.draft.data.tracking_number + ': ';
            return '';
        },

        async sendMessage() {
            this.errorBanner = '';

            const contact = this.activeContact;
            const text = (this.newMessage || '').trim();

            if (!contact || this.isSending) return;
            if (!text && !this.selectedFile && !this.draft) return;
            if (text.length > MESSAGE_LIMIT) {
                this.errorBanner = 'Chat message cannot exceed 1,000 characters.';
                return;
            }

            this.isSending = true;

            const form = new FormData();
            if (this.mode === 'direct') form.append('receiver_id', contact.id);
            if (text) form.append('message', text);
            if (this.mode === 'direct' && this.draft && this.draft.data && this.draft.data.id) {
                form.append(this.draft.type === 'product' ? 'product_id' : 'shipment_id', this.draft.data.id);
            }
            if (this.selectedFile) form.append('attachment', this.selectedFile);

            const backupText = this.newMessage;
            this.newMessage = '';
            if (this.$refs && this.$refs.textarea) this.$refs.textarea.style.height = '46px';

            try {
                const sent = await this.request(this.url(this.cfg.endpoints.send, contact.id), { method: 'POST', body: form });

                this.appendMessage(sent);
                this.draft = null;
                this.clearAttachment();

                contact.latest_message_at = sent.created_at;
                contact.latest_message_preview = this.previewFor(sent);

                // Writing to a resolved Help Center thread reopens it on the server.
                if (this.isSupportUser) this.supportStatus = 'open';

                this.scrollToBottom();
            } catch (e) {
                this.errorBanner = e.message;
                this.newMessage = backupText;
            } finally {
                this.isSending = false;
            }
        },

        appendMessage(msg) {
            if (!this.messages.some((m) => String(m.id) === String(msg.id))) this.messages.push(msg);
        },

        previewFor(msg) {
            let body = msg.message;
            if (!body) {
                if (msg.attachment_url) body = 'Sent a photo';
                else if (msg.product_card) body = 'Product inquiry';
                else if (msg.shipment_card) body = 'Shipment inquiry';
                else body = '';
            }

            let prefix = '';
            if (this.isAdminMode) prefix = msg.sender_type === 'admin' ? 'Support: ' : '';
            else if (this.isMine(msg)) prefix = 'You: ';
            else if (this.isSupportUser) prefix = 'Support: ';

            return prefix + body;
        },

        // ------------------------------------------------------------------
        // Composer helpers
        // ------------------------------------------------------------------

        useQuickReply(text) {
            this.newMessage = text;
            this.$nextTick(() => { if (this.$refs.textarea) this.$refs.textarea.focus(); });
        },

        onSelectAttachment(event) {
            this.errorBanner = '';

            const file = event.target.files && event.target.files[0];
            if (!file) return;

            if (!ALLOWED_IMAGE_TYPES.includes(file.type)) {
                this.errorBanner = 'Only JPG, PNG and WEBP images are allowed.';
                event.target.value = '';
                return;
            }
            if (file.size > MAX_FILE_BYTES) {
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
            if (this.$refs && this.$refs.fileInput) this.$refs.fileInput.value = '';
        },

        clearDraft() {
            this.draft = null;
        },

        async toggleShipmentPicker() {
            if (this.shipmentPickerOpen) {
                this.shipmentPickerOpen = false;
                return;
            }
            if (!this.canAttachShipment) return;

            this.shipmentPickerOpen = true;
            this.loadingShipments = true;

            try {
                const rows = await this.request(this.url(this.cfg.endpoints.shipments, this.activeContact.id));
                this.shipmentOptions = Array.isArray(rows) ? rows : [];
            } catch (e) {
                this.shipmentOptions = [];
                this.errorBanner = e.message;
            } finally {
                this.loadingShipments = false;
            }
        },

        pickShipment(card) {
            this.draft = { type: 'shipment', data: card };
            this.shipmentPickerOpen = false;
            if (!(this.newMessage || '').trim()) this.newMessage = this.draftGreeting();
            this.$nextTick(() => { if (this.$refs.textarea) this.$refs.textarea.focus(); });
        },

        async toggleStatus() {
            const contact = this.activeContact;
            const url = this.cfg.endpoints.status;
            if (!contact || !url || this.statusBusy) return;

            this.statusBusy = true;
            this.errorBanner = '';

            try {
                const next = contact.status === 'resolved' ? 'open' : 'resolved';
                const data = await this.request(this.url(url, contact.id), {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ status: next }),
                });

                if (data && data.contact) this.mergeContact(data.contact);
                else contact.status = data.status;
            } catch (e) {
                this.errorBanner = e.message;
            } finally {
                this.statusBusy = false;
            }
        },

        // ------------------------------------------------------------------
        // Message presentation
        // ------------------------------------------------------------------

        isMine(msg) {
            return !!msg && msg.sender_key === this.selfKey;
        },

        authorLabel(msg) {
            if (!msg || this.isMine(msg)) return '';
            if (this.isSupportUser) return msg.sender_label || '';
            if (this.isAdminMode && msg.sender_type === 'admin') return msg.agent_name || 'Support team';
            return '';
        },

        formatPrice(value) {
            return '\u20B1' + Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2 });
        },

        fullDate(dateString) {
            const date = new Date(dateString);
            return isNaN(date.getTime()) ? '' : date.toLocaleString();
        },

        formatTimeAgo(dateString) {
            if (!dateString) return '';

            const date = new Date(dateString);
            if (isNaN(date.getTime())) return dateString;

            const seconds = Math.floor((this.now - date) / 1000);
            if (seconds < 60) return 'Just now';

            const minutes = Math.floor(seconds / 60);
            if (minutes < 60) return minutes + 'm ago';

            const hours = Math.floor(minutes / 60);
            if (hours < 24) return hours + 'h ago';

            const days = Math.floor(hours / 24);
            if (days < 7) return days + 'd ago';

            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        },

        resizeTextarea(event) {
            event.target.style.height = '46px';
            event.target.style.height = Math.min(event.target.scrollHeight, 120) + 'px';
        },

        scrollToBottom(instant = false) {
            this.$nextTick(() => {
                const box = this.$refs.messagesBox;
                if (box) box.scrollTo({ top: box.scrollHeight, behavior: instant ? 'auto' : 'smooth' });
            });
        },
    };
}