<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import gsap from 'gsap';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import ratingsRoutes from '@/routes/ratings';

type Rating = {
    id: number;
    name?: string | null;
    email?: string | null;
    score: number;
    message?: string | null;
    is_public: boolean;
    created_at: string;
};

type PageProps = {
    ratings: Rating[];
    filters?: {
        from?: string | null;
        to?: string | null;
    };
};

const props = defineProps<PageProps>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Ratings',
        href: '/ratings',
    },
];

const ratings = computed(() => props.ratings ?? []);

const listRef = ref<HTMLDivElement | null>(null);
const editingId = ref<number | null>(null);
const editScore = ref(5);
const editMessage = ref('');
const editIsPublic = ref(true);
const saving = ref(false);
const from = ref(props.filters?.from ?? '');
const to = ref(props.filters?.to ?? '');

function startEdit(rating: Rating) {
    editingId.value = rating.id;
    editScore.value = rating.score;
    editMessage.value = rating.message ?? '';
    editIsPublic.value = rating.is_public;
}

function cancelEdit() {
    editingId.value = null;
    editMessage.value = '';
}

function setEditScore(value: number) {
    editScore.value = value;
}

function saveEdit(rating: Rating) {
    if (!editingId.value) return;
    saving.value = true;

    router.put(
        ratingsRoutes.update.url({ rating: rating.id }),
        {
            score: editScore.value,
            message: editMessage.value,
            is_public: editIsPublic.value,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                saving.value = false;
                editingId.value = null;
            },
        },
    );
}

function remove(rating: Rating) {
    if (!confirm('Delete this rating?')) return;

    router.delete(ratingsRoutes.destroy.url({ rating: rating.id }), {
        preserveScroll: true,
    });
}

function applyFilter() {
    router.get(
        ratingsRoutes.index.url({
            query: {
                from: from.value || undefined,
                to: to.value || undefined,
            },
        }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}

function starsArray(count: number) {
    return Array.from({ length: 5 }, (_, i) => i < count);
}

onMounted(() => {
    if (!listRef.value) return;
    const cards = listRef.value.querySelectorAll<HTMLElement>('[data-rating-card]');
    gsap.from(cards, {
        opacity: 0,
        y: 24,
        duration: 0.7,
        stagger: 0.06,
        ease: 'power3.out',
    });
});
</script>

<template>
    <Head title="Ratings" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
            <div
                class="rounded-xl border border-sidebar-border/70 bg-gradient-to-br from-primary/10 via-background to-background p-4 shadow-sm dark:border-sidebar-border"
            >
                <h1 class="text-lg font-semibold text-foreground">
                    Ratings
                </h1>
                <p class="mt-1 text-xs text-muted-foreground">
                    See how users rate the attendance experience. You can filter
                    by date, edit scores, or hide entries.
                </p>
                <div class="mt-4 flex flex-wrap items-end gap-3 text-xs">
                    <div class="space-y-1">
                        <label class="block text-[11px] font-medium text-muted-foreground">
                            From
                        </label>
                        <Input v-model="from" type="date" class="h-8 text-xs" />
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[11px] font-medium text-muted-foreground">
                            To
                        </label>
                        <Input v-model="to" type="date" class="h-8 text-xs" />
                    </div>
                    <Button
                        size="sm"
                        class="mt-5"
                        variant="outline"
                        @click="applyFilter"
                    >
                        Filter
                    </Button>
                </div>
            </div>

            <div
                ref="listRef"
                class="grid gap-3 md:grid-cols-2 xl:grid-cols-3"
            >
                <div
                    v-if="ratings.length === 0"
                    data-rating-card
                    class="flex flex-col justify-center rounded-xl border border-dashed border-sidebar-border/70 bg-muted/40 p-6 text-xs text-muted-foreground dark:border-sidebar-border"
                >
                    No ratings yet. Once guests rate from the welcome page, they
                    will appear here.
                </div>

                <article
                    v-for="rating in ratings"
                    :key="rating.id"
                    data-rating-card
                    class="flex flex-col justify-between rounded-xl border border-sidebar-border/70 bg-background/80 p-4 text-sm shadow-sm backdrop-blur dark:border-sidebar-border"
                >
                    <div class="space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    {{ rating.name || 'Anonymous' }}
                                </p>
                                <p
                                    v-if="rating.email"
                                    class="text-[11px] text-muted-foreground/80"
                                >
                                    {{ rating.email }}
                                </p>
                                <p class="mt-1 text-[11px] text-muted-foreground/80">
                                    {{ new Date(rating.created_at).toLocaleString() }}
                                </p>
                            </div>
                            <div class="flex items-center gap-0.5">
                                <template
                                    v-if="editingId === rating.id"
                                >
                                    <button
                                        v-for="i in 5"
                                        :key="i"
                                        type="button"
                                        class="p-0.5"
                                        @click="setEditScore(i)"
                                    >
                                        <span
                                            class="text-lg"
                                            :class="i <= editScore ? 'text-yellow-400' : 'text-muted-foreground/40'"
                                        >
                                            ★
                                        </span>
                                    </button>
                                </template>
                                <template v-else>
                                    <span
                                        v-for="(filled, i) in starsArray(rating.score)"
                                        :key="i"
                                        class="text-lg"
                                        :class="filled ? 'text-yellow-400' : 'text-muted-foreground/40'"
                                    >
                                        ★
                                    </span>
                                </template>
                            </div>
                        </div>

                        <div v-if="editingId === rating.id" class="space-y-2">
                            <textarea
                                v-model="editMessage"
                                rows="4"
                                class="w-full rounded-md border border-input bg-background px-2 py-1.5 text-xs shadow-sm outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/40"
                                placeholder="Optional notes about this rating…"
                            />
                            <label class="flex items-center gap-2 text-[11px] text-muted-foreground">
                                <input
                                    v-model="editIsPublic"
                                    type="checkbox"
                                    class="h-3 w-3 rounded border-input bg-background text-primary focus-visible:ring-1 focus-visible:ring-ring"
                                />
                                Visible in lists
                            </label>
                        </div>
                        <p
                            v-else
                            class="whitespace-pre-wrap text-xs leading-relaxed text-foreground"
                        >
                            {{ rating.message || 'No comment' }}
                        </p>
                    </div>

                    <div class="mt-3 flex items-center justify-between gap-2">
                        <span
                            class="rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            {{ rating.is_public ? 'Public' : 'Hidden' }}
                        </span>

                        <div class="flex gap-2">
                            <Button
                                v-if="editingId === rating.id"
                                size="sm"
                                variant="outline"
                                :disabled="saving"
                                @click="saveEdit(rating)"
                            >
                                Save
                            </Button>
                            <Button
                                v-if="editingId === rating.id"
                                size="sm"
                                variant="ghost"
                                @click="cancelEdit"
                            >
                                Cancel
                            </Button>
                            <Button
                                v-else
                                size="sm"
                                variant="outline"
                                @click="startEdit(rating)"
                            >
                                Edit
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                class="text-destructive hover:bg-destructive/10"
                                @click="remove(rating)"
                            >
                                Delete
                            </Button>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </AppLayout>
</template>

