/**
 * "Fly to cart" animation + live cart badge helpers.
 *
 * Hooks in the navbar:
 *   [data-cart-icon]  -> the cart link/icon (animation target)
 *   [data-cart-badge] -> the little red count bubble
 */

const prefersReducedMotion = () =>
    window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function visibleCartIcon() {
    const icons = document.querySelectorAll('[data-cart-icon]');
    for (const el of icons) {
        const r = el.getBoundingClientRect();
        if (r.width > 0 && r.height > 0) return el;
    }
    return null;
}

/** Update (or reveal) the navbar badge. */
export function setCartBadge(count) {
    const n = Number(count);
    if (!Number.isFinite(n)) return;

    document.querySelectorAll('[data-cart-badge]').forEach((badge) => {
        if (n <= 0) {
            badge.classList.add('hidden');
            return;
        }
        badge.textContent = n > 99 ? '99+' : String(n);
        badge.classList.remove('hidden');
        badge.animate(
            [{ transform: 'scale(1)' }, { transform: 'scale(1.5)' }, { transform: 'scale(1)' }],
            { duration: 350, easing: 'ease-out' }
        );
    });
}

/** Current number shown on the badge (0 when hidden / absent). */
export function getCartBadgeCount() {
    const badge = document.querySelector('[data-cart-badge]');
    if (!badge || badge.classList.contains('hidden')) return 0;
    const n = parseInt(badge.textContent, 10);
    return Number.isFinite(n) ? n : 0;
}

function bumpCartIcon(icon) {
    icon.animate(
        [
            { transform: 'scale(1) rotate(0deg)' },
            { transform: 'scale(1.3) rotate(-10deg)', offset: 0.35 },
            { transform: 'scale(1.15) rotate(6deg)', offset: 0.65 },
            { transform: 'scale(1) rotate(0deg)' },
        ],
        { duration: 450, easing: 'ease-out' }
    );
}

/**
 * Fly a small thumbnail from `source` (e.g. the Add to Cart button) to the
 * navbar cart icon. Resolves when the animation is done. Never throws.
 *
 * @param {Object}      opts
 * @param {HTMLElement} opts.source    element the animation starts from
 * @param {string}      [opts.imageUrl] thumbnail to fly (falls back to a brand-coloured dot)
 */
export function flyToCart({ source, imageUrl } = {}) {
    return new Promise((resolve) => {
        try {
            const icon = visibleCartIcon();
            if (!source || !icon || prefersReducedMotion() || !source.animate) {
                if (icon) bumpCartIcon(icon);
                return resolve();
            }

            const from = source.getBoundingClientRect();
            const to = icon.getBoundingClientRect();
            const size = 48;

            const startX = from.left + from.width / 2 - size / 2;
            const startY = from.top + from.height / 2 - size / 2;
            const dx = to.left + to.width / 2 - size / 2 - startX;
            const dy = to.top + to.height / 2 - size / 2 - startY;

            const ghost = document.createElement('div');
            ghost.setAttribute('aria-hidden', 'true');
            Object.assign(ghost.style, {
                position: 'fixed',
                left: `${startX}px`,
                top: `${startY}px`,
                width: `${size}px`,
                height: `${size}px`,
                borderRadius: '9999px',
                overflow: 'hidden',
                zIndex: '9999',
                pointerEvents: 'none',
                background: imageUrl
                    ? `#fff center / contain no-repeat url("${imageUrl}")`
                    : 'var(--color-primary, #2563eb)',
                border: '2px solid #fff',
                boxShadow: '0 8px 20px rgba(0,0,0,.25)',
                willChange: 'transform, opacity',
            });
            document.body.appendChild(ghost);

            const anim = ghost.animate(
                [
                    { transform: 'translate(0, 0) scale(1)', opacity: 1 },
                    {
                        transform: `translate(${dx * 0.55}px, ${dy * 0.35 - 40}px) scale(0.8)`,
                        opacity: 1,
                        offset: 0.45,
                    },
                    { transform: `translate(${dx}px, ${dy}px) scale(0.2)`, opacity: 0.35 },
                ],
                { duration: 800, easing: 'cubic-bezier(.5, 0, .75, 0)', fill: 'forwards' }
            );

            const done = () => {
                ghost.remove();
                bumpCartIcon(icon);
                resolve();
            };
            anim.onfinish = done;
            anim.oncancel = done;
        } catch (e) {
            resolve();
        }
    });
}