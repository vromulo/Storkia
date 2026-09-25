export default function searchableSelect(config = {}) {
    return {
        open: false,
        search: '',
        selected: config.selected || '',
        options: config.options || [],
        get filteredOptions() {
            if (!this.search.trim()) return this.options;
            return this.options.filter(opt => opt.toLowerCase().includes(this.search.toLowerCase()));
        },
        selectOption(opt) {
            this.selected = opt;
            this.open = false;
            this.search = '';
        }
    };
}