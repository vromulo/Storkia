@props([
    'placeholder' => 'Select an option',
    'options' => [],
    'valueKey' => 'code',
    'labelKey' => 'name',
    'disabled' => false,
    'hasError' => false,
    'loadingTarget' => null,
])

<div 
    x-data="{
        open: false,
        search: '',
        value: @entangle($attributes->wire('model')),
        options: {{ json_encode($options) }},
        valueKey: '{{ $valueKey }}',
        labelKey: '{{ $labelKey }}',

        get selectedLabel() {
            if (!this.value) return '';
            let match = this.options.find(opt => {
                let val = (typeof opt === 'object' && opt !== null) ? opt[this.valueKey] : opt;
                return String(val) === String(this.value);
            });
            if (match) {
                return (typeof match === 'object' && match !== null) ? match[this.labelKey] : match;
            }
            return '';
        },

        get filteredOptions() {
            if (!this.search.trim()) return this.options;
            return this.options.filter(opt => {
                let label = (typeof opt === 'object' && opt !== null) ? opt[this.labelKey] : String(opt);
                return label.toLowerCase().includes(this.search.toLowerCase());
            });
        },

        select(opt) {
            let val = (typeof opt === 'object' && opt !== null) ? opt[this.valueKey] : opt;
            this.value = val;
            this.open = false;
            this.search = '';
        },

        handleFocus() {
            if (!{{ $disabled ? 'true' : 'false' }}) {
                this.open = true;
                this.search = '';
            }
        },

        close() {
            this.open = false;
            this.search = '';
        }
    }"
    x-init="
        $watch('options', () => { search = ''; });
    "
    @click.away="close()"
    @keydown.escape.window="close()"
    class="relative w-full"
>
    <!-- Combined Input Box / Dropdown Trigger -->
    <div 
        @click="handleFocus()"
        class="flex items-center w-full border-2 rounded-xl bg-surface transition-all shadow-sm overflow-hidden 
            {{ $disabled ? 'bg-surface-subtle cursor-not-allowed border-border-subtle' : 'cursor-pointer hover:border-text-main/50' }}
            {{ $hasError ? 'border-danger focus-within:border-danger focus-within:ring-1 focus-within:ring-danger' : 'border-border-subtle focus-within:border-text-main focus-within:ring-1 focus-within:ring-text-main' }}"
    >
        <input 
            type="text"
            x-model="search"
            @focus="handleFocus()"
            @click="handleFocus()"
            :placeholder="open ? 'Type to search...' : (selectedLabel || '{{ $placeholder }}')"
            {{ $disabled ? 'disabled' : '' }}
            class="w-full py-2 px-3 bg-transparent outline-none text-sm text-text-main placeholder:text-text-muted cursor-text"
        >

        <div class="flex items-center pr-3 shrink-0 pointer-events-none">
            @if($loadingTarget)
                <div wire:loading wire:target="{{ $loadingTarget }}" class="text-primary text-xs font-bold mr-1">
                    ...
                </div>
            @endif
            <svg class="w-4 h-4 text-text-muted transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </div>
    </div>

    <!-- Dropdown Menu (Strictly opens downward) -->
    <div 
        x-show="open" 
        x-cloak 
        x-transition:enter="transition ease-out duration-150 transform"
        x-transition:enter-start="opacity-0 translate-y-[-6px]"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100 transform"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-[-6px]"
        class="absolute top-full left-0 right-0 z-50 mt-1 bg-surface border border-border-subtle rounded-xl shadow-xl overflow-hidden"
    >
        <div class="max-h-[185px] overflow-y-auto divide-y divide-border-subtle/50 custom-scrollbar">
            <template x-for="(opt, idx) in filteredOptions" :key="idx">
                <button 
                    type="button" 
                    @click.stop="select(opt)" 
                    class="w-full px-3 py-2 text-left text-sm text-text-main hover:bg-brand-light/30 transition-colors flex items-center justify-between cursor-pointer"
                >
                    <span x-text="(typeof opt === 'object' && opt !== null) ? opt[labelKey] : opt"></span>
                    <span x-show="value && (String((typeof opt === 'object' && opt !== null) ? opt[valueKey] : opt) === String(value))" class="text-primary font-bold">✓</span>
                </button>
            </template>

            <div x-show="filteredOptions.length === 0" class="p-3 text-center text-xs text-text-muted">
                No matches found
            </div>
        </div>
    </div>
</div>