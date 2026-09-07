<script setup lang="ts">
/* Import Components */
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

/* Import Utilities */
import axios from 'axios';
import { Save } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';

import { BreadcrumbItem } from '@/types';

const baseentityurl = '/TripTicketSignatory';
const baseentityname = 'Trip Ticket Signatories';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: baseentityname,
        href: baseentityurl,
    },
];

const loading = ref(true);
const saving = ref(false);

const municipalAdministratorName = ref('');
const municipalMayorName = ref('');

const fetchSettings = async () => {
    loading.value = true;
    try {
        const response = await axios.get(`${baseentityurl}/current`);
        municipalAdministratorName.value = response.data.municipal_administrator_name ?? '';
        municipalMayorName.value = response.data.municipal_mayor_name ?? '';
    } catch (error) {
        console.error(error);
        toast.error('Failed to load signatory settings.');
    } finally {
        loading.value = false;
    }
};

const onSave = async () => {
    saving.value = true;
    try {
        await axios.put(baseentityurl, {
            municipal_administrator_name: municipalAdministratorName.value || null,
            municipal_mayor_name: municipalMayorName.value || null,
        });
        toast.success('Signatory settings saved.');
    } catch (error: any) {
        if (error.response && error.response.status === 422) {
            const errors = error.response.data.errors || {};
            const firstError = Object.values(errors)[0] as string[] | undefined;
            toast.error(firstError?.[0] ?? 'Validation failed. Please check the fields.');
        } else {
            console.error(error);
            toast.error('An unexpected error occurred while saving.');
        }
    } finally {
        saving.value = false;
    }
};

onMounted(fetchSettings);
</script>

<template>
    <Head :title="baseentityname" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-2 rounded-xl p-4">
            <div class="max-w-lg space-y-1">
                <h1 class="text-lg font-semibold">{{ baseentityname }}</h1>
                <p class="text-sm text-muted-foreground">
                    These names are used to pre-fill the "Authorized By" and "Approved By" signature
                    lines on the printed Trip Ticket. Leave a field blank to keep that line blank on print.
                </p>
            </div>

            <div v-if="loading" class="max-w-lg py-6 text-sm text-muted-foreground">
                Loading current settings…
            </div>

            <form v-else class="max-w-lg space-y-6 pt-4" @submit.prevent="onSave">
                <div class="space-y-2">
                    <Label>Municipal Administrator Name</Label>
                    <Input
                        v-model="municipalAdministratorName"
                        placeholder="e.g., Juan Dela Cruz"
                    />
                </div>

                <div class="space-y-2">
                    <Label>Municipal Mayor Name</Label>
                    <Input
                        v-model="municipalMayorName"
                        placeholder="e.g., Maria Santos"
                    />
                </div>

                <Button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white" :disabled="saving">
                    <Save class="h-4 w-4 mr-1" />
                    {{ saving ? 'Saving…' : 'Save Settings' }}
                </Button>
            </form>
        </div>
    </AppLayout>
</template>
