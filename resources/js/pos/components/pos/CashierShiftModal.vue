<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { usePosStore } from '../../stores/posStore';

const props = defineProps({ mode: { type: String, required: true } });
const emit = defineEmits(['close', 'saved']);
const store = usePosStore();
const cashInput = ref(null);
const submitting = ref(false);
const error = ref('');
const form = ref({ cash: props.mode === 'open' ? '' : '', notes: '' });
const isOpening = computed(() => props.mode === 'open');
const title = computed(() => isOpening.value ? 'Open Cashier Shift' : 'Close Shift and Generate EOD');
const label = computed(() => isOpening.value ? 'Opening cash in drawer' : 'Cash counted in drawer');

async function submit() {
    error.value = '';
    const cash = Number(form.value.cash);
    if (!Number.isFinite(cash) || cash < 0) {
        error.value = 'Enter a valid cash amount of zero or more.';
        return;
    }

    submitting.value = true;
    try {
        const url = isOpening.value
            ? '/pos/api/shifts/open'
            : `/pos/api/shifts/${store.bootstrap.active_shift.id}/close`;
        const payload = isOpening.value
            ? { opening_cash: cash, notes: form.value.notes || null }
            : { closing_cash: cash, notes: form.value.notes || null };
        const response = await window.axios.post(url, payload);
        emit('saved', response.data);
    } catch (requestError) {
        error.value = requestError.response?.data?.message
            ?? requestError.message
            ?? 'The cashier shift could not be saved.';
    } finally {
        submitting.value = false;
    }
}

onMounted(() => nextTick(() => cashInput.value?.focus()));
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/65 p-4">
        <form class="pos-card my-auto w-full max-w-lg rounded-[32px] p-5 sm:p-6" @submit.prevent="submit">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-[0.22em] text-[var(--pos-muted)]">Cashier Control</p>
                    <h2 class="mt-1 text-2xl font-bold text-white">{{ title }}</h2>
                    <p v-if="!isOpening" class="mt-2 text-sm text-[var(--pos-muted)]">Shift {{ store.bootstrap.active_shift?.shift_number }}. Closing creates a locked EOD report ready for A4 printing.</p>
                </div>
                <button type="button" class="pos-button-secondary min-h-11" :disabled="submitting" @click="emit('close')">Close</button>
            </div>

            <label class="mt-6 block text-sm font-medium text-[var(--pos-paper)]">
                {{ label }}
                <input ref="cashInput" v-model="form.cash" class="pos-input mt-2 text-xl font-bold tabular-nums" inputmode="decimal" placeholder="0.00" aria-label="Cash amount">
            </label>
            <label class="mt-4 block text-sm font-medium text-[var(--pos-paper)]">
                Notes (optional)
                <textarea v-model="form.notes" class="pos-input mt-2 min-h-24" :placeholder="isOpening ? 'Float or handover note' : 'Explain any cash difference'" />
            </label>
            <p v-if="error" class="mt-4 rounded-2xl bg-rose-400/10 p-3 text-sm text-rose-100">{{ error }}</p>
            <button class="pos-button-primary mt-6 min-h-12 w-full" :disabled="submitting" type="submit">
                {{ submitting ? 'Saving...' : (isOpening ? 'Open Shift' : 'Close Shift & Open EOD Report') }}
            </button>
        </form>
    </div>
</template>
