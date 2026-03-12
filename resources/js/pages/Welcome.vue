<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref, computed } from 'vue';
import gsap from 'gsap';
import { dashboard, login, register } from '@/routes';

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
    }>(),
    {
        canRegister: true,
        comments: () => [],
        ratings: () => [],
    },
);

const containerRef = ref<HTMLElement | null>(null);
const titleRef = ref<HTMLElement | null>(null);
const textRef = ref<HTMLElement | null>(null);
const btnRef = ref<HTMLElement | null>(null);
const carouselContainerRef = ref<HTMLElement | null>(null);
const carouselRef = ref<HTMLElement | null>(null);

const defaultCards = [
    { id: 'd1', title: 'Real-time Tracking', desc: 'Says "Monitor attendance as it happens with zero delay."', color: 'from-primary/10 via-background to-background' },
    { id: 'd2', title: 'Smart Schedules', desc: 'Says "Automated class scheduling and easy management."', color: 'from-emerald-500/10 via-background to-background' },
    { id: 'd3', title: 'Quick Reports', desc: 'Says "Export detailed attendance analytics in seconds."', color: 'from-indigo-500/10 via-background to-background' },
    { id: 'd4', title: 'Secure Access', desc: 'Says "Role-based permissions and secure data storage."', color: 'from-rose-500/10 via-background to-background' },
    { id: 'd5', title: 'Easy Integration', desc: 'Says "Works seamlessly with your existing campus tools."', color: 'from-amber-500/10 via-background to-background' },
];

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
    
    if (items.length === 0) {
        return defaultCards;
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
    if (activeIndex.value < cards.value.length - 1) {
        activeIndex.value++;
    } else {
        activeIndex.value = 0; // Loop back to start
    }
    animateCarousel();
};

const prevCard = () => {
    if (activeIndex.value > 0) {
        activeIndex.value--;
    } else {
        activeIndex.value = cards.value.length - 1; // Loop to end
    }
    animateCarousel();
};

const animateCarousel = () => {
    if (!carouselRef.value) return;
    
    // Smooth transition between cards
    gsap.to(carouselRef.value, {
        x: -(activeIndex.value * cardWidth),
        duration: 0.8,
        ease: 'power3.inOut' // Very smooth, premium feel ease
    });
};

onMounted(() => {
    const tl = gsap.timeline();

    tl.fromTo(titleRef.value, { y: 60, opacity: 0 }, { y: 0, opacity: 1, duration: 1.2, ease: 'power4.out' })
      .fromTo(textRef.value, { y: 40, opacity: 0 }, { y: 0, opacity: 1, duration: 1, ease: 'power4.out' }, "-=0.9")
      .fromTo(btnRef.value, { y: 30, opacity: 0 }, { y: 0, opacity: 1, duration: 0.8, ease: 'power4.out' }, "-=0.7");
      
    if (carouselContainerRef.value) {
        gsap.fromTo(carouselContainerRef.value.querySelectorAll('.carousel-item'), 
            { x: 100, opacity: 0 }, 
            { x: 0, opacity: 1, duration: 1, stagger: 0.1, ease: 'power3.out' }, 
            "-=0.8"
        );
    }
    
    startAutoSlide();
});

onUnmounted(() => {
    stopAutoSlide();
});
</script>

<template>
    <Head title="Welcome">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
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

        <header class="relative z-10 w-full px-8 py-6 lg:px-16 lg:py-10 flex justify-between items-center">
            <div class="text-primary font-semibold tracking-wider text-sm flex items-center gap-3">
                <div class="w-8 h-8 rounded-md bg-primary/20 flex items-center justify-center">
                    <svg class="h-4 w-4 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
                        <path d="M7 7h1v1H7z"/>
                        <path d="M16 7h1v1h-1z"/>
                        <path d="M7 16h1v1H7z"/>
                        <path d="M13 13h4v4h-4z"/>
                    </svg>
                </div>
                <span>QR_ATTENDANCE</span>
            </div>
            <nav class="flex items-center gap-6">
                <Link
                    v-if="$page.props.auth?.user"
                    :href="dashboard()"
                    class="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors"
                >
                    Dashboard
                </Link>
                <template v-else>
                    <Link
                        :href="login()"
                        class="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors"
                    >
                        Log in
                    </Link>
                    <Link
                        v-if="canRegister"
                        :href="register()"
                        class="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors"
                    >
                        Register
                    </Link>
                </template>
            </nav>
        </header>

        <main class="relative z-10 flex-1 flex flex-col lg:flex-row items-center w-full px-8 lg:px-16 pt-10 pb-8 lg:pb-20 lg:py-0">
            <!-- Left Side Content -->
            <div class="w-full lg:w-5/12 z-20">
                <div ref="titleRef" class="space-y-2">
                    <h1 class="text-[3.5rem] lg:text-[5rem] leading-[1.1] font-semibold tracking-tight text-foreground">
                        Crafted for <br/> 
                        <span class="font-['Playfair_Display'] italic text-muted-foreground font-medium tracking-normal">Engagement</span>
                    </h1>
                </div>
                
                <p ref="textRef" class="mt-8 text-muted-foreground max-w-[380px] text-sm lg:text-[15px] leading-relaxed">
                    Deliver unforgettable interactions with designs that pull users into your world. Scan once, track all real-time status.
                </p>
                
                <div ref="btnRef" class="mt-12 flex flex-wrap gap-4">
                    <Link
                        v-if="$page.props.auth?.user"
                        :href="dashboard()"
                        class="inline-flex items-center justify-center px-8 py-3.5 rounded-full bg-primary text-primary-foreground hover:bg-primary/90 transition-all text-sm font-medium tracking-wide shadow-lg shadow-primary/20"
                    >
                        Go to dashboard
                    </Link>
                    <Link
                        v-else
                        :href="login()"
                        class="inline-flex items-center justify-center px-8 py-3.5 rounded-full border border-sidebar-border/70 text-foreground bg-background/50 backdrop-blur hover:bg-muted/50 transition-all text-sm font-medium tracking-wide"
                    >
                        Start Now!
                    </Link>
                </div>
            </div>

            <!-- Right Side Carousel -->
            <div ref="carouselContainerRef" 
                 class="w-full lg:w-7/12 mt-12 lg:mt-0 relative h-[380px] lg:h-[450px] flex flex-col justify-end"
                 @mouseenter="isHovering = true"
                 @mouseleave="isHovering = false"
            >
                <div class="w-full overflow-hidden absolute right-0 bottom-16" style="mask-image: linear-gradient(to right, transparent, black 10%, black 75%, transparent); -webkit-mask-image: linear-gradient(to right, transparent, black 10%, black 75%, transparent);">
                    <div ref="carouselRef" class="flex gap-5 px-4 lg:pl-[10%] w-max transition-transform border-l border-transparent">
                        <div 
                            v-for="(card, index) in cards" 
                            :key="card.id"
                            class="carousel-item relative w-[220px] h-[280px] rounded-[24px] p-6 flex flex-col justify-between overflow-hidden shadow-sm bg-gradient-to-br border border-sidebar-border/70 shrink-0 transform-gpu cursor-pointer"
                            :class="[
                                card.color,
                                activeIndex === index ? 'opacity-100 scale-100 shadow-xl' : 'opacity-60 scale-[0.9] hover:opacity-100'
                            ]"
                            style="transition: opacity 0.5s ease-out, transform 0.5s ease-out, box-shadow 0.5s ease-out;"
                            @click="activeIndex = index; animateCarousel()"
                        >
                            <!-- Glassmorphism overlay to match Dashboard cards exactly -->
                            <div class="absolute inset-0 bg-background/60 backdrop-blur-xl pointer-events-none" :class="{ 'bg-background/40': activeIndex === index }"></div>
                            
                            <!-- Soft gradient for text readability -->
                            <div class="absolute inset-0 bg-gradient-to-t from-background/95 via-background/40 to-transparent pointer-events-none"></div>
                            
                            <!-- Card Content - Testimonial Style -->
                            <div class="relative z-10 space-y-4 flex-1 flex flex-col justify-end pb-2">
                                <p class="text-muted-foreground font-['Playfair_Display'] italic text-[15.5px] leading-relaxed pb-1 whitespace-pre-wrap select-none w-full">
                                    {{ card.desc }}
                                </p>
                            </div>
                            
                            <!-- Profile Section -->
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
                        <button 
                            @click="prevCard" 
                            class="w-10 h-10 rounded-full border border-sidebar-border/70 flex items-center justify-center text-muted-foreground hover:bg-muted/50 hover:text-foreground transition-all focus:outline-none"
                        >
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M15 18l-6-6 6-6"/></svg>
                        </button>
                        <button 
                            @click="nextCard" 
                            class="w-10 h-10 rounded-full border border-sidebar-border/70 flex items-center justify-center text-muted-foreground hover:bg-muted/50 hover:text-foreground transition-all relative overflow-hidden group focus:outline-none"
                        >
                            <!-- Visual progress indicator -->
                            <div 
                                class="absolute top-0 bottom-0 left-0 bg-primary/10 transition-all pointer-events-none flex items-center justify-center"
                                :style="{ width: isHovering ? '0%' : '100%' }"
                                style="transition: width 4.5s linear;"
                                :key="activeIndex"
                            ></div>
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
        </main>

        <footer class="relative z-10 w-full px-8 lg:px-16 pb-8 flex flex-col sm:flex-row justify-center lg:justify-start items-center gap-4 sm:gap-8 text-[11px] uppercase tracking-wider text-muted-foreground mt-auto">
            <div class="flex gap-8">
                <a href="https://koamishin.org" class="hover:text-foreground transition-colors">Koamishin.org</a>
            </div>
            <span class="text-sidebar-border/70 hidden sm:inline">|</span>
            <p class="text-[10px]">&copy; {{ new Date().getFullYear() }} All rights reserved Koamishin.org</p>
        </footer>
    </div>
</template>
