<script setup lang="ts">
/**
 * Plain text input with a live suggestions dropdown backed by the
 * /locations/search endpoint. Used for Trip Ticket Destination, Employee
 * Account Address, and Property Return Slip LGU Name — any field where
 * previously-typed values should be remembered and offered back as
 * suggestions (the actual "remembering" happens server-side via
 * LocationController::remember() after a successful save, not here).
 *
 * Deliberately NOT a strict combobox — the person can always type a brand
 * new value freely; suggestions are just a convenience, never a constraint.
 */
import { ref, watch } from 'vue';
import axios from 'axios';
import { Input } from '@/components/ui/input';

const props = defineProps<{
    modelValue: string;
    placeholder?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const suggestions = ref<string[]>([]);
const showSuggestions = ref(false);
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

const fetchSuggestions = async (query: string) => {
    try {
        const response = await axios.get('/locations/search', { params: { q: query } });
        suggestions.value = response.data;
    } catch (error) {
        // Non-fatal: suggestions are a convenience, not a required feature.
        // Silently fail rather than interrupt typing with an error toast.
        console.error('Failed to fetch location suggestions:', error);
        suggestions.value = [];
    }
};

const handleInput = (event: Event) => {
    const value = (event.target as HTMLInputElement).value;
    emit('update:modelValue', value);

    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        fetchSuggestions(value);
    }, 250);

    showSuggestions.value = true;
};

const selectSuggestion = (value: string) => {
    emit('update:modelValue', value);
    showSuggestions.value = false;
};

const handleFocus = () => {
    fetchSuggestions(props.modelValue);
    showSuggestions.value = true;
};

const handleBlur = () => {
    // Delay closing so a click on a suggestion registers before the
    // dropdown unmounts (blur fires before click otherwise).
    setTimeout(() => {
        showSuggestions.value = false;
    }, 150);
};
</script>

<template>
    <div class="relative">
        <Input
            :model-value="modelValue"
            :placeholder="placeholder"
            @input="handleInput"
            @focus="handleFocus"
            @blur="handleBlur"
            autocomplete="off"
        />
        <div
            v-if="showSuggestions && suggestions.length > 0"
            class="absolute z-50 mt-1 w-full rounded-md border bg-popover shadow-md max-h-48 overflow-y-auto"
        >
            <button
                v-for="suggestion in suggestions"
                :key="suggestion"
                type="button"
                class="w-full text-left px-3 py-1.5 text-sm hover:bg-muted"
                @mousedown.prevent="selectSuggestion(suggestion)"
            >
                {{ suggestion }}
            </button>
        </div>
    </div>
</template>