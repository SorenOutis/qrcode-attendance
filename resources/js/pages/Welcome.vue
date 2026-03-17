<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref, computed } from 'vue';
import gsap from 'gsap';
import { dashboard, login, register } from '@/routes';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import commentsRoutes from '@/routes/comments';
import ratingsRoutes from '@/routes/ratings';
import ScanningVisual from '@/components/ScanningVisual.vue';

type Comment = {
    id: number;
    name?: string | null;
    message: string;
    created_at: string;
};

type Rating = {
    id: number;
    name?: string | null;
    score: number;
    message?: string | null;
    created_at: string;
};

const props = withDefaults(
    defineProps<{
        canRegister?: boolean;
        comments?: Comment[];
        ratings?: Rating[];
        stats?: {
            total_scans: number;
            present_today: number;
        };
    }>(),
    {
        canRegister: true,
        comments: () => [],
        ratings: () => [],
        stats: () => ({ total_scans: 0, present_today: 0 }),
    },
);

const containerRef = ref<HTMLElement | null>(null);
const titleRef = ref<HTMLElement | null>(null);
const textRef = ref<HTMLElement | null>(null);
const btnRef = ref<HTMLElement | null>(null);
const carouselContainerRef = ref<HTMLElement | null>(null);
const carouselRef = ref<HTMLElement | null>(null);

const cards = computed(() => {
    let items = [];
    if (props.ratings && props.ratings.length > 0) {
        items.push(...props.ratings.map(r => ({
            id: 'r' + r.id,
            title: r.name || 'Anonymous',
            desc: `Says: "${r.message || 'Rated the system.'}"\n\n${'★'.repeat(r.score)}${'☆'.repeat(5 - r.score)}`,
            color: 'from-amber-500/10 via-background to-background'
        })));
    }
    if (props.comments && props.comments.length > 0) {
        items.push(...props.comments.map(c => ({
            id: 'c' + c.id,
            title: c.name || 'Anonymous',
            desc: `Says: "${c.message}"`,
            color: 'from-indigo-500/10 via-background to-background'
        })));
    }
    
    return items;
});

// Carousel state
const activeIndex = ref(0);
const cardWidth = 240; // 220px width + 20px gap (gap-5)

let slideInterval: ReturnType<typeof setInterval> | null = null;
const isHovering = ref(false);

const startAutoSlide = () => {
    if (slideInterval) clearInterval(slideInterval);
    slideInterval = setInterval(() => {
        if (!isHovering.value) {
            nextCard();
        }
    }, 4500); // Auto slide every 2.5 seconds for slower, smoother feel
};

const stopAutoSlide = () => {
    if (slideInterval) {
        clearInterval(slideInterval);
        slideInterval = null;
    }
};

const nextCard = () => {
    if (cards.value.length === 0) return;
    if (activeIndex.value < cards.value.length - 1) {
        activeIndex.value++;
    } else {
        activeIndex.value = 0; // Loop back to start
    }
    animateCarousel();
};

const prevCard = () => {
    if (cards.value.length === 0) return;
    if (activeIndex.value > 0) {
        activeIndex.value--;
    } else {
        activeIndex.value = cards.value.length - 1; // Loop to end
    }
    animateCarousel();
};

const animateCarousel = () => {
    if (!carouselRef.value) return;
    
    const items = carouselRef.value.querySelectorAll<HTMLElement>('.carousel-item');
    
    // Smooth transition between cards with slight 3D pop
    gsap.to(carouselRef.value, {
        x: -(activeIndex.value * cardWidth),
        duration: 0.8,
        ease: 'power3.inOut' // Very smooth, premium feel ease
    });
    
    // Animate individual items for 3D effect
    items.forEach((item, index) => {
        if (index === activeIndex.value) {
            gsap.to(item, {
                scale: 1,
                opacity: 1,
                rotationY: 0,
                z: 0,
                duration: 0.8,
                ease: 'power3.inOut'
            });
        } else {
            // Revert tilt and scale down inactive cards with 3D rotation
            gsap.to(item, {
                scale: 0.88,
                opacity: 0.5,
                rotationX: 0,
                rotationY: index < activeIndex.value ? 25 : -25,
                z: -50,
                duration: 0.8,
                ease: 'power3.inOut'
            });
        }
    });
};

onMounted(() => {
    const tl = gsap.timeline();

    // Setup 3D perspectives
    if (containerRef.value) {
        gsap.set([titleRef.value, textRef.value, btnRef.value], { transformStyle: "preserve-3d" });
        tl.fromTo(titleRef.value, { y: 60, opacity: 0, rotationX: 45, z: -100 }, { y: 0, opacity: 1, rotationX: 0, z: 0, duration: 1.2, ease: 'power4.out' })
          .fromTo(textRef.value, { y: 40, opacity: 0, rotationX: 20, z: -50 }, { y: 0, opacity: 1, rotationX: 0, z: 0, duration: 1, ease: 'power4.out' }, "-=0.9")
          .fromTo(btnRef.value, { y: 30, opacity: 0, z: -30 }, { y: 0, opacity: 1, z: 0, duration: 0.8, ease: 'power4.out' }, "-=0.7");
    }

    if (carouselContainerRef.value) {
        gsap.set(carouselContainerRef.value, { perspective: 1200 });
        const items = carouselContainerRef.value.querySelectorAll<HTMLElement>('.carousel-item');
        
        items.forEach(item => gsap.set(item, { transformStyle: "preserve-3d" }));
        
        // Initial setup for inactive vs active cards
        items.forEach((item, index) => {
            if (index !== activeIndex.value) {
                gsap.set(item, { scale: 0.88, opacity: 0.5, rotationY: index < activeIndex.value ? 25 : -25, z: -50 });
            }
        });

        gsap.from(items, 
            { x: 150, opacity: 0, rotationY: -45, z: -150, duration: 1.2, stagger: 0.1, ease: 'power3.out' }
        );

        // 3D tilt effect on hover for active item
        items.forEach((card, index) => {
            card.addEventListener('mousemove', (e: MouseEvent) => {
                if (activeIndex.value !== index) return;
                
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                
                const rotateX = ((y - centerY) / centerY) * -15;
                const rotateY = ((x - centerX) / centerX) * 15;
                
                gsap.to(card, {
                    rotationX: rotateX,
                    rotationY: rotateY,
                    scale: 1.05,
                    z: 50,
                    boxShadow: '0 30px 40px -10px rgba(0, 0, 0, 0.3), 0 15px 15px -10px rgba(0, 0, 0, 0.1)',
                    duration: 0.4,
                    ease: 'power3.out'
                });
            });

            card.addEventListener('mouseleave', () => {
                if (activeIndex.value !== index) return;
                
                gsap.to(card, {
                    rotationX: 0,
                    rotationY: 0,
                    scale: 1,
                    z: 0,
                    boxShadow: 'none',
                    duration: 0.6,
                    ease: 'elastic.out(1, 0.3)'
                });
            });
        });
    }
    
    startAutoSlide();
});

const ratingModalOpen = ref(false);
const commentModalOpen = ref(false);

const ratingForm = useForm({
    name: '',
    email: '',
    score: 5,
    message: '',
});

const commentForm = useForm({
    name: '',
    email: '',
    message: '',
});

function openRatingModal() {
    ratingForm.reset();
    ratingModalOpen.value = true;
}

function openCommentModal() {
    commentForm.reset();
    commentModalOpen.value = true;
}

function submitRating() {
    ratingForm.post(ratingsRoutes.store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            ratingModalOpen.value = false;
            ratingForm.reset();
        },
    });
}

function submitComment() {
    commentForm.post(commentsRoutes.store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            commentModalOpen.value = false;
            commentForm.reset();
        },
    });
}

onUnmounted(() => {
    stopAutoSlide();
});
</script>

<template>
    <Head title="Welcome">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
        <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,500;1,600&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet" />
    </Head>
    
    <div ref="containerRef" class="min-h-screen relative flex flex-col bg-background text-foreground font-sans overflow-hidden">
        <!-- Abstract gradient background matching Dashboard feel -->
        <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none">
            <!-- These classes match the gradients used in Dashboard cards -->
            <div class="absolute top-[-20%] left-[-10%] w-[50%] h-[50%] rounded-full bg-primary/10 blur-[150px]"></div>
            <div class="absolute bottom-[-10%] right-[-10%] w-[50%] h-[50%] rounded-full bg-emerald-500/10 blur-[170px]"></div>
            
            <div class="absolute inset-0 bg-[linear-gradient(to_right,#80808012_1px,transparent_1px),linear-gradient(to_bottom,#80808012_1px,transparent_1px)] bg-[size:32px_32px]"></div>
            
            <!-- Vignette to draw attention to center -->
            <div class="absolute inset-0 shadow-[inset_0_0_150px_60px_hsl(var(--background))] backdrop-blur-sm bg-background/50"></div>
        </div>
        <header class="relative z-20 w-full px-8 lg:px-16 py-8 flex justify-between items-center bg-background/50 backdrop-blur-md border-b border-sidebar-border/50 sticky top-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-foreground text-background flex items-center justify-center font-bold text-xl drop-shadow-lg shadow-black/20 dark:shadow-white/20">
                    Q
                </div>
                <div class="flex flex-col">
                    <span class="font-serif font-bold text-lg leading-none tracking-wide text-foreground">QR Attendance</span>
                    <span class="text-[10px] uppercase tracking-[0.2em] text-muted-foreground mt-0.5">System</span>
                </div>
            </div>
            <nav class="flex items-center gap-6 text-sm font-medium">
                <ThemeToggle />
                <template v-if="$page.props.auth.user">
                    <Link
                        :href="dashboard.url()"
                        class="text-muted-foreground hover:text-foreground transition-colors relative after:absolute after:bottom-0 after:left-0 after:w-full after:h-[1px] after:bg-foreground after:origin-right hover:after:origin-left after:scale-x-0 hover:after:scale-x-100 after:transition-transform after:duration-300"
                    >
                        Dashboard
                    </Link>
                </template>
                <template v-else>
                    <Link
                        :href="login.url()"
                        class="text-muted-foreground hover:text-foreground transition-colors relative after:absolute after:bottom-0 after:left-0 after:w-full after:h-[1px] after:bg-foreground after:origin-right hover:after:origin-left after:scale-x-0 hover:after:scale-x-100 after:transition-transform after:duration-300"
                    >
                        Log in
                    </Link>
                    <Link
                        v-if="canRegister"
                        :href="register.url()"
                        class="text-muted-foreground hover:text-foreground transition-colors relative after:absolute after:bottom-0 after:left-0 after:w-full after:h-[1px] after:bg-foreground after:origin-right hover:after:origin-left after:scale-x-0 hover:after:scale-x-100 after:transition-transform after:duration-300"
                    >
                        Register
                    </Link>
                </template>
            </nav>
        </header>

        <main class="relative z-10 flex flex-col lg:flex-row min-h-[calc(100vh-100px)] w-full gap-8 lg:gap-0 lg:py-0 pb-16">
            <!-- Left Side Content -->
            <div class="w-full lg:w-5/12 flex flex-col justify-center px-8 lg:px-16 z-20">
                <div ref="titleRef" class="space-y-4 mb-8">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-sidebar-border/70 bg-background/50 backdrop-blur-md text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-4">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                        Live System Active
                    </div>
                    <h1 class="text-6xl sm:text-7xl font-serif font-bold text-foreground leading-[1.1] tracking-tight">
                        Elevate<br/>
                        <span class="italic text-muted-foreground">Attendance.</span>
                    </h1>
                </div>
                
                <p ref="textRef" class="text-base sm:text-lg text-muted-foreground/90 font-light leading-relaxed mb-10 max-w-sm">
                    Experience a seamless, contactless, and elegant approach to tracking presence in real-time.
                </p>
                
                <div ref="btnRef" class="flex flex-col sm:flex-row gap-4">
                    <template v-if="$page.props.auth.user">
                        <Link
                            :href="dashboard.url()"
                            class="inline-flex items-center justify-center h-14 px-8 rounded-full bg-foreground text-background hover:bg-foreground/90 text-sm font-semibold tracking-wide transition-all shadow-xl hover:shadow-2xl hover:-translate-y-1"
                        >
                            Go to Dashboard
                        </Link>
                    </template>
                    <template v-else>
                        <Button @click="ratingModalOpen = true" class="h-14 px-8 rounded-full bg-foreground text-background hover:bg-foreground/90 text-sm font-semibold tracking-wide transition-all shadow-xl hover:shadow-2xl hover:-translate-y-1">
                            Rate System
                        </Button>
                        <Button @click="commentModalOpen = true" variant="outline" class="h-14 px-8 rounded-full border-sidebar-border hover:bg-sidebar-border/20 text-foreground text-sm font-semibold tracking-wide transition-all backdrop-blur-sm">
                            Leave Feedback
                        </Button>
                    </template>
                </div>

                <!-- Live Quick-Stats Widget -->
                <div class="mt-16 flex items-center gap-6 p-6 rounded-3xl border border-sidebar-border/50 bg-background/30 backdrop-blur-xl shadow-2xl relative overflow-hidden group hover:border-sidebar-border/80 transition-all duration-500 max-w-[400px]">
                    <div class="absolute -right-8 -top-8 w-32 h-32 bg-primary/5 rounded-full blur-2xl group-hover:bg-primary/10 transition-colors pointer-events-none"></div>
                    <div class="relative z-10">
                        <div class="text-3xl font-serif font-bold text-foreground">
                            {{ props.stats.present_today }}
                        </div>
                        <div class="text-[10px] uppercase tracking-widest text-muted-foreground font-semibold mt-1">Present Today</div>
                    </div>
                    <div class="w-[1px] h-12 bg-sidebar-border/50 relative z-10"></div>
                    <div class="relative z-10">
                        <div class="text-3xl font-serif font-bold text-foreground">
                            {{ props.stats.total_scans }}
                        </div>
                        <div class="text-[10px] uppercase tracking-widest text-muted-foreground font-semibold mt-1">Total Scans</div>
                    </div>
                </div>
            </div>

            <!-- Right Visuals/Mockup -->
            <div class="w-full lg:w-7/12 relative h-auto min-h-[500px] lg:h-auto flex items-center justify-center lg:justify-end px-4 lg:pr-[10%] overflow-hidden">
                <div class="relative w-full max-w-[500px] aspect-square">
                    <div class="absolute inset-0 bg-gradient-to-tr from-zinc-200/20 to-zinc-50/5 dark:from-zinc-800/20 dark:to-zinc-900/5 rounded-full blur-3xl animate-pulse" style="animation-duration: 4s;"></div>
                    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[80%] h-[80%] rounded-full border border-sidebar-border/40 animate-[spin_60s_linear_infinite]"></div>
                    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[60%] h-[60%] rounded-full border border-sidebar-border/60 border-dashed animate-[spin_40s_linear_infinite_reverse]"></div>
                    
                    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full h-full transform hover:scale-[1.02] transition-transform duration-700">
                        <ScanningVisual />
                    </div>
                </div>
            </div>
        </main>

        <!-- Feedback Section -->
        <section class="relative z-10 w-full px-8 lg:px-16 py-16 bg-background/40 backdrop-blur-md border-t border-sidebar-border/30">
            <div class="max-w-7xl mx-auto flex flex-col lg:flex-row items-center justify-between gap-12">
                <div class="w-full lg:w-4/12 flex flex-col justify-center">
                    <h2 class="text-3xl font-serif font-bold text-foreground mb-4">What Our Users Say</h2>
                    <p class="text-muted-foreground/90 font-light mb-8">
                        Real feedback from students and faculty experiencing the streamlined attendance process.
                    </p>
                </div>

                <!-- Right Side Carousel -->
                <div ref="carouselContainerRef" 
                     v-if="cards.length > 0"
                     class="w-full lg:w-7/12 mt-12 lg:mt-0 relative h-[380px] lg:h-[450px] flex flex-col justify-end"
                     @mouseenter="isHovering = true"
                     @mouseleave="isHovering = false"
                >
                    <div class="w-full overflow-hidden absolute right-0 bottom-16" style="mask-image: linear-gradient(to right, transparent, black 10%, black 75%, transparent); -webkit-mask-image: linear-gradient(to right, transparent, black 10%, black 75%, transparent);">
                        <div ref="carouselRef" class="flex gap-5 px-4 lg:pl-[10%] w-max border-l border-transparent">
                            <div 
                                v-for="(card, index) in cards" 
                                :key="card.id"
                                class="carousel-item relative w-[220px] h-[280px] rounded-[24px] p-6 flex flex-col justify-between overflow-hidden shadow-sm bg-gradient-to-br border border-sidebar-border/70 shrink-0 cursor-pointer"
                                :class="[card.color]"
                                @click="activeIndex = index; animateCarousel()"
                            >
                                <div class="absolute inset-0 bg-background/60 backdrop-blur-xl pointer-events-none" :class="{ 'bg-background/40': activeIndex === index }"></div>
                                <div class="absolute inset-0 bg-gradient-to-t from-background/95 via-background/40 to-transparent pointer-events-none"></div>
                                <div class="relative z-10 space-y-4 flex-1 flex flex-col justify-end pb-2">
                                    <p class="text-muted-foreground font-serif italic text-[15.5px] leading-relaxed pb-1 whitespace-pre-wrap select-none w-full line-clamp-3 group-hover:line-clamp-none transition-all duration-500">
                                        {{ card.desc }}
                                    </p>
                                </div>
                                <div class="relative z-10 flex items-center gap-3 pt-4 border-t border-sidebar-border/50 w-full select-none">
                                    <div class="w-8 h-8 rounded-full bg-primary/20 flex items-center justify-center shrink-0">
                                        <span class="text-xs font-bold text-primary">{{ card.title.charAt(0).toUpperCase() }}</span>
                                    </div>
                                    <div>
                                        <h3 class="text-foreground font-medium text-[13px] drop-shadow-sm font-['Inter'] line-clamp-1 leading-tight">{{ card.title }}</h3>
                                        <div class="text-[10px] text-muted-foreground uppercase tracking-widest mt-0.5">User</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Carousel Controls -->
                    <div class="absolute bottom-0 right-4 lg:right-[10%] flex items-center gap-10 text-sm">
                        <div class="flex items-center gap-3">
                            <button @click="prevCard" class="w-10 h-10 rounded-full border border-sidebar-border/70 flex items-center justify-center text-muted-foreground hover:bg-muted/50 hover:text-foreground transition-all">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M15 18l-6-6 6-6"/></svg>
                            </button>
                            <button @click="nextCard" class="w-10 h-10 rounded-full border border-sidebar-border/70 flex items-center justify-center text-muted-foreground hover:bg-muted/50 hover:text-foreground transition-all relative overflow-hidden group">
                                <div class="absolute top-0 bottom-0 left-0 bg-primary/10 transition-all pointer-events-none" :style="{ width: isHovering ? '0%' : '100%' }" style="transition: width 1s linear;" :key="activeIndex"></div>
                                <svg class="relative z-10" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 18l6-6-6-6"/></svg>
                            </button>
                        </div>
                        <div class="flex items-center gap-4 font-mono text-[10px] tracking-[0.2em] text-muted-foreground">
                            <span class="text-foreground">{{ String(activeIndex + 1).padStart(2, '0') }}</span>
                            <div class="w-12 h-[1px] bg-sidebar-border/70 shrink-0"></div>
                            <span>{{ String(cards.length).padStart(2, '0') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Empty State for Carousel -->
                <div v-else class="w-full lg:w-7/12 relative h-[380px] lg:h-[450px] flex items-center justify-center">
                    <div class="w-full max-w-[320px] text-center space-y-5 rounded-3xl border border-dashed border-sidebar-border bg-background/30 backdrop-blur-sm p-10 shadow-sm relative overflow-hidden group hover:border-sidebar-border/80 transition-all duration-500">
                        <div class="absolute -right-8 -top-8 w-32 h-32 bg-primary/5 rounded-full blur-2xl group-hover:bg-primary/10 transition-colors pointer-events-none"></div>
                        <div class="w-16 h-16 rounded-full bg-background/50 mx-auto flex items-center justify-center text-muted-foreground/50 border border-sidebar-border/50 shadow-inner relative z-10">
                            <svg class="w-8 h-8 opacity-75" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 011.037-.443 48.282 48.282 0 005.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                            </svg>
                        </div>
                        <p class="text-foreground/80 font-serif italic text-[16px] relative z-10">
                            Be the first to tell us what you think!
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <footer class="relative z-10 w-full px-8 lg:px-16 pb-8 flex flex-col sm:flex-row justify-center lg:justify-start items-center gap-4 sm:gap-8 text-[11px] uppercase tracking-wider text-muted-foreground mt-auto">
            <div class="flex gap-8">
                <a href="https://koamishin.org" class="hover:text-foreground transition-colors">Koamishin.org</a>
            </div>
            <span class="text-sidebar-border/70 hidden sm:inline">|</span>
            <p class="text-[10px]">&copy; {{ new Date().getFullYear() }} All rights reserved Koamishin.org</p>
        </footer>

        <!-- Rating Modal -->
        <Dialog v-model:open="ratingModalOpen">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>Rate the system</DialogTitle>
                    <DialogDescription>
                        How was your experience using our attendance system?
                    </DialogDescription>
                </DialogHeader>
                <form @submit.prevent="submitRating" class="space-y-4 py-2">
                    <div class="flex justify-center gap-2">
                        <button
                            v-for="i in 5"
                            :key="i"
                            type="button"
                            class="text-3xl transition-all hover:scale-110 active:scale-95"
                            :class="i <= ratingForm.score ? 'text-yellow-400 drop-shadow-sm' : 'text-muted-foreground/30'"
                            @click="ratingForm.score = i"
                        >
                             ★
                        </button>
                    </div>

                    <div class="grid gap-3">
                        <div class="space-y-1.5">
                            <Label for="r-name" class="text-xs">Name (Optional)</Label>
                            <Input id="r-name" v-model="ratingForm.name" placeholder="John Doe" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="r-message" class="text-xs">Any suggestions?</Label>
                            <textarea
                                id="r-message"
                                v-model="ratingForm.message"
                                placeholder="Optional comments..."
                                rows="3"
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            ></textarea>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="submit" class="w-full" :disabled="ratingForm.processing">
                            Submit Rating
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Suggestion Modal -->
        <Dialog v-model:open="commentModalOpen">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>Suggestions</DialogTitle>
                    <DialogDescription>
                        We'd love to hear how we can improve.
                    </DialogDescription>
                </DialogHeader>
                <form @submit.prevent="submitComment" class="space-y-4 py-2">
                    <div class="grid gap-3">
                        <div class="space-y-1.5">
                            <Label for="c-name" class="text-xs">Name (Optional)</Label>
                            <Input id="c-name" v-model="commentForm.name" placeholder="John Doe" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="c-message" class="text-xs">Your Feedback</Label>
                            <textarea
                                id="c-message"
                                v-model="commentForm.message"
                                placeholder="What's on your mind?"
                                rows="4"
                                required
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            ></textarea>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="submit" class="w-full" :disabled="commentForm.processing">
                            Send Feedback
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
