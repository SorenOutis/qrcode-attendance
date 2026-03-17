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
const mouseGlowRef = ref<HTMLElement | null>(null);
let glowAnimId: number | null = null;
let targetX = 0, targetY = 0, currentX = 0, currentY = 0;

onMounted(() => {
    const onMouseMove = (e: MouseEvent) => {
        targetX = e.clientX;
        targetY = e.clientY;
    };

    const lerp = (a: number, b: number, t: number) => a + (b - a) * t;

    const animate = () => {
        currentX = lerp(currentX, targetX, 0.06);
        currentY = lerp(currentY, targetY, 0.06);
        if (mouseGlowRef.value) {
            mouseGlowRef.value.style.transform = `translate(${currentX}px, ${currentY}px) translate(-50%, -50%)`;
        }
        glowAnimId = requestAnimationFrame(animate);
    };

    window.addEventListener('mousemove', onMouseMove, { passive: true });
    glowAnimId = requestAnimationFrame(animate);
});

onUnmounted(() => {
    if (glowAnimId) cancelAnimationFrame(glowAnimId);
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
            <!-- Noise Overlay -->
            <div class="absolute inset-0 opacity-[0.03] pointer-events-none z-50 bg-[url('https://grainy-gradients.vercel.app/noise.svg')] bg-repeat"></div>
            
            <!-- Mouse Follow Glow -->
            <div ref="mouseGlowRef" class="hidden lg:block fixed top-0 left-0 w-[600px] h-[600px] rounded-full bg-primary/10 blur-[140px] pointer-events-none z-0" style="will-change: transform;"></div>

            <div class="absolute top-[-20%] left-[-10%] w-[50%] h-[50%] rounded-full bg-primary/10 blur-[150px]"></div>
            <div class="absolute bottom-[-10%] right-[-10%] w-[50%] h-[50%] rounded-full bg-emerald-500/10 blur-[170px]"></div>
            
            <div class="absolute inset-0 bg-[linear-gradient(to_right,#80808012_1px,transparent_1px),linear-gradient(to_bottom,#80808012_1px,transparent_1px)] bg-[size:32px_32px]"></div>
            
            <!-- Vignette to draw attention to center -->
            <div class="absolute inset-0 shadow-[inset_0_0_150px_60px_hsl(var(--background))] backdrop-blur-sm bg-background/50"></div>
        </div>
        <header class="relative z-50 w-full px-6 lg:px-16 py-4 lg:py-6 flex justify-between items-center bg-background/80 backdrop-blur-xl border-b border-sidebar-border/30 sticky top-0 transition-all">
            <div class="flex items-center gap-2 lg:gap-3">
                <div class="w-9 h-9 lg:w-10 lg:h-10 rounded-xl bg-foreground text-background flex items-center justify-center font-bold text-lg lg:text-xl drop-shadow-lg shadow-black/20 dark:shadow-white/20">
                    Q
                </div>
                <div class="flex flex-col">
                    <span class="font-serif font-bold text-base lg:text-lg leading-none tracking-tight lg:tracking-wide text-foreground">QR Attendance</span>
                    <span class="text-[8px] lg:text-[10px] uppercase tracking-[0.2em] text-muted-foreground mt-0.5 opacity-80">System</span>
                </div>
            </div>
            <nav class="flex items-center gap-2 lg:gap-6 text-sm font-medium">
                <ThemeToggle />
                <template v-if="$page.props.auth.user">
                    <Link
                        :href="dashboard.url()"
                        class="text-xs lg:text-sm text-foreground hover:opacity-80 transition-all font-semibold whitespace-nowrap"
                    >
                        Dashboard
                    </Link>
                </template>
                <template v-else>
                    <Link
                        :href="login.url()"
                        class="text-xs lg:text-sm text-muted-foreground hover:text-foreground transition-colors px-2 lg:px-3 py-1.5 rounded-full hover:bg-sidebar-border/20 whitespace-nowrap"
                    >
                        Log in
                    </Link>
                    <Link
                        v-if="canRegister"
                        :href="register.url()"
                        class="text-xs lg:text-sm px-3 lg:px-4 py-1.5 rounded-full bg-foreground text-background hover:opacity-90 transition-all font-semibold shadow-sm whitespace-nowrap"
                    >
                        Register
                    </Link>
                </template>
            </nav>
        </header>

        <main class="relative z-10 flex flex-col lg:flex-row lg:min-h-[calc(100vh-100px)] w-full gap-4 lg:gap-0 lg:py-0 pb-6 lg:pb-0">
            <!-- Left Side Content -->
            <div class="w-full lg:w-5/12 flex flex-col justify-center px-6 lg:px-16 z-20">
                <div ref="titleRef" class="space-y-3 lg:space-y-4 mb-6 lg:mb-8">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-sidebar-border/70 bg-background/50 backdrop-blur-md text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-4">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                        Live System Active
                    </div>
                    
                    <div class="flex items-center justify-between lg:block gap-4">
                        <h1 class="text-4xl xs:text-5xl sm:text-6xl lg:text-7xl font-serif font-bold text-foreground leading-[1.1] tracking-tight flex-1">
                            Elevate<br/>
                            <span class="italic text-muted-foreground">Attendance.</span>
                        </h1>
                        
                        <!-- Small Scanning Visual for Mobile -->
                        <div class="lg:hidden w-32 h-32 shrink-0">
                            <ScanningVisual small />
                        </div>
                    </div>
                </div>
                
                <p ref="textRef" class="text-sm sm:text-base lg:text-lg text-muted-foreground/90 font-light leading-relaxed mb-8 lg:mb-10 max-w-sm">
                    Experience a seamless, contactless, and elegant approach to tracking presence in real-time.
                </p>
                
                <div ref="btnRef" class="flex flex-col sm:flex-row gap-3 lg:gap-4">
                    <template v-if="$page.props.auth.user">
                        <Link
                            :href="dashboard.url()"
                            class="inline-flex items-center justify-center h-12 lg:h-14 px-8 rounded-full bg-foreground text-background hover:bg-foreground/90 text-sm font-semibold tracking-wide transition-all shadow-xl hover:shadow-2xl hover:-translate-y-1"
                        >
                            Go to Dashboard
                        </Link>
                    </template>
                    <template v-else>
                        <Button @click="ratingModalOpen = true" class="h-12 lg:h-14 px-8 rounded-full bg-foreground text-background hover:bg-foreground/90 text-sm font-semibold tracking-wide transition-all shadow-xl hover:shadow-2xl hover:-translate-y-1">
                            Rate System
                        </Button>
                        <Button @click="commentModalOpen = true" variant="outline" class="h-12 lg:h-14 px-8 rounded-full border-sidebar-border hover:bg-sidebar-border/20 text-foreground text-sm font-semibold tracking-wide transition-all backdrop-blur-sm">
                            Leave Feedback
                        </Button>
                    </template>
                </div>

                <!-- Live Quick-Stats Widget -->
                <div class="mt-8 lg:mt-16 flex items-center gap-4 lg:gap-6 p-4 lg:p-6 rounded-3xl border border-sidebar-border/50 bg-background/30 backdrop-blur-xl shadow-2xl relative overflow-hidden group hover:border-sidebar-border/80 transition-all duration-500 max-w-[400px]">
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

            <!-- Right Visuals (Desktop Only) -->
            <div class="hidden lg:flex w-full lg:w-7/12 relative h-auto min-h-[350px] lg:min-h-[500px] items-center justify-center lg:justify-end px-4 lg:pr-[10%] overflow-hidden">
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
        <section class="relative z-10 w-full px-6 lg:px-16 py-8 lg:py-12 bg-background/40 backdrop-blur-md border-t border-sidebar-border/30">
            <div class="max-w-7xl mx-auto flex flex-col lg:flex-row items-center justify-between gap-8 lg:gap-12">
                <div class="w-full lg:w-4/12 flex flex-col justify-center">
                    <h2 class="text-2xl lg:text-3xl font-serif font-bold text-foreground mb-3 lg:mb-4">What Our Users Say</h2>
                    <p class="text-sm lg:text-base text-muted-foreground/90 font-light mb-6 lg:mb-8">
                        Real feedback from students and faculty experiencing the streamlined attendance process.
                    </p>
                </div>

                <!-- Right Side Carousel -->
                <div ref="carouselContainerRef" 
                     v-if="cards.length > 0"
                     class="w-full lg:w-7/12 mt-6 lg:mt-0 relative h-[320px] lg:h-[420px] flex flex-col justify-end"
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
                <div v-else class="w-full lg:w-7/12 relative h-[260px] lg:h-[420px] flex items-center justify-center">
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

        <!-- How It Works Section -->
        <section class="relative z-10 w-full px-6 lg:px-16 py-16 lg:py-24 border-t border-sidebar-border/30">
            <div class="max-w-7xl mx-auto">
                <!-- Section Header -->
                <div class="text-center mb-12 lg:mb-20">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-sidebar-border/70 bg-background/50 backdrop-blur-md text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-6">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        Seamless Process
                    </div>
                    <h2 class="text-3xl lg:text-5xl font-serif font-bold text-foreground leading-tight mb-4">
                        How It <span class="italic text-muted-foreground">Works</span>
                    </h2>
                    <p class="text-sm lg:text-base text-muted-foreground/80 font-light max-w-md mx-auto">
                        Three simple steps to a fully contactless, real-time attendance system.
                    </p>
                </div>

                <!-- Mobile: Snap Scroll Carousel | Desktop: Grid -->
                <div class="relative">
                    <!-- Desktop connecting line -->
                    <div class="hidden md:block absolute top-10 left-[20%] right-[20%] h-[1px] bg-gradient-to-r from-transparent via-sidebar-border/50 to-transparent z-0"></div>

                    <!-- Cards Container -->
                    <div 
                        id="how-it-works-carousel"
                        class="flex md:grid md:grid-cols-3 gap-4 lg:gap-8 overflow-x-auto md:overflow-x-visible snap-x snap-mandatory scroll-smooth pb-4 md:pb-0 -mx-6 px-6 md:mx-0 md:px-0"
                        style="scrollbar-width: none; -ms-overflow-style: none;"
                    >
                    <!-- Step 1 -->
                    <div class="group relative flex flex-col items-center text-center p-8 rounded-3xl border border-sidebar-border/40 bg-background/20 backdrop-blur-sm hover:border-emerald-500/30 hover:bg-background/40 transition-all duration-500 hover:-translate-y-1 shrink-0 w-[85vw] md:w-auto snap-center snap-always">
                        <div class="absolute inset-0 rounded-3xl bg-gradient-to-br from-emerald-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none"></div>
                        <div class="relative z-10 w-20 h-20 rounded-2xl bg-foreground/5 border border-sidebar-border/60 flex items-center justify-center mb-6 group-hover:border-emerald-500/40 group-hover:bg-emerald-500/5 transition-all duration-500 shadow-inner">
                            <svg class="w-9 h-9 text-emerald-500/80 group-hover:text-emerald-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75zM6.75 16.5h.75v.75h-.75v-.75zM16.5 6.75h.75v.75h-.75v-.75zM13.5 13.5h.75v.75h-.75v-.75zM13.5 19.5h.75v.75h-.75v-.75zM19.5 13.5h.75v.75h-.75v-.75zM19.5 19.5h.75v.75h-.75v-.75zM16.5 16.5h.75v.75h-.75v-.75z" />
                            </svg>
                        </div>
                        <div class="text-xs font-mono text-muted-foreground/50 uppercase tracking-widest mb-2">Step 01</div>
                        <h3 class="text-lg font-serif font-bold text-foreground mb-3">Admin Generates</h3>
                        <p class="text-sm text-muted-foreground/80 font-light leading-relaxed">Faculty creates a session-unique, encrypted QR code from the dashboard in seconds.</p>
                    </div>

                    <!-- Step 2 -->
                    <div class="group relative flex flex-col items-center text-center p-8 rounded-3xl border border-sidebar-border/40 bg-background/20 backdrop-blur-sm hover:border-emerald-500/30 hover:bg-background/40 transition-all duration-500 hover:-translate-y-1 md:-translate-y-4 shrink-0 w-[85vw] md:w-auto snap-center snap-always">
                        <div class="absolute inset-0 rounded-3xl bg-gradient-to-br from-emerald-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none"></div>
                        <div class="relative z-10 w-20 h-20 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center mb-6 group-hover:border-emerald-500/60 group-hover:bg-emerald-500/20 transition-all duration-500 shadow-[0_0_30px_rgba(16,185,129,0.1)]">
                            <svg class="w-9 h-9 text-emerald-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
                            </svg>
                        </div>
                        <div class="text-xs font-mono text-emerald-500/60 uppercase tracking-widest mb-2">Step 02</div>
                        <h3 class="text-lg font-serif font-bold text-foreground mb-3">Student Scans</h3>
                        <p class="text-sm text-muted-foreground/80 font-light leading-relaxed">Students scan with any mobile device — contactless, instant, and seamless.</p>
                    </div>

                    <!-- Step 3 -->
                    <div class="group relative flex flex-col items-center text-center p-8 rounded-3xl border border-sidebar-border/40 bg-background/20 backdrop-blur-sm hover:border-emerald-500/30 hover:bg-background/40 transition-all duration-500 hover:-translate-y-1 shrink-0 w-[85vw] md:w-auto snap-center snap-always">
                        <div class="absolute inset-0 rounded-3xl bg-gradient-to-br from-emerald-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none"></div>
                        <div class="relative z-10 w-20 h-20 rounded-2xl bg-foreground/5 border border-sidebar-border/60 flex items-center justify-center mb-6 group-hover:border-emerald-500/40 group-hover:bg-emerald-500/5 transition-all duration-500 shadow-inner">
                            <svg class="w-9 h-9 text-emerald-500/80 group-hover:text-emerald-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                            </svg>
                        </div>
                        <div class="text-xs font-mono text-muted-foreground/50 uppercase tracking-widest mb-2">Step 03</div>
                        <h3 class="text-lg font-serif font-bold text-foreground mb-3">Real-time Tracking</h3>
                        <p class="text-sm text-muted-foreground/80 font-light leading-relaxed">Attendance is instantly recorded and visible in the admin dashboard — live, accurate, and exportable.</p>
                    </div>
                    </div>

                    <!-- Mobile Dots Indicator -->
                    <div class="flex md:hidden items-center justify-center gap-2 mt-6">
                        <div class="w-6 h-1.5 rounded-full bg-foreground/60 transition-all duration-300"></div>
                        <div class="w-1.5 h-1.5 rounded-full bg-foreground/20 transition-all duration-300"></div>
                        <div class="w-1.5 h-1.5 rounded-full bg-foreground/20 transition-all duration-300"></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Powered By Section -->
        <section class="relative z-10 w-full px-6 lg:px-16 py-10 lg:py-12 border-t border-sidebar-border/20">
            <div class="max-w-7xl mx-auto">
                <p class="text-center text-[10px] uppercase tracking-[0.3em] text-muted-foreground/50 mb-8 font-semibold">Powered by</p>
                <div class="flex flex-wrap items-center justify-center gap-8 lg:gap-16 opacity-40 hover:opacity-60 transition-opacity duration-500">
                    <!-- Laravel -->
                    <div class="flex items-center gap-2 group cursor-default">
                        <svg class="h-6 opacity-70 grayscale transition-all duration-300 group-hover:grayscale-0 group-hover:opacity-100" viewBox="0 0 66 66" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M62.91 16.81c.08.43.08.86.08 1.3v14.37c0 1.71-.92 3.3-2.41 4.16L48.84 43.3v13.56c0 1.72-.91 3.3-2.4 4.16L33.27 68.3a4.82 4.82 0 01-4.8 0L15.3 61.02A4.82 4.82 0 0112.89 57V43.44L1.58 36.84A4.82 4.82 0 01.17 32.8V18.11c0-1.72.91-3.3 2.4-4.16L15.74 6.7a4.82 4.82 0 014.8 0l12.45 7.25a4.82 4.82 0 014.8 0l13.17-7.66a4.82 4.82 0 014.8 0l11.75 6.85c1.13.65 1.88 1.72 2.1 2.97z" fill="#FF2D20" fill-opacity="0.1"/>
                            <text x="33" y="40" font-family="serif" font-size="22" font-weight="bold" fill="#FF2D20" text-anchor="middle">L</text>
                        </svg>
                        <span class="text-sm font-semibold tracking-wide" style="font-family: 'Inter', sans-serif;">Laravel</span>
                    </div>
                    <!-- Vue -->
                    <div class="flex items-center gap-2 group cursor-default">
                        <svg class="h-5 grayscale transition-all duration-300 group-hover:grayscale-0" viewBox="0 0 256 221" xmlns="http://www.w3.org/2000/svg">
                            <path d="M204.8 0H256L128 220.8 0 0h97.92L128 51.2 157.44 0h47.36z" fill="#41B883"/>
                            <path d="M0 0l128 220.8L256 0h-51.2L128 132.48 50.56 0H0z" fill="#41B883"/>
                            <path d="M50.56 0L128 133.12 204.8 0h-47.36L128 51.2 97.92 0H50.56z" fill="#35495E"/>
                        </svg>
                        <span class="text-sm font-semibold tracking-wide" style="font-family: 'Inter', sans-serif;">Vue.js</span>
                    </div>
                    <!-- Tailwind -->
                    <div class="flex items-center gap-2 group cursor-default">
                        <svg class="h-5 grayscale transition-all duration-300 group-hover:grayscale-0" viewBox="0 0 248 31" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M25.517 0C18.712 0 14.46 3.382 12.758 10.146c2.552-3.382 5.529-4.65 8.931-3.805 1.941.482 3.329 1.882 4.864 3.432 2.502 2.524 5.398 5.545 11.722 5.545 6.804 0 11.057-3.382 12.758-10.145-2.551 3.382-5.528 4.65-8.93 3.804-1.942-.482-3.33-1.882-4.865-3.431C34.736 3.022 31.841 0 25.517 0zM12.758 15.218C5.954 15.218 1.701 18.6 0 25.364c2.552-3.382 5.529-4.65 8.93-3.805 1.942.482 3.33 1.882 4.865 3.432 2.502 2.524 5.397 5.545 11.722 5.545 6.804 0 11.057-3.381 12.758-10.145-2.552 3.382-5.529 4.65-8.931 3.805-1.941-.483-3.329-1.883-4.864-3.432-2.502-2.524-5.398-5.546-11.722-5.546z" fill="#38BDF8"/>
                        </svg>
                        <span class="text-sm font-semibold tracking-wide" style="font-family: 'Inter', sans-serif;">Tailwind CSS</span>
                    </div>
                    <!-- Inertia -->
                    <div class="flex items-center gap-2 group cursor-default">
                        <div class="h-5 w-5 rounded bg-violet-500/20 border border-violet-500/40 flex items-center justify-center">
                            <span class="text-[8px] font-black text-violet-500">I</span>
                        </div>
                        <span class="text-sm font-semibold tracking-wide" style="font-family: 'Inter', sans-serif;">Inertia.js</span>
                    </div>
                    <!-- GSAP -->
                    <div class="flex items-center gap-2 group cursor-default">
                        <div class="h-5 w-5 rounded-full bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center">
                            <span class="text-[8px] font-black text-emerald-500">G</span>
                        </div>
                        <span class="text-sm font-semibold tracking-wide" style="font-family: 'Inter', sans-serif;">GSAP</span>
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
