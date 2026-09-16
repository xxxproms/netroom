<script setup lang="ts">
import { Filter, Route, Search, X } from '@lucide/vue';
import { nextTick, onBeforeUnmount, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import type { FacetGroup, useMapFocus } from '@/composables/useMapFocus';

/**
 * Reading controls, as opposed to the editing ones in the toolbar: find a node
 * by name, narrow the map to one kind of kit, follow what a device is wired to.
 * Everyone gets these, including someone who may not rearrange a thing.
 *
 * It sits in the page header beside the other reading controls rather than on
 * the canvas — three overlays along one edge collide the moment the window is
 * anything short of wide.
 */

const { t } = useI18n();

const props = defineProps<{
    focus: ReturnType<typeof useMapFocus>;
    groups: FacetGroup[];
}>();

const { focus } = props;

// Only one map renders at a time, so a fixed id beats plumbing a template ref
// through the Input wrapper just to put the caret in the box.
async function openSearch(): Promise<void> {
    focus.searching.value = true;
    await nextTick();
    document.getElementById('map-search')?.focus();
}

// Ctrl+K already belongs to the search across the whole panel, so the diagram
// takes the other worn-in shortcut: "/" opens the box, Escape puts it away.
function onKeydown(event: KeyboardEvent): void {
    const target = event.target as HTMLElement | null;
    const typing =
        target !== null &&
        (target.isContentEditable ||
            ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));

    if (event.key === '/' && !typing) {
        event.preventDefault();
        void openSearch();

        return;
    }

    if (event.key === 'Escape' && focus.searching.value) {
        focus.searching.value = false;
        focus.query.value = '';
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

function onEnter(): void {
    const first = focus.matches.value[0];

    if (first) {
        focus.focus(first.id);
        focus.select(first.id);
    }
}
</script>

<template>
    <div class="map-viewbar relative">
        <div class="flex items-center gap-1 rounded-lg border bg-card p-1">
            <template v-if="focus.searching.value">
                <Input
                    id="map-search"
                    v-model="focus.query.value"
                    class="h-8 w-56 border-0 shadow-none focus-visible:ring-0"
                    :placeholder="t('map.searchHint')"
                    @keydown.enter.prevent="onEnter"
                />
                <Button
                    size="icon"
                    variant="ghost"
                    class="size-8"
                    :title="t('common.cancel')"
                    @click="
                        focus.searching.value = false;
                        focus.query.value = '';
                    "
                >
                    <X class="size-4" />
                </Button>
            </template>

            <Button
                v-else
                size="sm"
                variant="ghost"
                class="h-8 gap-2 px-2"
                :title="t('map.searchShortcut')"
                @click="openSearch"
            >
                <Search class="size-4" />
                {{ t('map.search') }}
            </Button>

            <span class="mx-0.5 h-5 w-px bg-border" />

            <DropdownMenu v-if="groups.length">
                <DropdownMenuTrigger as-child>
                    <Button
                        size="sm"
                        :variant="focus.filterCount.value ? 'default' : 'ghost'"
                        class="h-8 gap-2 px-2"
                        :title="t('map.filters')"
                    >
                        <Filter class="size-4" />
                        {{ focus.filterCount.value || t('map.filters') }}
                    </Button>
                </DropdownMenuTrigger>

                <DropdownMenuContent align="center" class="w-56">
                    <template v-for="(group, index) in groups" :key="group.key">
                        <DropdownMenuSeparator v-if="index > 0" />
                        <DropdownMenuLabel>{{ group.label }}</DropdownMenuLabel>
                        <DropdownMenuCheckboxItem
                            v-for="option in group.options"
                            :key="option.value"
                            :model-value="
                                focus.isFiltered(group.key, option.value)
                            "
                            @select="(event: Event) => event.preventDefault()"
                            @update:model-value="
                                focus.toggleFilter(group.key, option.value)
                            "
                        >
                            {{ option.label }}
                        </DropdownMenuCheckboxItem>
                    </template>

                    <template v-if="focus.filterCount.value">
                        <DropdownMenuSeparator />
                        <DropdownMenuCheckboxItem
                            :model-value="false"
                            @update:model-value="focus.clearFilters()"
                        >
                            {{ t('map.filterClear') }}
                        </DropdownMenuCheckboxItem>
                    </template>
                </DropdownMenuContent>
            </DropdownMenu>

            <Button
                size="sm"
                :variant="focus.tracing.value ? 'default' : 'ghost'"
                class="h-8 gap-2 px-2"
                :title="t('map.traceHint')"
                @click="focus.tracing.value = !focus.tracing.value"
            >
                <Route class="size-4" />
                {{ t('map.trace') }}
            </Button>
        </div>

        <ul
            v-if="focus.searching.value && focus.query.value.trim()"
            class="absolute top-full right-0 z-30 mt-1 w-72 overflow-hidden rounded-lg border bg-card text-sm shadow-md"
        >
            <li
                v-if="!focus.matches.value.length"
                class="px-3 py-2 text-muted-foreground"
            >
                {{ t('map.searchEmpty') }}
            </li>
            <li v-for="match in focus.matches.value" :key="match.id">
                <button
                    type="button"
                    class="flex w-full flex-col items-start px-3 py-1.5 text-left hover:bg-accent"
                    @click="
                        focus.focus(match.id);
                        focus.select(match.id);
                    "
                >
                    <span class="font-medium text-foreground">
                        {{ match.label }}
                    </span>
                    <span
                        v-if="match.sub"
                        class="text-xs text-muted-foreground"
                    >
                        {{ match.sub }}
                    </span>
                </button>
            </li>
        </ul>
    </div>
</template>
