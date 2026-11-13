import storkiaLive from '../common/live-dom-engine';

export default function orderQueueSort(config = {}) {
    const paramName = config.param || 'sort';
    const defaultValue = config.defaultValue || 'oldest';

    return {
        current: config.current === 'newest' ? 'newest' : 'oldest',

        change(value) {
            const selected = value === 'newest' ? 'newest' : 'oldest';

            if (selected === this.current) {
                return;
            }

            this.current = selected;

            // Start from the current URL so existing filters (status tab, search,
            // dashboard period/offset) are kept exactly as they are.
            const url = new URL(window.location.href);

            if (selected === defaultValue) {
                url.searchParams.delete(paramName);
            } else {
                url.searchParams.set(paramName, selected);
            }

            // A different sort order changes which orders land on each page,
            // so always go back to the first page.
            url.searchParams.delete('page');

            const target = url.toString();

            // Reuse the app's live DOM engine (same as the other filters and links)
            // so the list updates without a full page reload.
            if (storkiaLive && typeof storkiaLive.visit === 'function') {
                storkiaLive.visit(target, {
                    method: 'GET',
                    preserveScroll: true,
                    pushState: true,
                });
                return;
            }

            window.location.assign(target);
        },
    };
}