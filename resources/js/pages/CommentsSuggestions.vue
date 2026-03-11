<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import gsap from 'gsap';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import commentsRoutes from '@/routes/comments';

type Comment = {
    id: number;
    name?: string | null;
    email?: string | null;
    message: string;
    is_public: boolean;
    created_at: string;
};

type PageProps = {
    comments: Comment[];
    filters?: {
        from?: string | null;
        to?: string | null;
    };
};

const props = defineProps<PageProps>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Comments & suggestions',
        href: '/comments',
    },
];

const comments = computed(() => props.comments ?? []);

const listRef = ref<HTMLDivElement | null>(null);
const editingId = ref<number | null>(null);
const editMessage = ref('');
const editIsPublic = ref(true);
const saving = ref(false);
const from = ref(props.filters?.from ?? '');
const to = ref(props.filters?.to ?? '');

function startEdit(comment: Comment) {
    editingId.value = comment.id;
    editMessage.value = comment.message;
    editIsPublic.value = comment.is_public;
}

function cancelEdit() {
    editingId.value = null;
    editMessage.value = '';
}

function saveEdit(comment: Comment) {
    if (!editingId.value) return;
    saving.value = true;

    router.put(
        commentsRoutes.update.url({ comment: comment.id }),
        {
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

function remove(comment: Comment) {
    if (!confirm('Delete this comment?')) return;

    router.delete(commentsRoutes.destroy.url({ comment: comment.id }), {
        preserveScroll: true,
    });
}

function applyFilter() {
    router.get(
        commentsRoutes.index.url({
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

onMounted(() => {
    if (!listRef.value) return;
    const cards = listRef.value.querySelectorAll<HTMLElement>('[data-comment-card]');
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
    <Head title="Comments & Suggestions" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
            <div
                class="rounded-xl border border-sidebar-border/70 bg-gradient-to-br from-primary/10 via-background to-background p-4 shadow-sm dark:border-sidebar-border"
            >
                <h1 class="text-lg font-semibold text-foreground">
                    Comments & suggestions
                </h1>
                <p class="mt-1 text-xs text-muted-foreground">
                    Feedback submitted from the public landing page will appear
                    here. You can review, lightly edit, or remove entries.
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
                    v-if="comments.length === 0"
                    data-comment-card
                    class="flex flex-col justify-center rounded-xl border border-dashed border-sidebar-border/70 bg-muted/40 p-6 text-xs text-muted-foreground dark:border-sidebar-border"
                >
                    No comments yet. Once guests submit feedback from the
                    welcome page, it will appear here.
                </div>

                <article
                    v-for="comment in comments"
                    :key="comment.id"
                    data-comment-card
                    class="flex flex-col justify-between rounded-xl border border-sidebar-border/70 bg-background/80 p-4 text-sm shadow-sm backdrop-blur dark:border-sidebar-border"
                >
                    <div class="space-y-2">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                {{ comment.name || 'Anonymous' }}
                            </p>
                            <p
                                v-if="comment.email"
                                class="text-[11px] text-muted-foreground/80"
                            >
                                {{ comment.email }}
                            </p>
                            <p class="mt-1 text-[11px] text-muted-foreground/80">
                                {{ new Date(comment.created_at).toLocaleString() }}
                            </p>
                        </div>

                        <div v-if="editingId === comment.id" class="space-y-2">
                            <textarea
                                v-model="editMessage"
                                rows="4"
                                class="w-full rounded-md border border-input bg-background px-2 py-1.5 text-xs shadow-sm outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/40"
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
                            {{ comment.message }}
                        </p>
                    </div>

                    <div class="mt-3 flex items-center justify-between gap-2">
                        <span
                            class="rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            {{ comment.is_public ? 'Public' : 'Hidden' }}
                        </span>

                        <div class="flex gap-2">
                            <Button
                                v-if="editingId === comment.id"
                                size="sm"
                                variant="outline"
                                :disabled="saving"
                                @click="saveEdit(comment)"
                            >
                                Save
                            </Button>
                            <Button
                                v-if="editingId === comment.id"
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
                                @click="startEdit(comment)"
                            >
                                Edit
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                class="text-destructive hover:bg-destructive/10"
                                @click="remove(comment)"
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

