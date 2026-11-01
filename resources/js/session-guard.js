/**
 * Session guard
 *
 * One place that deals with an expired or revoked login for every fetch() call
 * in the app (cart, profile, address book, store settings, delete account...).
 * Before this, only 422 was handled, so a session that timed out showed a
 * generic error or failed silently.
 *
 * What it does, for same-origin requests only:
 *  1. Adds the X-CSRF-TOKEN header on POST / PUT / PATCH / DELETE calls that
 *     did not set one, using <meta name="csrf-token"> from the layout.
 *  2. On 401 / 419 shows one "Your session has expired" prompt with a button
 *     to the right login page. The original response is still returned, so a
 *     component that handles the error itself keeps working.
 *  3. On a 403 JSON reply from EnsureAccountActive / EnforceIdleTimeout that
 *     carries a redirect URL (suspended, deleted or timed-out account) it
 *     sends the browser there.
 *  4. Reloads a page restored from the back/forward cache when the person was
 *     signed in, so pressing Back after logout never shows account data.
 *
 * Opt out for one call with the header  X-Skip-Session-Guard: 1
 * Livewire requests are left alone: Livewire has its own expired-page dialog.
 */
(() => {
    if (window.__storkiaSessionGuard || typeof window.fetch !== 'function') {
        return;
    }

    window.__storkiaSessionGuard = true;

    const nativeFetch = window.fetch.bind(window);

    const SKIP_HEADER = 'X-Skip-Session-Guard';
    const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];
    const REDIRECT_CODES = ['account_suspended', 'account_archived', 'account_blocked', 'session_expired'];
    const EXCLUDED_PATH_PREFIXES = ['/livewire', '/broadcasting'];

    let promptOpen = false;
    let redirecting = false;

    const csrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const requestUrl = (input) => {
        if (typeof input === 'string') return input;
        if (input instanceof URL) return input.href;

        return input?.url || '';
    };

    const parseUrl = (input) => {
        try {
            return new URL(requestUrl(input), window.location.href);
        } catch (error) {
            return null;
        }
    };

    const isGuardedRequest = (url) => {
        if (!url || url.origin !== window.location.origin) return false;

        return !EXCLUDED_PATH_PREFIXES.some((prefix) => url.pathname.startsWith(prefix));
    };

    /**
     * The login page that matches where the person is. Layouts can set
     * <meta name="session-login-url">; otherwise it is worked out from the path.
     */
    const resolveLoginUrl = () => {
        const fromMeta = document.querySelector('meta[name="session-login-url"]')?.getAttribute('content');

        if (fromMeta) return fromMeta;

        const path = window.location.pathname;

        if (path.startsWith('/admin')) return '/admin/login';
        if (path.startsWith('/seller')) return '/seller/login';
        if (/^\/(logistics|rider|courier)/.test(path)) return '/logistics/login';

        return '/login';
    };

    const goTo = (target) => {
        if (redirecting) return;

        try {
            const url = new URL(target, window.location.href);

            // Only follow links that stay on this site.
            if (url.origin !== window.location.origin) return;

            redirecting = true;
            window.location.assign(url.href);
        } catch (error) {
            // Ignore a malformed redirect target.
        }
    };

    const showSessionExpiredPrompt = () => {
        if (promptOpen || redirecting) return;

        promptOpen = true;

        const overlay = document.createElement('div');
        overlay.setAttribute('role', 'alertdialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'session-guard-title');
        overlay.setAttribute('aria-describedby', 'session-guard-text');
        overlay.className =
            'fixed inset-0 z-[100000] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4';

        overlay.innerHTML = `
            <div class="w-full max-w-sm rounded-3xl border border-border-subtle bg-surface p-6 text-center shadow-2xl">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-brand-light/40 text-primary-dark">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h2 id="session-guard-title" class="text-lg font-extrabold text-text-main">Your session has expired</h2>
                <p id="session-guard-text" class="mt-2 text-sm leading-relaxed text-text-muted">
                    For your security you were signed out. Sign in again to continue.
                </p>
                <div class="mt-5 flex flex-col gap-2">
                    <a data-session-guard="login" href="#"
                        class="inline-flex w-full items-center justify-center rounded-xl bg-primary-dark px-4 py-3 text-sm font-bold text-surface shadow-md transition-all duration-200 hover:bg-text-main focus:outline-none focus:ring-2 focus:ring-primary-dark focus:ring-offset-2">
                        Sign in again
                    </a>
                    <button type="button" data-session-guard="stay"
                        class="w-full cursor-pointer rounded-xl px-4 py-2 text-sm font-semibold text-text-muted transition-colors hover:text-text-main focus:outline-none">
                        Stay on this page
                    </button>
                </div>
            </div>
        `;

        const loginLink = overlay.querySelector('[data-session-guard="login"]');
        const stayButton = overlay.querySelector('[data-session-guard="stay"]');

        // Set through the DOM, never through the HTML string.
        loginLink.setAttribute('href', resolveLoginUrl());

        const close = () => {
            document.removeEventListener('keydown', onKeydown);
            overlay.remove();
            promptOpen = false;
        };

        const onKeydown = (event) => {
            if (event.key === 'Escape') close();
        };

        stayButton.addEventListener('click', close);
        document.addEventListener('keydown', onKeydown);
        document.body.appendChild(overlay);
        loginLink.focus();
    };

    /**
     * A 403 whose JSON body names an account problem and carries a redirect
     * URL: send the browser there. Runs in the background so the caller still
     * gets its response immediately.
     */
    const followAccountRedirect = (response) => {
        const contentType = response.headers.get('content-type') || '';

        if (!contentType.includes('application/json')) return;

        response
            .clone()
            .json()
            .then((data) => {
                if (data && data.redirect && REDIRECT_CODES.includes(data.code)) {
                    goTo(data.redirect);
                }
            })
            .catch(() => {});
    };

    window.fetch = async function guardedFetch(input, init) {
        const url = parseUrl(input);
        const guarded = isGuardedRequest(url);

        let options = init;
        let skip = false;

        if (guarded) {
            const plainInput = typeof input === 'string' || input instanceof URL;
            const method = String(options?.method || (input instanceof Request ? input.method : 'GET')).toUpperCase();

            if (plainInput) {
                const headers = new Headers(options?.headers || undefined);

                skip = headers.has(SKIP_HEADER);

                if (!SAFE_METHODS.includes(method) && !headers.has('X-CSRF-TOKEN') && !headers.has('X-XSRF-TOKEN')) {
                    const token = csrfToken();

                    if (token) {
                        headers.set('X-CSRF-TOKEN', token);
                        options = { ...(options || {}), headers };
                    }
                }
            }
        }

        const response = await nativeFetch(input, options);

        if (!guarded || skip) return response;

        if (response.status === 401 || response.status === 419) {
            showSessionExpiredPrompt();
        } else if (response.status === 403) {
            followAccountRedirect(response);
        }

        return response;
    };

    // Back/forward cache: after logout, Back could show a signed-in page from
    // memory. Pages set <meta name="session-authenticated"> while signed in.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted && document.querySelector('meta[name="session-authenticated"]')) {
            window.location.reload();
        }
    });
})();