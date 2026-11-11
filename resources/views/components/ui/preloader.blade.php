<div id="storkia-asset-preloader"
     style="display: none; opacity: 1;"
     class="fixed inset-0 z-[9999] flex items-center justify-center bg-white transition-opacity duration-300 ease-out pointer-events-auto">
    <style>
        @keyframes fillUp {
            0% {
                clip-path: inset(100% 0 0 0);
                opacity: 0.2;
            }
            50% {
                clip-path: inset(50% 0 0 0);
                opacity: 0.6;
            }
            100% {
                clip-path: inset(0 0 0 0);
                opacity: 1;
            }
        }
        .animate-logo-fill {
            animation: fillUp 1.5s cubic-bezier(0.4, 0, 0.2, 1) infinite alternate;
        }
        #storkia-live-progress {
            position: fixed;
            top: 0;
            left: 0;
            height: 3px;
            width: 0%;
            z-index: 10000;
            background: linear-gradient(90deg, #CF4173, #F39399, #5D3140);
            transition: width 0.25s ease, opacity 0.25s ease;
            opacity: 0;
            pointer-events: none;
        }
    </style>

    <div class="relative w-24 h-24 md:w-32 md:h-32 flex items-center justify-center">
        <img
            src="{{ asset('assets/pink-storkia-minimized.webp') }}"
            alt="{{ config('app.name') }}"
            class="absolute inset-0 w-full h-full object-contain opacity-20"
        />
        <img
            src="{{ asset('assets/pink-storkia-minimized.webp') }}"
            alt="{{ config('app.name') }}"
            class="absolute inset-0 w-full h-full object-contain animate-logo-fill"
        />
    </div>
</div>
<div id="storkia-live-progress"></div>

<script>
(function () {
    const VISITED_PAGES_KEY = 'storkia_visited_pages_v1';
    const LOADED_ASSETS_KEY = 'storkia_loaded_assets_v1';
    const preloaderEl = document.getElementById('storkia-asset-preloader');

    if (!preloaderEl) return;

    const getStoredSet = (key) => {
        try {
            const raw = sessionStorage.getItem(key);
            return new Set(raw ? JSON.parse(raw) : []);
        } catch (e) {
            return new Set();
        }
    };

    const saveStoredSet = (key, setObj) => {
        try {
            const arr = Array.from(setObj).slice(-400);
            sessionStorage.setItem(key, JSON.stringify(arr));
        } catch (e) {}
    };

    const visitedPages = getStoredSet(VISITED_PAGES_KEY);
    const loadedAssets = getStoredSet(LOADED_ASSETS_KEY);
    const currentPageKey = window.location.pathname;

    const navEntries = (window.performance && performance.getEntriesByType)
        ? performance.getEntriesByType('navigation')
        : [];
    const navType = navEntries.length > 0 ? navEntries[0].type : '';
    const isBackForward = navType === 'back_forward';
    const hasVisitedPageBefore = visitedPages.has(currentPageKey);

    let isHidden = false;
    let showDelayTimer = null;

    const recordCurrentAssetsAndPage = () => {
        visitedPages.add(currentPageKey);
        document.querySelectorAll('img').forEach((img) => {
            const src = img.currentSrc || img.getAttribute('src');
            if (src && img.complete && img.naturalWidth > 0) {
                loadedAssets.add(src);
            }
        });
        saveStoredSet(VISITED_PAGES_KEY, visitedPages);
        saveStoredSet(LOADED_ASSETS_KEY, loadedAssets);
    };

    const hidePreloader = (immediate = false) => {
        if (showDelayTimer) {
            clearTimeout(showDelayTimer);
            showDelayTimer = null;
        }
        if (isHidden) return;
        isHidden = true;
        recordCurrentAssetsAndPage();

        if (immediate || preloaderEl.style.display === 'none') {
            preloaderEl.style.display = 'none';
            preloaderEl.style.pointerEvents = 'none';
            return;
        }

        preloaderEl.style.opacity = '0';
        preloaderEl.style.pointerEvents = 'none';
        setTimeout(() => {
            preloaderEl.style.display = 'none';
        }, 280);
    };

    window.StorkiaPreloader = {
        hide: () => hidePreloader(true),
        recordAsset: (src) => {
            if (!src) return;
            loadedAssets.add(src);
            saveStoredSet(LOADED_ASSETS_KEY, loadedAssets);
        },
        recordPage: (path) => {
            visitedPages.add(path || window.location.pathname);
            saveStoredSet(VISITED_PAGES_KEY, visitedPages);
        }
    };

    if (isBackForward || hasVisitedPageBefore) {
        hidePreloader(true);
        return;
    }

    const getUnloadedPageImages = () => {
        const imgs = Array.from(document.querySelectorAll('img'));
        return imgs.filter((img) => {
            if (preloaderEl.contains(img)) return false;
            const src = img.currentSrc || img.getAttribute('src');
            if (!src || src === '' || src.startsWith('data:')) return false;
            if (loadedAssets.has(src)) return false;
            if (img.loading === 'lazy') return false;
            return !img.complete || img.naturalWidth === 0;
        });
    };

    const evaluateAssets = () => {
        if (isHidden) return;
        const pendingImgs = getUnloadedPageImages();

        if (pendingImgs.length === 0 && document.readyState !== 'loading') {
            hidePreloader(false);
            return;
        }

        if (!showDelayTimer && preloaderEl.style.display === 'none') {
            showDelayTimer = setTimeout(() => {
                if (!isHidden && getUnloadedPageImages().length > 0) {
                    preloaderEl.style.display = 'flex';
                    preloaderEl.style.opacity = '1';
                }
            }, 90);
        }

        let remaining = pendingImgs.length;
        if (remaining === 0) return;

        const onAssetSettled = (img) => {
            const src = img.currentSrc || img.getAttribute('src');
            if (src) loadedAssets.add(src);
            remaining--;
            if (remaining <= 0) {
                hidePreloader(false);
            }
        };

        pendingImgs.forEach((img) => {
            if (img.complete) {
                onAssetSettled(img);
            } else {
                img.addEventListener('load', () => onAssetSettled(img), { once: true });
                img.addEventListener('error', () => onAssetSettled(img), { once: true });
            }
        });
    };

    if (document.readyState === 'complete') {
        hidePreloader(true);
    } else {
        document.addEventListener('DOMContentLoaded', evaluateAssets, { once: true });
        window.addEventListener('load', () => hidePreloader(false), { once: true });
        setTimeout(() => hidePreloader(false), 2200);
    }

    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            hidePreloader(true);
        }
    });
})();
</script>