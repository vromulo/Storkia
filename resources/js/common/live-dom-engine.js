const EXCLUDED_FORM_ACTIONS = [
    '/login',
    '/logout',
    '/register',
    '/admin/login',
    '/admin/logout',
    '/seller/login',
    '/logistics/login',
    '/report/export',
    '/pdf',
    '/print'
];

class StorkiaLiveDomEngine {
    constructor() {
        this.isNavigating = false;
        this.pollTimer = null;
        this.pollIntervalMs = 15000;
        this.abortController = null;
        this.initialized = false;
    }

    init() {
        if (this.initialized) return;
        this.initialized = true;

        this.bindGlobalFormInterceptor();
        this.bindGlobalLinkInterceptor();
        this.bindGlobalAutoSubmitInputs();
        this.bindPopState();
        this.startRealtimePolling();
    }

    getProgressBar() {
        return document.getElementById('storkia-live-progress');
    }

    startProgress() {
        const bar = this.getProgressBar();
        if (!bar) return;
        bar.style.opacity = '1';
        bar.style.width = '35%';
        setTimeout(() => {
            if (this.isNavigating && bar) {
                bar.style.width = '75%';
            }
        }, 140);
    }

    finishProgress() {
        const bar = this.getProgressBar();
        if (!bar) return;
        bar.style.width = '100%';
        setTimeout(() => {
            if (bar) {
                bar.style.opacity = '0';
                setTimeout(() => {
                    if (bar) bar.style.width = '0%';
                }, 220);
            }
        }, 180);
    }

    getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    showToast(message, type = 'success') {
        if (!message) return;
        window.dispatchEvent(new CustomEvent('notify', {
            detail: { message, type }
        }));
        window.dispatchEvent(new CustomEvent('toast', {
            detail: { message, type }
        }));

        let container = document.getElementById('storkia-global-live-toasts');
        if (!container) {
            container = document.createElement('div');
            container.id = 'storkia-global-live-toasts';
            container.className = 'fixed top-5 right-5 z-[9990] flex flex-col gap-2.5 max-w-md w-full pointer-events-none px-4 sm:px-0';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        const isError = type === 'error' || type === 'danger';
        const isWarning = type === 'warning';

        toast.className = `pointer-events-auto flex items-start justify-between gap-3 p-4 rounded-2xl shadow-xl border text-xs sm:text-sm font-bold transition-all duration-300 transform translate-y-0 opacity-100 ${
            isError
                ? 'bg-red-50 border-red-200 text-red-800'
                : (isWarning ? 'bg-amber-50 border-amber-200 text-amber-900' : 'bg-emerald-50 border-emerald-200 text-emerald-800')
        }`;

        const textSpan = document.createElement('span');
        textSpan.className = 'leading-relaxed';
        textSpan.textContent = message;

        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'text-current opacity-60 hover:opacity-100 font-extrabold text-base leading-none cursor-pointer shrink-0';
        closeBtn.innerHTML = '&times;';
        closeBtn.onclick = () => toast.remove();

        toast.appendChild(textSpan);
        toast.appendChild(closeBtn);
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-8px)';
            setTimeout(() => toast.remove(), 300);
        }, 4200);
    }

    shouldInterceptForm(form, submitter = null) {
        if (!form || !(form instanceof HTMLFormElement)) return false;
        if (form.hasAttribute('data-no-live') || form.hasAttribute('data-no-ajax')) return false;
        if (submitter && (submitter.hasAttribute('data-no-live') || submitter.hasAttribute('data-no-ajax'))) return false;
        if (form.target && form.target !== '_self') return false;
        if (form.closest('[wire\\:id]')) return false;

        const rawAction = form.getAttribute('action') || window.location.href;
        try {
            const url = new URL(rawAction, window.location.origin);
            if (url.origin !== window.location.origin) return false;
            if (EXCLUDED_FORM_ACTIONS.some((p) => url.pathname.endsWith(p) || url.pathname.includes(p))) {
                return false;
            }
        } catch (e) {
            return false;
        }

        return true;
    }

    shouldInterceptLink(anchor) {
        if (!anchor || !(anchor instanceof HTMLAnchorElement)) return false;
        if (anchor.hasAttribute('data-no-live') || anchor.hasAttribute('data-no-ajax')) return false;
        if (anchor.hasAttribute('download')) return false;
        if (anchor.target && anchor.target !== '_self') return false;
        if (anchor.closest('[wire\\:id]')) return false;

        const rawHref = anchor.getAttribute('href');
        if (!rawHref || rawHref.startsWith('#') || rawHref.startsWith('javascript:') || rawHref.startsWith('mailto:') || rawHref.startsWith('tel:')) {
            return false;
        }

        try {
            const url = new URL(rawHref, window.location.origin);
            if (url.origin !== window.location.origin) return false;
            if (EXCLUDED_FORM_ACTIONS.some((p) => url.pathname.endsWith(p) || url.pathname.includes(p))) {
                return false;
            }

            const isSamePathname = url.pathname === window.location.pathname;
            const isExplicitLive = anchor.hasAttribute('data-live-link');
            const isPagination = Boolean(anchor.closest('nav[role="navigation"]'));

            return isSamePathname || isExplicitLive || isPagination;
        } catch (e) {
            return false;
        }
    }

    setButtonLoading(btn, isLoading) {
        if (!btn) return;
        if (isLoading) {
            btn.dataset.originalDisabled = btn.disabled ? '1' : '0';
            btn.disabled = true;
            btn.classList.add('opacity-75', 'pointer-events-none');
        } else {
            if (btn.dataset.originalDisabled !== '1') {
                btn.disabled = false;
            }
            btn.classList.remove('opacity-75', 'pointer-events-none');
        }
    }

    bindGlobalFormInterceptor() {
        document.addEventListener('submit', async (event) => {
            if (event.defaultPrevented) return;
            const form = event.target;
            const submitter = event.submitter || form.querySelector('button[type="submit"], input[type="submit"]');

            if (!this.shouldInterceptForm(form, submitter)) return;

            event.preventDefault();
            await this.submitForm(form, submitter);
        });
    }

    async submitForm(form, submitter = null) {
        const method = (form.getAttribute('method') || 'GET').toUpperCase();
        const rawAction = form.getAttribute('action') || window.location.href;
        const formData = new FormData(form);

        if (submitter && submitter.name) {
            formData.append(submitter.name, submitter.value || '');
        }

        this.setButtonLoading(submitter, true);

        try {
            if (method === 'GET') {
                const url = new URL(rawAction, window.location.origin);
                const params = new URLSearchParams();
                for (const [key, val] of formData.entries()) {
                    if (typeof val === 'string') {
                        params.append(key, val);
                    }
                }
                url.search = params.toString();
                const isSamePage = url.pathname === window.location.pathname;
                if (!isSamePage) {
                    window.location.href = url.toString();
                    return;
                }
                await this.visit(url.toString(), {
                    method: 'GET',
                    preserveScroll: true,
                    pushState: true
                });
            } else {
                await this.visit(rawAction, {
                    method: 'POST',
                    body: formData,
                    preserveScroll: true,
                    pushState: false
                });
            }
        } finally {
            this.setButtonLoading(submitter, false);
        }
    }

    bindGlobalLinkInterceptor() {
        document.addEventListener('click', async (event) => {
            if (event.defaultPrevented) return;
            if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

            const anchor = event.target.closest('a[href]');
            if (!this.shouldInterceptLink(anchor)) return;

            event.preventDefault();
            const targetUrl = new URL(anchor.href, window.location.origin).toString();
            const isSamePathname = new URL(targetUrl).pathname === window.location.pathname;

            await this.visit(targetUrl, {
                method: 'GET',
                preserveScroll: isSamePathname,
                pushState: true
            });
        });
    }

    bindGlobalAutoSubmitInputs() {
        document.addEventListener('change', async (event) => {
            const target = event.target;
            if (!target || !(target instanceof HTMLElement)) return;

            if (target.matches('input[type="date"][name="ref_date"], [data-live-autosubmit]')) {
                const form = target.closest('form');
                if (form && this.shouldInterceptForm(form)) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    await this.submitForm(form);
                }
            }
        }, true);
    }

    bindPopState() {
        window.addEventListener('popstate', () => {
            if (window.StorkiaPreloader) {
                window.StorkiaPreloader.hide();
            }
            this.visit(window.location.href, {
                method: 'GET',
                preserveScroll: true,
                pushState: false
            });
        });
    }

    isUserBusyWithOverlayOrInput() {
        const active = document.activeElement;
        if (active && ['INPUT', 'TEXTAREA', 'SELECT'].includes(active.tagName)) {
            const inputType = (active.getAttribute('type') || '').toLowerCase();
            if (!['radio', 'checkbox', 'button', 'submit'].includes(inputType)) {
                return true;
            }
        }

        const openModals = document.querySelectorAll('.fixed.inset-0');
        for (const modal of openModals) {
            if (modal.id === 'storkia-asset-preloader') continue;
            const style = window.getComputedStyle(modal);
            if (style.display !== 'none' && style.visibility !== 'hidden' && Number(style.opacity) > 0) {
                return true;
            }
        }

        return false;
    }

    startRealtimePolling() {
        if (this.pollTimer) clearInterval(this.pollTimer);

        this.pollTimer = setInterval(() => {
            if (document.hidden || this.isNavigating) return;
            if (this.isUserBusyWithOverlayOrInput()) return;

            const path = window.location.pathname;
            if (
                path.includes('/create') ||
                path.includes('/edit') ||
                path.includes('/checkout') ||
                path.includes('/settings') ||
                path.includes('/profile')
            ) {
                this.refreshNotificationsOnly();
                return;
            }

            this.visit(window.location.href, {
                method: 'GET',
                preserveScroll: true,
                pushState: false,
                silentPoll: true
            });
        }, this.pollIntervalMs);

        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && !this.isNavigating && !this.isUserBusyWithOverlayOrInput()) {
                this.refreshNotificationsOnly();
            }
        });
    }

    async refreshNotificationsOnly() {
        const notifRegion = document.querySelector('[data-live-notifications]');
        if (!notifRegion) return;

        try {
            const response = await fetch(window.location.href, {
                method: 'GET',
                headers: {
                    'Accept': 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Storkia-Poll': '1'
                },
                credentials: 'same-origin'
            });
            if (!response.ok) return;
            const html = await response.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            this.syncNotificationRegions(doc);
        } catch (e) {}
    }

    syncNotificationRegions(newDoc) {
        const currentNotifs = document.querySelectorAll('[data-live-notifications]');
        const incomingNotifs = newDoc.querySelectorAll('[data-live-notifications]');

        if (currentNotifs.length === incomingNotifs.length) {
            currentNotifs.forEach((el, idx) => {
                const isDropdownOpen = el.__x?.$data?.notifOpen || el._x_dataStack?.[0]?.notifOpen;
                if (isDropdownOpen) return;

                const nextHtml = incomingNotifs[idx].innerHTML;
                if (el.innerHTML.trim() !== nextHtml.trim()) {
                    el.innerHTML = nextHtml;
                    if (window.Alpine) {
                        window.Alpine.initTree(el);
                    }
                }
            });
        }
    }

    cleanupTeleportedModals() {
        document.querySelectorAll('body > div.fixed.inset-0').forEach((el) => {
            if (el.id === 'storkia-asset-preloader') return;
            if (el.hasAttribute('data-keep-global')) return;
            el.remove();
        });
    }

    executeInlineScripts(container) {
        if (!container) return;
        const scripts = container.querySelectorAll('script');
        scripts.forEach((oldScript) => {
            const newScript = document.createElement('script');
            Array.from(oldScript.attributes).forEach((attr) => {
                newScript.setAttribute(attr.name, attr.value);
            });
            newScript.textContent = oldScript.textContent;
            oldScript.parentNode?.replaceChild(newScript, oldScript);
        });
    }

    extractAndBroadcastAlerts(newDoc) {
        const successBanner = newDoc.querySelector('.bg-green-50.border-green-200.text-green-700');
        if (successBanner && successBanner.textContent.trim()) {
            this.showToast(successBanner.textContent.trim(), 'success');
        }
    }

    async visit(url, options = {}) {
        const {
            method = 'GET',
            body = null,
            preserveScroll = true,
            pushState = false,
            silentPoll = false,
            beforeSwap = null,
            afterSwap = null
        } = options;

        if (this.abortController && !silentPoll) {
            this.abortController.abort();
        }
        const controller = new AbortController();
        if (!silentPoll) {
            this.abortController = controller;
            this.isNavigating = true;
            this.startProgress();
        }

        const scrollContainer = document.querySelector('#storkia-main-scroll') || document.querySelector('main') || window;
        const prevWindowScrollY = window.scrollY;
        const prevContainerScrollTop = scrollContainer !== window ? scrollContainer.scrollTop : 0;

        try {
            const headers = {
                'Accept': 'text/html, application/xhtml+xml, application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Storkia-Live': '1'
            };

            if (method !== 'GET') {
                headers['X-CSRF-TOKEN'] = this.getCsrfToken();
            }

            const response = await fetch(url, {
                method,
                headers,
                body,
                credentials: 'same-origin',
                signal: controller.signal
            });

            const contentType = response.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                const data = await response.json();
                if (data.redirect) {
                    window.location.href = data.redirect;
                    return data;
                }
                if (data.message) {
                    this.showToast(data.message, response.ok ? 'success' : 'error');
                }
                return data;
            }

            const finalUrl = response.url || url;
            const finalPath = new URL(finalUrl, window.location.origin).pathname;

            if (
                finalPath.endsWith('/login') ||
                finalPath.includes('/checkout/success')
            ) {
                window.location.href = finalUrl;
                return;
            }

            const html = await response.text();
            const newDoc = new DOMParser().parseFromString(html, 'text/html');

            if (newDoc.title) {
                document.title = newDoc.title;
            }

            this.syncNotificationRegions(newDoc);

            const currentMain = document.getElementById('storkia-main-content') || document.querySelector('main');
            const incomingMain = newDoc.getElementById('storkia-main-content') || newDoc.querySelector('main');

            if (!currentMain || !incomingMain) {
                if (!silentPoll) {
                    window.location.href = finalUrl;
                }
                return;
            }

            if (silentPoll && this.isUserBusyWithOverlayOrInput()) {
                return;
            }

            let preservedState = null;
            if (typeof beforeSwap === 'function') {
                preservedState = beforeSwap(currentMain, newDoc);
            }

            if (!silentPoll) {
                this.cleanupTeleportedModals();
            }

            currentMain.innerHTML = incomingMain.innerHTML;
            this.executeInlineScripts(currentMain);

            if (window.Alpine) {
                window.Alpine.initTree(currentMain);
            }

            if (typeof afterSwap === 'function') {
                afterSwap(currentMain, preservedState);
            }

            if (!silentPoll && method !== 'GET') {
                this.extractAndBroadcastAlerts(newDoc);
            }

            if (window.StorkiaPreloader) {
                window.StorkiaPreloader.hide();
                window.StorkiaPreloader.recordPage(finalPath);
            }

            if (pushState || (method !== 'GET' && finalUrl !== window.location.href)) {
                window.history.pushState({ storkiaLive: true }, newDoc.title || document.title, finalUrl);
            }

            if (preserveScroll) {
                if (scrollContainer !== window) {
                    scrollContainer.scrollTop = prevContainerScrollTop;
                }
                window.scrollTo({ top: prevWindowScrollY, behavior: 'instant' });
            } else {
                if (scrollContainer !== window) {
                    scrollContainer.scrollTop = 0;
                }
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        } catch (err) {
            if (err.name !== 'AbortError' && !silentPoll) {
                console.error('StorkiaLive navigation error:', err);
            }
        } finally {
            if (!silentPoll) {
                this.isNavigating = false;
                this.finishProgress();
            }
        }
    }
}

const storkiaLiveInstance = new StorkiaLiveDomEngine();
window.StorkiaLive = storkiaLiveInstance;

export default storkiaLiveInstance;