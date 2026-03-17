<script setup lang="ts">
import { onMounted, ref } from 'vue';
import gsap from 'gsap';

const qrRef = ref<HTMLElement | null>(null);
const lineRef = ref<HTMLElement | null>(null);
const meshRef = ref<HTMLElement | null>(null);
const containerRef = ref<HTMLElement | null>(null);

onMounted(() => {
    if (!containerRef.value) return;

    // Line animation
    gsap.to(lineRef.value, {
        top: '100%',
        duration: 2.5,
        ease: 'none',
        repeat: -1,
        yoyo: true,
    });

    // Mesh pulse
    gsap.to('.mesh-dot', {
        opacity: 0.2,
        duration: 'random(1, 2)',
        repeat: -1,
        yoyo: true,
        stagger: {
            amount: 1.5,
            grid: [10, 10],
            from: 'center',
        },
    });

    // QR Pulse
    gsap.to(qrRef.value, {
        scale: 1.05,
        duration: 2,
        repeat: -1,
        yoyo: true,
        ease: 'sine.inOut'
    });

    // Floating particles
    const particles = 15;
    for (let i = 0; i < particles; i++) {
        const p = document.createElement('div');
        p.className = 'absolute w-1 h-1 bg-emerald-500/40 rounded-full blur-[1px] pointer-events-none';
        containerRef.value.appendChild(p);
        
        const startX = Math.random() * 100;
        const startY = Math.random() * 100;
        
        gsap.set(p, { left: `${startX}%`, top: `${startY}%` });
        
        gsap.to(p, {
            x: 'random(-50, 50)',
            y: 'random(-50, 50)',
            opacity: 0,
            duration: 'random(3, 6)',
            repeat: -1,
            ease: 'sine.inOut',
            delay: Math.random() * 5
        });
    }
});
</script>

<template>
    <div ref="containerRef" class="relative w-full h-full flex items-center justify-center p-8 overflow-hidden rounded-3xl">
        <!-- Technical Mesh Grid -->
        <div ref="meshRef" class="absolute inset-0 grid grid-cols-10 grid-rows-10 gap-x-8 gap-y-8 p-4 opacity-10">
            <div v-for="i in 100" :key="i" class="mesh-dot w-1 h-1 bg-foreground/50 rounded-full"></div>
        </div>

        <!-- Scanning Card -->
        <div class="relative w-full max-w-[280px] aspect-[3/4] bg-background/40 backdrop-blur-2xl rounded-3xl border border-white/20 dark:border-white/10 shadow-2xl flex flex-col items-center justify-center gap-8 overflow-hidden group">
            
            <!-- Scanning Line -->
            <div ref="lineRef" class="absolute w-full h-[2px] top-0 left-0 z-20 overflow-visible">
                <div class="absolute inset-0 bg-emerald-500 shadow-[0_0_15px_2px_rgba(16,185,129,0.8)]"></div>
                <!-- Line glow trail -->
                <div class="absolute bottom-full left-0 w-full h-20 bg-gradient-to-t from-emerald-500/20 to-transparent"></div>
            </div>

            <!-- Stylized QR Code -->
            <div ref="qrRef" class="relative z-10 w-32 h-32 p-3 bg-white/5 rounded-2xl border border-white/10 flex items-center justify-center shadow-inner group-hover:border-emerald-500/30 transition-colors duration-500">
                <div class="grid grid-cols-3 grid-rows-3 gap-2 w-full h-full opacity-80">
                    <div class="border-2 border-foreground/40 rounded-sm m-1"></div>
                    <div class="border-2 border-foreground/40 rounded-sm m-1"></div>
                    <div class="bg-foreground/20 rounded-sm m-1"></div>
                    <div class="bg-emerald-500/20 rounded-sm m-1 group-hover:bg-emerald-500/40 transition-colors"></div>
                    <div class="border-2 border-foreground/40 rounded-sm m-1"></div>
                    <div class="bg-foreground/20 rounded-sm m-1"></div>
                    <div class="bg-foreground/20 rounded-sm m-1"></div>
                    <div class="bg-foreground/20 rounded-sm m-1"></div>
                    <div class="border-2 border-foreground/40 rounded-sm m-1"></div>
                </div>
                
                <!-- Corner Decorations -->
                <div class="absolute -top-1 -left-1 w-4 h-4 border-t-2 border-l-2 border-emerald-500"></div>
                <div class="absolute -top-1 -right-1 w-4 h-4 border-t-2 border-r-2 border-emerald-500"></div>
                <div class="absolute -bottom-1 -left-1 w-4 h-4 border-b-2 border-l-2 border-emerald-500"></div>
                <div class="absolute -bottom-1 -right-1 w-4 h-4 border-b-2 border-r-2 border-emerald-500"></div>
            </div>

            <!-- Data HUD elements -->
            <div class="w-full space-y-3 px-8 z-10">
                <div class="flex items-center justify-between">
                    <div class="h-1 w-20 bg-foreground/20 rounded-full overflow-hidden">
                        <div class="h-full bg-emerald-500 w-1/2 animate-[pulse_2s_infinite]"></div>
                    </div>
                    <span class="text-[8px] font-mono text-muted-foreground uppercase tracking-widest">Scanning...</span>
                </div>
                <div class="h-1.5 w-full bg-foreground/10 rounded-full flex gap-1">
                    <div v-for="i in 8" :key="i" class="h-full flex-1 rounded-full" :class="i < 6 ? 'bg-foreground/10' : 'bg-transparent'"></div>
                </div>
                <div class="flex justify-center gap-4 pt-2">
                    <div class="w-8 h-1 bg-foreground/5 rounded-full"></div>
                    <div class="w-12 h-1 bg-foreground/5 rounded-full"></div>
                </div>
            </div>

            <!-- Internal glow -->
            <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 to-transparent pointer-events-none"></div>
        </div>
        
        <!-- Background Orbs -->
        <div class="absolute top-1/4 -right-1/4 w-64 h-64 bg-emerald-500/10 rounded-full blur-[100px] animate-pulse"></div>
        <div class="absolute bottom-1/4 -left-1/4 w-64 h-64 bg-primary/5 rounded-full blur-[100px] animate-pulse" style="animation-delay: 1s"></div>
    </div>
</template>

<style scoped>
.mesh-dot {
    transition: opacity 0.5s ease;
}
</style>
