<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    Title,
    Tooltip,
    Legend,
    ArcElement,
} from 'chart.js';
import { Line, Bar, Pie } from 'vue-chartjs';
import { Download, TrendingUp, Users, Clock } from 'lucide-vue-next';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    Title,
    Tooltip,
    Legend,
    ArcElement
);

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reports', href: '/reports' },
];

const loading = ref(true);
const stats = ref<any>(null);

async function fetchStats() {
    loading.value = true;
    try {
        const response = await fetch('/api/reports/stats?days=30');
        stats.value = await response.json();
    } catch (e) {
        console.error('Failed to fetch stats', e);
    } finally {
        loading.value = false;
    }
}

const lineData = ref<any>(null);
const barData = ref<any>(null);
const pieData = ref<any>(null);

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
};

import gsap from 'gsap';

onMounted(async () => {
    await fetchStats();
    if (stats.value) {
        lineData.value = {
            labels: stats.value.daily.map((d: any) => d.date),
            datasets: [
                {
                    label: 'Daily Scans',
                    backgroundColor: '#10b981',
                    borderColor: '#10b981',
                    data: stats.value.daily.map((d: any) => d.count),
                    tension: 0.4,
                },
            ],
        };

        barData.value = {
            labels: Object.keys(stats.value.sections),
            datasets: [
                {
                    label: 'Scans by Section',
                    backgroundColor: '#6366f1',
                    data: Object.values(stats.value.sections),
                },
            ],
        };

        pieData.value = {
            labels: stats.value.status.map((s: any) => s.status),
            datasets: [
                {
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#6366f1'],
                    data: stats.value.status.map((s: any) => s.count),
                },
            ],
        };

        gsap.from('.report-card', {
            opacity: 0,
            y: 30,
            stagger: 0.1,
            duration: 0.8,
            ease: 'expo.out'
        });
    }
});

function exportCsv() {
    window.location.href = '/reports/export';
}
</script>

<template>
    <Head title="Reports & Analytics" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Reports & Analytics</h1>
                    <p class="text-muted-foreground">Detailed overview of attendance trends and statistics.</p>
                </div>
                <Button @click="exportCsv">
                    <Download class="mr-2 h-4 w-4" />
                    Export CSV
                </Button>
            </div>

            <div v-if="loading" class="flex h-64 items-center justify-center">
                <p>Loading analytics...</p>
            </div>

            <div v-else class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <!-- Trend Chart -->
                <div class="report-card glass-card col-span-2 rounded-2xl p-6 shadow-sm">
                    <div class="mb-4 flex items-center gap-2">
                        <TrendingUp class="h-5 w-5 text-emerald-500" />
                        <h3 class="font-semibold text-lg">Attendance Trend (30 Days)</h3>
                    </div>
                    <div class="h-64">
                        <Line :data="lineData" :options="chartOptions" v-if="lineData" />
                    </div>
                </div>

                <!-- Status Distribution -->
                <div class="report-card glass-card rounded-2xl p-6 shadow-sm">
                    <div class="mb-4 flex items-center gap-2">
                        <Clock class="h-5 w-5 text-indigo-500" />
                        <h3 class="font-semibold text-lg">Status Distribution</h3>
                    </div>
                    <div class="h-64">
                        <Pie :data="pieData" :options="chartOptions" v-if="pieData" />
                    </div>
                </div>

                <!-- Section Comparison -->
                <div class="report-card glass-card col-span-2 rounded-2xl p-6 shadow-sm">
                    <div class="mb-4 flex items-center gap-2">
                        <Users class="h-5 w-5 text-blue-500" />
                        <h3 class="font-semibold text-lg">Activity by Section</h3>
                    </div>
                    <div class="h-64">
                        <Bar :data="barData" :options="chartOptions" v-if="barData" />
                    </div>
                </div>
                
                <div class="report-card glass-card rounded-2xl p-6 shadow-sm flex flex-col justify-center items-center text-center">
                    <div class="h-12 w-12 rounded-full bg-primary/10 flex items-center justify-center mb-4 text-primary">
                        <TrendingUp class="h-6 w-6" />
                    </div>
                    <h3 class="text-lg font-bold mb-2">Need detailed logs?</h3>
                    <p class="text-sm text-muted-foreground mb-4">Download the full attendance history for all students as an Excel-compatible CSV file.</p>
                    <Button variant="outline" class="rounded-full px-6" @click="exportCsv">Download Full History</Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
