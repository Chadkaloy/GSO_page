<script setup lang="ts">
import { AlertDialog, AlertDialogContent, AlertDialogHeader, AlertDialogTitle, AlertDialogDescription, AlertDialogFooter } from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { ref, watch } from 'vue';

const props = defineProps({
    open: Boolean, // Controls the visibility of the dialog
    title: {
        type: String,
        default: 'Are you sure?',
    },
    description: {
        type: String,
        default: 'This action cannot be undone.',
    },
});

const isOpen = ref(props.open);

watch(() => props.open, (newValue : any) => {
    isOpen.value = newValue;
});

const emit = defineEmits(['confirm', 'cancel']);

const confirm = () => {
    emit('confirm'); // Emit the confirm event
};

const cancel = () => {
    emit('cancel'); // Emit the cancel event
};
</script>

<template>
    <AlertDialog v-model:open="isOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ title }}</AlertDialogTitle>
                <AlertDialogDescription>{{ description }}</AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <Button variant="outline" @click="cancel">Cancel</Button>
                <Button variant="destructive" @click="confirm">Delete</Button>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>

