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
    const header = document.querySelector('.bg-white.dark\\:bg-black');
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

    // Hover interactions removed as per request

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
            <div class="rounded-[2rem] border border-sidebar-border/50 bg-background/50 backdrop-blur-xl p-8 shadow-2xl relative overflow-hidden group">
                <div class="absolute -right-16 -top-16 w-64 h-64 bg-primary/5 rounded-full blur-3xl pointer-events-none group-hover:bg-primary/10 transition-colors duration-700"></div>
                <h1 class="text-2xl font-serif font-bold text-foreground tracking-tight">
                    Comments & Suggestions
                </h1>
                <p class="mt-2 text-sm text-muted-foreground/80 font-light max-w-2xl leading-relaxed">
                    Review and curate the feedback submitted from the presence gateway. Each entry represents a unique interaction with the system.
                </p>
                <div class="mt-8 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-10 px-5 rounded-full border-sidebar-border/50 bg-background/50 backdrop-blur-sm hover:bg-muted/50 transition-all gap-2 text-xs font-semibold tracking-wide"
                            @click="filterModalOpen = true"
                        >
                            <Filter class="h-3.5 w-3.5" />
                            Filter Timeline
                        </Button>

                        <div v-if="from || to" class="flex items-center gap-2 rounded-full border border-sidebar-border bg-muted/30 px-4 py-2 text-[11px] font-medium tracking-wide animate-in fade-in zoom-in duration-500">
                            <Calendar class="h-3.5 w-3.5 text-primary" />
                            <span class="text-foreground">
                                {{ from || 'Initial' }} — {{ to || 'Latest' }}
                            </span>
                            <button
                                class="ml-1 rounded-full p-1 hover:bg-muted/80 transition-colors"
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
                class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6"
            >

                <article
                    v-for="comment in comments"
                    :key="comment.id"
                    data-comment-card
                    class="group relative flex flex-col rounded-[2rem] border border-sidebar-border/40 bg-background/40 backdrop-blur-xl p-6 shadow-lg transition-all duration-500 hover:shadow-2xl hover:-translate-y-1.5 hover:border-sidebar-border/80 hover:bg-background/60 overflow-hidden"
                >
                    <!-- Abstract Background Element -->
                    <div class="absolute -right-6 -top-6 w-24 h-24 bg-primary/5 rounded-full blur-2xl group-hover:bg-primary/10 transition-colors pointer-events-none"></div>
                    
                    <div class="relative z-10 flex-1 flex flex-col gap-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center border border-primary/20 group-hover:bg-primary/20 transition-colors">
                                    <span class="text-xs font-bold text-primary">{{ (comment.name || 'A').charAt(0).toUpperCase() }}</span>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-sm font-serif font-bold text-foreground leading-tight truncate">
                                        {{ comment.name || 'Anonymous' }}
                                    </h3>
                                    <p class="text-[10px] text-muted-foreground uppercase tracking-widest font-semibold mt-0.5">
                                        User Feedback
                                    </p>
                                </div>
                            </div>
                            <time class="text-[10px] text-muted-foreground/60 font-mono">
                                {{ new Date(comment.created_at).toLocaleDateString() }}
                            </time>
                        </div>

                        <div v-if="editingId === comment.id" class="space-y-4 py-2">
                            <textarea
                                v-model="editMessage"
                                rows="4"
                                class="w-full rounded-2xl border border-sidebar-border bg-background/50 px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-primary/20 transition-all backdrop-blur-sm"
                            />
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input
                                    v-model="editIsPublic"
                                    type="checkbox"
                                    class="h-4 w-4 rounded-full border-sidebar-border bg-background text-primary focus:ring-primary/20 transition-all"
                                />
                                <span class="text-xs text-muted-foreground font-medium">Visible to everyone</span>
                            </label>
                        </div>
                        <div v-else class="flex-1">
                            <p class="text-sm leading-relaxed text-foreground/90 font-light italic line-clamp-3 group-hover:line-clamp-none transition-all duration-500">
                                "{{ comment.message }}"
                            </p>
                        </div>
                    </div>

                    <div class="relative z-10 mt-6 pt-5 border-t border-sidebar-border/30 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <span
                                class="inline-flex h-2 w-2 rounded-full"
                                :class="comment.is_public ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]' : 'bg-zinc-400'"
                            ></span>
                            <span class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
                                {{ comment.is_public ? 'Public' : 'Private' }}
                            </span>
                        </div>

                        <div class="flex items-center gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                            <template v-if="editingId === comment.id">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="h-8 px-4 rounded-full border-sidebar-border hover:bg-primary hover:text-primary-foreground hover:border-primary transition-all text-xs font-semibold"
                                    :disabled="saving"
                                    @click="saveEdit(comment)"
                                >
                                    Save
                                </Button>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    class="h-8 px-4 rounded-full hover:bg-muted/50 transition-all text-xs font-semibold"
                                    @click="cancelEdit"
                                >
                                    Cancel
                                </Button>
                            </template>
                            <template v-else>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="h-8 px-4 rounded-full border-sidebar-border hover:bg-muted transition-all text-xs font-semibold"
                                    @click="startEdit(comment)"
                                >
                                    Edit
                                </Button>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    class="h-8 px-4 rounded-full text-destructive hover:bg-destructive/10 transition-all text-xs font-semibold"
                                    @click="remove(comment)"
                                >
                                    Remove
                                </Button>
                            </template>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </AppLayout>
</template>

