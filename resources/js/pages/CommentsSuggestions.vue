<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import gsap from 'gsap';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
    DialogClose,
} from '@/components/ui/dialog';
import commentsRoutes from '@/routes/comments';
import { Filter, Calendar, X } from 'lucide-vue-next';

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
const filterModalOpen = ref(false);

function clearFilters() {
    from.value = '';
    to.value = '';
    applyFilter();
    filterModalOpen.value = false;
}

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
            onSuccess: () => {
                filterModalOpen.value = false;
            }
        },
    );
}

onMounted(() => {
    // 1. Initial Page Heading Animation with 3D
    const header = document.querySelector('.rounded-xl.border.bg-gradient-to-br');
    if (header) {
        gsap.set(header.parentElement, { perspective: 1000 });
        gsap.from(header, {
            opacity: 0,
            y: -30,
            rotationX: 20,
            z: -50,
            duration: 1,
            ease: 'power3.out'
        });
    }

    if (!listRef.value) return;
    const cards = listRef.value.querySelectorAll<HTMLElement>('[data-comment-card]');
    
    // Wrap cards list with perspective
    gsap.set(listRef.value, { perspective: 1000 });
    
    // 2. Staggered 3D Entry for Comment Cards
    gsap.from(cards, {
        opacity: 0,
        y: 50,
        rotationX: -30,
        z: -100,
        duration: 1,
        stagger: 0.1,
        ease: 'back.out(1.2)',
    });

    // 3. 3D Hover Interactions
    cards.forEach((card) => {
        gsap.set(card, { transformStyle: "preserve-3d" });

        card.addEventListener('mousemove', (e: MouseEvent) => {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            
            const rotateX = ((y - centerY) / centerY) * -8;
            const rotateY = ((x - centerX) / centerX) * 8;
            
            gsap.to(card, {
                rotationX: rotateX,
                rotationY: rotateY,
                scale: 1.03,
                z: 20,
                zIndex: 50,
                boxShadow: '0 25px 30px -10px rgba(var(--primary-rgb), 0.15), 0 10px 10px -5px rgba(0, 0, 0, 0.1)',
                borderColor: 'rgba(var(--primary), 0.5)',
                duration: 0.4,
                ease: 'power3.out'
            });
        });

        card.addEventListener('mouseleave', () => {
            gsap.to(card, {
                rotationX: 0,
                rotationY: 0,
                scale: 1,
                z: 0,
                zIndex: 0,
                boxShadow: '0 1px 2px 0 rgba(0, 0, 0, 0.05)',
                borderColor: 'inherit',
                duration: 0.6,
                ease: 'elastic.out(1, 0.4)'
            });
        });
    });

    // 4. Button Press Micro-interactions with 3D Depth
    const buttons = document.querySelectorAll('button');
    buttons.forEach((btn) => {
        gsap.set(btn, { transformStyle: "preserve-3d" });
        btn.addEventListener('mousedown', () => {
            gsap.to(btn, { scale: 0.95, z: -5, duration: 0.1, ease: 'power1.out' });
        });
        btn.addEventListener('mouseup', () => {
            gsap.to(btn, { scale: 1, z: 0, duration: 0.3, ease: 'bounce.out' });
        });
        btn.addEventListener('mouseleave', () => {
            gsap.to(btn, { scale: 1, z: 0, duration: 0.3, ease: 'power1.out' });
        });
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
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-9 gap-2"
                            @click="filterModalOpen = true"
                        >
                            <Filter class="h-4 w-4" />
                            Filter by Date
                        </Button>

                        <div v-if="from || to" class="flex items-center gap-2 rounded-lg border bg-muted/50 px-3 py-1.5 text-[11px] font-medium animate-in fade-in zoom-in duration-300">
                            <Calendar class="h-3.5 w-3.5 text-muted-foreground" />
                            <span>
                                {{ from || 'Any' }} — {{ to || 'Any' }}
                            </span>
                            <button
                                class="ml-1 rounded-full p-0.5 hover:bg-muted"
                                @click="clearFilters"
                            >
                                <X class="h-3 w-3" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Modal -->
            <Dialog v-model:open="filterModalOpen">
                <DialogContent class="max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Filter by Date</DialogTitle>
                    </DialogHeader>
                    <div class="space-y-4 py-2">
                        <div class="grid gap-4">
                            <div class="space-y-2">
                                <label class="text-xs font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                                    From
                                </label>
                                <Input v-model="from" type="date" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                                    To
                                </label>
                                <Input v-model="to" type="date" />
                            </div>
                        </div>
                    </div>
                    <DialogFooter class="flex flex-row justify-between sm:justify-between items-center gap-2 pt-2">
                        <Button
                            variant="ghost"
                            size="sm"
                            class="text-xs text-muted-foreground hover:text-foreground"
                            @click="clearFilters"
                        >
                            Clear filters
                        </Button>
                        <div class="flex gap-2">
                            <DialogClose as-child>
                                <Button variant="outline" size="sm">Cancel</Button>
                            </DialogClose>
                            <Button size="sm" @click="applyFilter">Apply Filter</Button>
                        </div>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <div
                v-if="comments.length === 0"
                class="flex w-full items-center justify-center rounded-2xl border border-dashed border-sidebar-border/70 bg-muted/30 p-12 text-center text-sm text-muted-foreground shadow-sm backdrop-blur-sm dark:border-sidebar-border"
            >
                <div class="max-w-[300px] space-y-2">
                    <p class="font-medium text-foreground">No comments yet</p>
                    <p>Once guests submit feedback from the welcome page, it will appear here.</p>
                </div>
            </div>

            <div
                v-else
                ref="listRef"
                class="columns-1 md:columns-2 xl:columns-3 gap-4 space-y-4"
            >

                <article
                    v-for="comment in comments"
                    :key="comment.id"
                    data-comment-card
                    class="break-inside-avoid mb-4 flex flex-col justify-between rounded-xl border border-sidebar-border/70 bg-background/80 p-4 text-sm shadow-sm backdrop-blur dark:border-sidebar-border"
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

