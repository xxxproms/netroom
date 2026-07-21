<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { MapPin, Router } from '@lucide/vue';
import { computed } from 'vue';
import { show as showDevice } from '@/routes/devices';
import { show as showWorkplace } from '@/routes/workplaces';
import type { CableEnd } from '@/types';

const props = defineProps<{
    end: CableEnd;
    linked?: boolean;
}>();

const href = computed(() =>
    props.end.kind === 'port'
        ? showDevice(props.end.device.id).url
        : showWorkplace(props.end.workplace.id).url,
);

/** "SW-N-01 · 12" or "Каб. 204 · розетка 204-1". */
const title = computed(() =>
    props.end.kind === 'port'
        ? props.end.device.name
        : props.end.workplace.name,
);

const detail = computed(() =>
    props.end.kind === 'port' ? props.end.name : props.end.label,
);

const note = computed(() =>
    props.end.kind === 'port'
        ? props.end.description
        : (props.end.workplace.person ?? props.end.workplace.room),
);
</script>

<template>
    <span class="flex items-center gap-2">
        <component
            :is="end.kind === 'port' ? Router : MapPin"
            class="size-4 shrink-0 text-muted-foreground"
        />

        <span class="flex min-w-0 flex-col leading-tight">
            <span class="flex items-center gap-1.5">
                <component
                    :is="linked === false ? 'span' : Link"
                    :href="linked === false ? undefined : href"
                    class="truncate font-medium"
                    :class="{ 'hover:underline': linked !== false }"
                >
                    {{ title }}
                </component>
                <span class="shrink-0 font-mono text-sm text-muted-foreground">
                    · {{ detail }}
                </span>
            </span>
            <span v-if="note" class="truncate text-sm text-muted-foreground">
                {{ note }}
            </span>
        </span>
    </span>
</template>
