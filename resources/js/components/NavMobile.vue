<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChartBar, LayoutGrid, MessagesSquare, Star, User } from 'lucide-vue-next';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { dashboard } from '@/routes';
import { index as commentsIndex } from '@/routes/comments';
import { index as ratingsIndex } from '@/routes/ratings';
import { index as reportsIndex } from '@/routes/reports';
import { edit as profileEdit } from '@/routes/profile';
import type { NavItem } from '@/types';

const { isCurrentUrl } = useCurrentUrl();

const navItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
        icon: LayoutGrid,
    },
    {
        title: 'Comments',
        href: commentsIndex().url,
        icon: MessagesSquare,
    },
    {
        title: 'Ratings',
        href: ratingsIndex().url,
        icon: Star,
    },
    {
        title: 'Reports',
        href: reportsIndex().url,
        icon: ChartBar,
    },
];
</script>

<template>
    <nav class="fixed bottom-0 left-0 z-50 w-full border-t border-sidebar-border bg-white/80 p-2 backdrop-blur-lg dark:bg-neutral-900/80 md:hidden">
        <div class="mx-auto flex max-w-lg items-center justify-around gap-1">
            <Link
                v-for="item in navItems"
                :key="item.title"
                :href="item.href"
                class="group flex flex-1 flex-col items-center gap-1 py-1 transition-all active:scale-95"
                :class="isCurrentUrl(item.href) ? 'text-blue-600 dark:text-blue-400' : 'text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100'"
            >
                <div class="relative flex items-center justify-center">
                    <component 
                        :is="item.icon" 
                        class="size-6 transition-transform group-hover:scale-110" 
                    />
                    <div 
                        v-if="isCurrentUrl(item.href)" 
                        class="absolute -bottom-1.5 h-1 w-1 rounded-full bg-current"
                    ></div>
                </div>
                <span class="text-[10px] font-medium leading-none">{{ item.title }}</span>
            </Link>

            <Link
                :href="profileEdit().url"
                class="group flex flex-1 flex-col items-center gap-1 py-1 transition-all active:scale-95"
                :class="isCurrentUrl(profileEdit().url) ? 'text-blue-600 dark:text-blue-400' : 'text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100'"
            >
                <div class="relative flex items-center justify-center">
                    <User class="size-6 transition-transform group-hover:scale-110" />
                    <div 
                        v-if="isCurrentUrl(profileEdit().url)" 
                        class="absolute -bottom-1.5 h-1 w-1 rounded-full bg-current"
                    ></div>
                </div>
                <span class="text-[10px] font-medium leading-none">Profile</span>
            </Link>
        </div>
        
        <!-- Safe Area Inset for iOS -->
        <div class="h-[env(safe-area-inset-bottom)]"></div>
    </nav>
</template>
