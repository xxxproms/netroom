<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CornerDownLeft, Search } from '@lucide/vue';
import { onKeyStroke, useDebounceFn } from '@vueuse/core';
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Dialog, DialogContent } from '@/components/ui/dialog';

const { t } = useI18n();

type Item = { title: string; subtitle: string | null; url: string };
type Group = { key: string; items: Item[] };

const open = ref(false);
const query = ref('');
const groups = ref<Group[]>([]);
const loading = ref(false);
const activeIndex = ref(0);
const inputRef = ref<HTMLInputElement | null>(null);

/** One flat list across every group, for arrow-key navigation. */
const flat = computed(() => groups.value.flatMap((group) => group.items));

const run = useDebounceFn(async (term: string) => {
    if (term.trim().length < 2) {
        groups.value = [];
        loading.value = false;

        return;
    }

    loading.value = true;

    try {
        const response = await fetch(`/search?q=${encodeURIComponent(term)}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        const data = (await response.json()) as { groups: Group[] };
        groups.value = data.groups ?? [];
        activeIndex.value = 0;
    } finally {
        loading.value = false;
    }
}, 200);

watch(query, (value) => run(value));

watch(open, async (isOpen) => {
    if (!isOpen) {
        return;
    }

    query.value = '';
    groups.value = [];
    activeIndex.value = 0;
    await nextTick();
    inputRef.value?.focus();
});

/** The running position of an item within the flattened list. */
function positionOf(groupIndex: number, itemIndex: number): number {
    let base = 0;

    for (let i = 0; i < groupIndex; i++) {
        base += groups.value[i].items.length;
    }

    return base + itemIndex;
}

function move(delta: number): void {
    const count = flat.value.length;

    if (count === 0) {
        return;
    }

    activeIndex.value = (activeIndex.value + delta + count) % count;
}

function select(item: Item): void {
    open.value = false;
    router.visit(item.url);
}

function onEnter(): void {
    const item = flat.value[activeIndex.value];

    if (item) {
        select(item);
    }
}

onKeyStroke(['k', 'K'], (event) => {
    if (event.metaKey || event.ctrlKey) {
        event.preventDefault();
        open.value = true;
    }
});
</script>

<template>
    <button
        type="button"
        class="flex h-9 items-center gap-2 rounded-md border border-input bg-transparent px-3 text-sm text-muted-foreground shadow-xs transition-colors hover:bg-accent/40"
        @click="open = true"
    >
        <Search class="size-4" />
        <span class="hidden sm:inline">{{ t('search.trigger') }}</span>
        <kbd
            class="ml-2 hidden rounded border bg-muted px-1.5 font-mono text-[10px] sm:inline"
        >
            Ctrl K
        </kbd>
    </button>

    <Dialog v-model:open="open">
        <DialogContent
            class="top-[15%] max-w-xl translate-y-0 gap-0 overflow-hidden p-0"
            :show-close-button="false"
        >
            <div class="flex items-center gap-2 border-b px-4">
                <Search class="size-4 shrink-0 text-muted-foreground" />
                <input
                    ref="inputRef"
                    v-model="query"
                    :placeholder="t('search.placeholder')"
                    class="h-12 w-full bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                    @keydown.down.prevent="move(1)"
                    @keydown.up.prevent="move(-1)"
                    @keydown.enter.prevent="onEnter"
                />
            </div>

            <div class="max-h-[60vh] overflow-y-auto p-2">
                <p
                    v-if="query.trim().length < 2"
                    class="px-2 py-6 text-center text-sm text-muted-foreground"
                >
                    {{ t('search.hint') }}
                </p>
                <p
                    v-else-if="!loading && !flat.length"
                    class="px-2 py-6 text-center text-sm text-muted-foreground"
                >
                    {{ t('search.empty') }}
                </p>

                <div
                    v-for="(group, groupIndex) in groups"
                    :key="group.key"
                    class="mb-2 last:mb-0"
                >
                    <p class="px-2 py-1 text-xs font-medium text-muted-foreground">
                        {{ t(`search.group.${group.key}`) }}
                    </p>
                    <button
                        v-for="(item, itemIndex) in group.items"
                        :key="item.url"
                        type="button"
                        class="flex w-full items-center justify-between gap-3 rounded-md px-2 py-2 text-left text-sm transition-colors"
                        :class="
                            positionOf(groupIndex, itemIndex) === activeIndex
                                ? 'bg-accent'
                                : 'hover:bg-accent/50'
                        "
                        @click="select(item)"
                        @mousemove="activeIndex = positionOf(groupIndex, itemIndex)"
                    >
                        <span class="min-w-0">
                            <span class="font-medium">{{ item.title }}</span>
                            <span
                                v-if="item.subtitle"
                                class="ml-2 text-muted-foreground"
                            >
                                {{ item.subtitle }}
                            </span>
                        </span>
                        <CornerDownLeft
                            v-if="positionOf(groupIndex, itemIndex) === activeIndex"
                            class="size-3.5 shrink-0 text-muted-foreground"
                        />
                    </button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
