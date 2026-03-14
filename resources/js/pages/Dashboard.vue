<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import { nextTick } from 'vue';
import gsap from 'gsap';
import jsQR from 'jsqr';
import QRCode from 'qrcode';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Users, Scan, CheckCircle2, AlertCircle } from 'lucide-vue-next';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import commentsRoutes from '@/routes/comments';
import ratingsRoutes from '@/routes/ratings';

type AttendanceRecord = {
    id: number;
    status: string;
    scanned_at: string;
};

type Student = {
    id: number;
    name: string;
    student_number: string;
    email?: string | null;
    section?: string | null;
    qr_token: string;
    schedule?: { day: string; start: string; end: string }[];
    today_statuses?: string[];
    latest_attendance?: {
        id: number;
        status: string;
        scanned_at: string;
    } | null;
    deleted_at?: string | null;
};

const daysOfWeek = [
    'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'
];

type PageProps = {
    students: Student[];
    trashedStudents: Student[];
};

const props = defineProps<PageProps>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

const page = usePage();

const students = computed(() => props.students ?? []);

const createModalOpen = ref(false);
const editModalOpen = ref(false);
const scanModalOpen = ref(false);
const activeTab = ref<'active' | 'deleted'>('active');
const selectedStudent = ref<Student | null>(null);
const qrModalOpen = ref(false);

const studentInfoModalOpen = ref(false);
const infoStudent = ref<Student | null>(null);
const attendanceHistory = ref<AttendanceRecord[]>([]);
const historyExpanded = ref(false);
const historyLoading = ref(false);

// Group attendance records by local date (most-recent date first)
const groupedAttendanceHistory = computed(() => {
    const groups: { date: string; label: string; records: AttendanceRecord[] }[] = [];
    const seen = new Map<string, AttendanceRecord[]>();

    const list = historyExpanded.value
        ? attendanceHistory.value
        : attendanceHistory.value.slice(0, 10);

    for (const record of list) {
        const d = new Date(record.scanned_at);
        const key = d.toLocaleDateString();
        if (!seen.has(key)) {
            const isToday = key === new Date().toLocaleDateString();
            const isYesterday = key === new Date(Date.now() - 86400000).toLocaleDateString();
            const label = isToday ? 'Today' : isYesterday ? 'Yesterday' : key;
            seen.set(key, []);
            groups.push({ date: key, label, records: seen.get(key)! });
        }
        seen.get(key)!.push(record);
    }
    return groups;
});

const name = ref('');
const studentNumber = ref('');
const email = ref('');
const section = ref('');
const schedules = ref<{ day: string; start: string; end: string }[]>([
    { day: 'Monday', start: '', end: '' },
]);

const editName = ref('');
const editStudentNumber = ref('');
const editEmail = ref('');
const editSection = ref('');
const editSchedules = ref<{ day: string; start: string; end: string }[]>([
    { day: 'Monday', start: '', end: '' },
]);
const editingStudentId = ref<number | null>(null);

const submitting = ref(false);
const formErrors = ref<Record<string, string[]>>({});

const cardsRef = ref<HTMLDivElement | null>(null);
const tableRef = ref<HTMLDivElement | null>(null);

const videoRef = ref<HTMLVideoElement | null>(null);
const scanning = ref(false);
const scanError = ref<string | null>(null);
const lastScanResult = ref<{
    student: Student;
    scanned_at: string;
    status: string;
} | null>(null);
const scanFeedback = ref<'success' | 'error' | null>(null);
const scanResultModalOpen = ref(false);

const confirmModalOpen = ref(false);
const confirmTitle = ref('');
const confirmDescription = ref('');
const confirmAction = ref<(() => void) | null>(null);
const confirmIsDestructive = ref(false);

function showConfirm(title: string, description: string, action: () => void, isDestructive = false) {
    confirmTitle.value = title;
    confirmDescription.value = description;
    confirmAction.value = action;
    confirmIsDestructive.value = isDestructive;
    confirmModalOpen.value = true;
}

function handleConfirm() {
    if (confirmAction.value) {
        confirmAction.value();
    }
    confirmModalOpen.value = false;
    confirmAction.value = null;
}
let scanInterval: number | null = null;
let mediaStream: MediaStream | null = null;

function resetForm() {
    name.value = '';
    studentNumber.value = '';
    email.value = '';
    section.value = '';
    schedules.value = [{ day: 'Monday', start: '', end: '' }];
    formErrors.value = {};
}

function openCreateModal() {
    resetForm();
    createModalOpen.value = true;
}

function closeCreateModal() {
    createModalOpen.value = false;
}

async function submitStudent() {
    submitting.value = true;
    formErrors.value = {};

    router.post(
        '/students',
        {
            name: name.value,
            student_number: studentNumber.value,
            email: email.value || null,
            section: section.value || null,
            schedule: schedules.value,
        },
        {
            onError: (errors) => {
                formErrors.value = errors as any;
            },
            onSuccess: () => {
                closeCreateModal();
            },
            onFinish: () => {
                submitting.value = false;
            },
            preserveScroll: true,
        },
    );
}

function addScheduleSlot() {
    schedules.value.push({ day: 'Monday', start: '', end: '' });
}

function removeScheduleSlot(index: number) {
    if (schedules.value.length === 1) return;
    schedules.value.splice(index, 1);
}

function deleteStudent(id: number) {
    showConfirm(
        'Delete Student?',
        'Are you sure you want to move this student to the Trash? You can restore them later.',
        () => {
            router.delete(`/students/${id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    closeStudentInfoModal();
                }
            });
        },
        true
    );
}

function restoreStudent(id: number) {
    router.post(`/students/${id}/restore`, {}, {
        preserveScroll: true,
    });
}

function forceDeleteStudent(id: number) {
    showConfirm(
        'Permanently Delete?',
        'This will permanently delete the student and all their records. This action cannot be undone. Are you sure?',
        () => {
            router.delete(`/students/${id}/force-delete`, {
                preserveScroll: true,
            });
        },
        true
    );
}

function openStudentInfoModal(student: Student) {
    infoStudent.value = student;
    attendanceHistory.value = [];
    historyExpanded.value = false;
    historyLoading.value = true;
    studentInfoModalOpen.value = true;

    window.fetch(`/students/${student.id}/attendance`, {
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN':
                (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)
                    ?.content ?? '',
        },
    })
        .then((r) => r.json())
        .then((data: { history: AttendanceRecord[] }) => {
            attendanceHistory.value = data.history;
        })
        .catch(() => {})
        .finally(() => {
            historyLoading.value = false;
        });
}

function closeStudentInfoModal() {
    studentInfoModalOpen.value = false;
    infoStudent.value = null;
    attendanceHistory.value = [];
}

function openEditFromInfo() {
    const student = infoStudent.value;
    if (!student) return;
    closeStudentInfoModal();
    // small delay so modal closes first
    setTimeout(() => openEditModal(student), 80);
}

function openQrFromInfo() {
    const student = infoStudent.value;
    if (!student) return;
    closeStudentInfoModal();
    // small delay so modal closes first
    setTimeout(() => openQrModal(student), 80);
}

function openEditModal(student: Student) {
    editingStudentId.value = student.id;
    editName.value = student.name;
    editStudentNumber.value = student.student_number;
    editEmail.value = student.email || '';
    editSection.value = student.section || '';
    editSchedules.value =
        student.schedule && student.schedule.length > 0
            ? student.schedule.map((s) => ({ day: s.day || 'Monday', start: s.start, end: s.end }))
            : [{ day: 'Monday', start: '', end: '' }];
    formErrors.value = {};
    editModalOpen.value = true;
}

function formatDateTime(iso: string) {
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;
    return `${d.toLocaleDateString()} ${d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
}

function updateLatestStatus(student: Student, status: string) {
    if (!student.latest_attendance?.id) return;

    router.put(
        `/attendance/${student.latest_attendance.id}`,
        { status },
        {
            preserveScroll: true,
            onSuccess: () => {
                router.visit(dashboard().url, {
                    only: ['students'],
                    preserveScroll: true,
                });
            },
        },
    );
}

function closeEditModal() {
    editModalOpen.value = false;
    editingStudentId.value = null;
}

function addEditScheduleSlot() {
    editSchedules.value.push({ day: 'Monday', start: '', end: '' });
}

function removeEditScheduleSlot(index: number) {
    if (editSchedules.value.length === 1) return;
    editSchedules.value.splice(index, 1);
}

async function submitEditStudent() {
    if (!editingStudentId.value) return;

    submitting.value = true;
    formErrors.value = {};

    router.put(
        `/students/${editingStudentId.value}`,
        {
            name: editName.value,
            student_number: editStudentNumber.value,
            email: editEmail.value || null,
            section: editSection.value || null,
            schedule: editSchedules.value,
        },
        {
            onError: (errors) => {
                formErrors.value = errors as any;
            },
            onSuccess: () => {
                closeEditModal();
            },
            onFinish: () => {
                submitting.value = false;
            },
            preserveScroll: true,
        },
    );
}

function openQrModal(student: Student) {
    selectedStudent.value = student;
    qrModalOpen.value = true;
}

function closeQrModal() {
    qrModalOpen.value = false;
    selectedStudent.value = null;
}

function regenerateQr() {
    if (!selectedStudent.value) return;

    router.post(
        `/students/${selectedStudent.value.id}/qr/regenerate`,
        {},
        {
            onSuccess: () => {
                router.visit(dashboard().url, {
                    only: ['students'],
                    preserveScroll: true,
                    onSuccess: (page) => {
                        const updated = (page.props as unknown as PageProps)
                            .students.find(
                                (s) => s.id === selectedStudent.value?.id,
                            );
                        if (updated) {
                            selectedStudent.value = updated;
                            nextTick(() => drawQrToCanvas());
                        }
                    },
                });
            },
        },
    );
}

function qrData(token: string) {
    return token;
}

async function drawQrToCanvas() {
    const canvas = document.querySelector<HTMLCanvasElement>('#qr-canvas');
    const student = selectedStudent.value;
    if (!canvas || !student?.qr_token) return;

    try {
        await QRCode.toCanvas(canvas, student.qr_token, {
            width: 192,
            margin: 1,
            color: { dark: '#000000', light: '#ffffff' },
        });
    } catch (e) {
        console.error('QR code draw failed:', e);
    }
}

watch(
    [qrModalOpen, selectedStudent],
    ([open, student]) => {
        if (open && student) {
            nextTick(() => drawQrToCanvas());
        }
    },
    { immediate: true },
);

function downloadQr() {
    const canvas = document.querySelector<HTMLCanvasElement>('#qr-canvas');
    if (!canvas || !selectedStudent.value) return;

    const link = document.createElement('a');
    link.href = canvas.toDataURL('image/png');
    link.download = `${selectedStudent.value.name}-qr.png`;
    link.click();
}

async function openScanModal() {
    scanError.value = null;
    lastScanResult.value = null;
    scanModalOpen.value = true;
    await startCamera();
}

function closeScanModal() {
    stopCamera();
    scanModalOpen.value = false;
    // Don't auto-reset scanResultModalOpen here so users can read the status
}

function closeScanResultModal() {
    scanResultModalOpen.value = false;
    // When closing result, if scanner is still open, resume it
    if (scanModalOpen.value && mediaStream) {
        startScanningLoop();
    }
}

async function startCamera() {
    if (!navigator.mediaDevices?.getUserMedia) {
        scanError.value = 'Camera not supported in this browser.';
        return;
    }

    try {
        mediaStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' },
        });
        if (!videoRef.value) return;
        videoRef.value.srcObject = mediaStream;
        await videoRef.value.play();
        startScanningLoop();
    } catch (e) {
        scanError.value = 'Unable to access camera.';
    }
}

function stopCamera() {
    if (scanInterval !== null) {
        window.clearInterval(scanInterval);
        scanInterval = null;
    }
    if (mediaStream) {
        mediaStream.getTracks().forEach((track) => track.stop());
        mediaStream = null;
    }
    scanning.value = false;
}

function startScanningLoop() {
    if (!videoRef.value) return;

    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    if (!ctx) {
        scanError.value = 'Unable to initialize scanner.';
        return;
    }

    scanning.value = true;

    scanInterval = window.setInterval(async () => {
        if (!videoRef.value) return;
        if (videoRef.value.readyState !== videoRef.value.HAVE_ENOUGH_DATA) {
            return;
        }

        canvas.width = videoRef.value.videoWidth;
        canvas.height = videoRef.value.videoHeight;
        ctx.drawImage(videoRef.value, 0, 0, canvas.width, canvas.height);

        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const code = jsQR(imageData.data, canvas.width, canvas.height);

        if (!code || !code.data) {
            return;
        }

        const token = code.data.trim();
        if (!token) return;

        scanning.value = false;
        if (scanInterval !== null) {
            window.clearInterval(scanInterval);
            scanInterval = null;
        }

        try {
            const response = await window.fetch('/attendance/scan', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document
                        .querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)
                        ?.content ?? '',
                },
                body: JSON.stringify({ token }),
            });

            if (!response.ok) {
                const contentType = response.headers.get('content-type') || '';
                if (contentType.includes('application/json')) {
                    const err = (await response.json()) as { message?: string };
                    throw new Error(
                        err?.message ||
                            `Scan failed (HTTP ${response.status}).`,
                    );
                }

                const text = await response.text();
                throw new Error(
                    `Scan failed (HTTP ${response.status}). ${text.slice(0, 120)}`,
                );
            }

            const data = await response.json();
            lastScanResult.value = {
                student: data.student,
                scanned_at: data.attendance.scanned_at,
                status: data.attendance.status,
            };
            // Optimistically update the matching student's today_statuses in the prop
            const matchedStudent = props.students.find((s) => s.id === data.student.id);
            if (matchedStudent) {
                if (!matchedStudent.today_statuses) {
                    (matchedStudent as any).today_statuses = [];
                }
                if (!matchedStudent.today_statuses!.includes(data.attendance.status)) {
                    matchedStudent.today_statuses!.push(data.attendance.status);
                }
                matchedStudent.latest_attendance = {
                    id: data.attendance.id,
                    status: data.attendance.status,
                    scanned_at: data.attendance.scanned_at,
                };
            }
            scanError.value = null;
            scanFeedback.value = 'success';
            scanResultModalOpen.value = true;
            setTimeout(() => { scanFeedback.value = null; }, 1500);
        } catch (error) {
            scanError.value =
                error instanceof Error
                    ? error.message
                    : 'Failed to record attendance.';
            scanFeedback.value = 'error';
            scanResultModalOpen.value = true;
            setTimeout(() => { scanFeedback.value = null; }, 1500);
        }
    }, 400);
}

onMounted(() => {
    // 1. Enter and Hover Animations for Cards
    if (cardsRef.value) {
        const cards = cardsRef.value.querySelectorAll<HTMLElement>('[data-card]');
        
        // Wrap with a perspective container
        gsap.set(cardsRef.value, { perspective: 1000 });

        gsap.from(cards, {
            opacity: 0,
            y: 50,
            rotationX: -45,
            z: -100,
            duration: 1,
            stagger: 0.15,
            ease: 'back.out(1.5)',
        });
        
        cards.forEach((card) => {
            gsap.set(card, { transformStyle: "preserve-3d" });

            card.addEventListener('mousemove', (e: MouseEvent) => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                
                const rotateX = ((y - centerY) / centerY) * -10;
                const rotateY = ((x - centerX) / centerX) * 10;
                
                gsap.to(card, {
                    rotationX: rotateX,
                    rotationY: rotateY,
                    scale: 1.05,
                    z: 30,
                    zIndex: 50,
                    boxShadow: '0 30px 40px -10px rgba(0, 0, 0, 0.3), 0 15px 15px -10px rgba(0, 0, 0, 0.1)',
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
                    boxShadow: '0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1)',
                    duration: 0.6,
                    ease: 'elastic.out(1, 0.3)'
                });
            });
        });
    }

    // 2. Table and Row Entrance
    if (tableRef.value) {
        gsap.set(tableRef.value, { perspective: 1000 });

        gsap.from(tableRef.value, {
            opacity: 0,
            y: 40,
            rotationX: 20,
            z: -50,
            duration: 1,
            delay: 0.2,
            ease: 'power3.out',
        });
        
        const rows = tableRef.value.querySelectorAll('tbody tr');
        rows.forEach(row => gsap.set(row, { transformStyle: "preserve-3d" }));

        gsap.from(rows, {
            opacity: 0,
            x: -30,
            filter: 'blur(10px)',
            duration: 0.8,
            stagger: 0.04,
            delay: 0.3,
            ease: 'expo.out',
        });
    }

    // 3. Status Badge Pulsing
    gsap.to('.status-pulse', {
        scale: 1.05,
        opacity: 0.8,
        duration: 1.5,
        repeat: -1,
        yoyo: true,
        ease: 'sine.inOut'
    });

    // 3. Button Press Micro-interactions
    const buttons = document.querySelectorAll('button');
    buttons.forEach((btn) => {
        gsap.set(btn, { transformStyle: "preserve-3d" });
        btn.addEventListener('mousedown', () => {
            gsap.to(btn, { scale: 0.95, z: -10, duration: 0.1, ease: 'power1.out' });
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
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
            <div
                ref="cardsRef"
                class="grid auto-rows-min gap-4 grid-cols-2 lg:grid-cols-4"
            >
                <div
                    data-card
                    class="glass-card relative overflow-hidden rounded-2xl p-5 shadow-sm transition-all hover:shadow-xl hover:-translate-y-1"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p
                                class="text-[10px] font-bold uppercase tracking-[0.1em] text-muted-foreground/80"
                            >
                                Total Students
                            </p>
                            <p class="mt-1 text-4xl font-bold tracking-tight">
                                {{ students.length }}
                            </p>
                        </div>
                        <div class="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center">
                            <Users class="h-5 w-5 text-primary" />
                        </div>
                    </div>
                </div>

                <div
                    data-card
                    class="glass-card relative overflow-hidden rounded-2xl p-5 shadow-sm col-span-2 lg:col-span-3"
                >
                    <div class="flex h-full flex-col justify-center gap-1">
                        <p
                            class="text-[10px] font-bold uppercase tracking-[0.1em] text-muted-foreground/80"
                        >
                            Security & Registry
                        </p>
                        <p class="text-[11px] leading-relaxed text-muted-foreground">
                            Encrypted QR tokens ensure data integrity. Total Active: {{ students.length }} | Trashed: {{ props.trashedStudents.length }}
                        </p>
                    </div>
                </div>
            </div>

            <div
                ref="tableRef"
                class="glass-card relative flex-1 overflow-hidden rounded-2xl shadow-sm md:min-h-min"
            >
                <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b p-4 gap-4">
                    <div class="flex items-center gap-4">
                        <div class="flex rounded-lg bg-muted/50 p-1">
                            <button
                                class="rounded-md px-3 py-1 text-xs font-medium transition-all"
                                :class="activeTab === 'active' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                                @click="activeTab = 'active'"
                            >
                                Active Students
                            </button>
                            <button
                                class="rounded-md px-3 py-1 text-xs font-medium transition-all"
                                :class="activeTab === 'deleted' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                                @click="activeTab = 'deleted'"
                            >
                                Deleted Students ({{ props.trashedStudents.length }})
                            </button>
                        </div>
                    </div>
                    <Button size="sm" class="rounded-full" @click="openCreateModal" v-if="activeTab === 'active'">
                        Add student
                    </Button>
                </div>

                <div class="max-h-[520px] overflow-x-auto overflow-y-auto w-full">
                    <table class="min-w-full text-left text-sm whitespace-nowrap">
                        <thead
                            class="sticky top-0 z-10 border-b bg-background/80 backdrop-blur"
                        >
                            <tr>
                                <th class="px-4 py-2 text-xs font-medium">
                                    Name
                                </th>
                                <th class="px-4 py-2 text-xs font-medium">
                                    Student #
                                </th>
                                <!-- Status columns -->
                                <th class="px-4 py-2 text-xs font-medium text-center text-emerald-600 dark:text-emerald-400">
                                    Present
                                </th>
                                <th class="px-4 py-2 text-xs font-medium text-center text-amber-500 dark:text-amber-400">
                                    Late
                                </th>
                                <th class="px-4 py-2 text-xs font-medium text-center text-blue-500 dark:text-blue-400">
                                    Time Out
                                </th>
                                <th class="px-4 py-2 text-xs font-medium text-center text-rose-500 dark:text-rose-400">
                                    Absent
                                </th>
                                <th class="px-4 py-2 text-xs font-medium">
                                    Section
                                </th>
                                <th class="px-4 py-2 text-xs font-medium">
                                    Email
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-if="students.length === 0"
                                class="border-b last:border-b-0"
                            >
                                <td
                                    colspan="9"
                                    class="px-4 py-6 text-center text-xs text-muted-foreground"
                                >
                                    No students yet. Use the
                                    <span class="font-semibold">
                                        Add student
                                    </span>
                                    button to create one.
                                </td>
                            </tr>
                            <tr
                                v-for="student in (activeTab === 'active' ? students : props.trashedStudents)"
                                :key="student.id"
                                class="border-b transition-colors hover:bg-muted/40 last:border-b-0 cursor-pointer"
                                @click="activeTab === 'active' ? openStudentInfoModal(student) : null"
                            >
                                <td class="px-4 py-2 text-sm font-medium">
                                    <span class="flex items-center gap-1.5">
                                        {{ student.name }}
                                        <div 
                                            v-if="activeTab === 'active'"
                                            class="h-1.5 w-1.5 rounded-full status-pulse"
                                            :class="[
                                                student.latest_attendance?.status === 'Present' ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.6)]' :
                                                student.latest_attendance?.status === 'Late'    ? 'bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.6)]' :
                                                'bg-muted-foreground/30'
                                            ]"
                                        ></div>
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-xs text-muted-foreground">
                                    {{ student.student_number }}
                                </td>
                                <!-- Status indicator columns (active students) -->
                                <template v-if="activeTab === 'active'">
                                    <!-- Present -->
                                    <td class="px-4 py-2 text-center" @click.stop>
                                        <span v-if="student.today_statuses?.includes('Present')"
                                            class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-100 dark:bg-emerald-900/40"
                                            title="Present"
                                        >
                                            <svg class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        </span>
                                        <span v-else class="inline-block w-4 h-px bg-muted-foreground/20"></span>
                                    </td>
                                    <!-- Late -->
                                    <td class="px-4 py-2 text-center" @click.stop>
                                        <span v-if="student.today_statuses?.includes('Late')"
                                            class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-100 dark:bg-amber-900/40"
                                            title="Late"
                                        >
                                            <svg class="w-3.5 h-3.5 text-amber-500 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        </span>
                                        <span v-else class="inline-block w-4 h-px bg-muted-foreground/20"></span>
                                    </td>
                                    <!-- Time Out -->
                                    <td class="px-4 py-2 text-center" @click.stop>
                                        <span v-if="student.today_statuses?.includes('Time Out')"
                                            class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-100 dark:bg-blue-900/40"
                                            title="Time Out"
                                        >
                                            <svg class="w-3.5 h-3.5 text-blue-500 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        </span>
                                        <span v-else class="inline-block w-4 h-px bg-muted-foreground/20"></span>
                                    </td>
                                    <!-- Absent: show when no scans today, or explicitly marked Absent -->
                                    <td class="px-4 py-2 text-center" @click.stop>
                                        <span v-if="!student.today_statuses?.length || student.today_statuses?.includes('Absent')"
                                            class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-rose-100 dark:bg-rose-900/40"
                                            title="Absent"
                                        >
                                            <svg class="w-3.5 h-3.5 text-rose-500 dark:text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        </span>
                                        <span v-else class="inline-block w-4 h-px bg-muted-foreground/20"></span>
                                    </td>
                                </template>
                                <!-- Deleted students: span across 4 status cols + show restore/delete buttons -->
                                <template v-else>
                                    <td colspan="4" class="px-4 py-2" @click.stop>
                                        <div class="flex items-center gap-2">
                                            <Button size="icon-sm" variant="ghost" class="h-8 w-8 text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50" title="Restore" @click="restoreStudent(student.id)">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M3 21v-5h5"/></svg>
                                            </Button>
                                            <Button size="icon-sm" variant="ghost" class="h-8 w-8 text-rose-600 hover:text-rose-700 hover:bg-rose-50" title="Delete Permanently" @click="forceDeleteStudent(student.id)">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
                                            </Button>
                                        </div>
                                    </td>
                                </template>
                                <td class="px-4 py-2 text-xs text-muted-foreground">
                                    {{ student.section || '—' }}
                                </td>
                                <td class="px-4 py-2 text-xs text-muted-foreground" v-if="activeTab === 'active'">
                                    {{ student.email || '—' }}
                                </td>
                                <td class="px-4 py-2 text-xs text-rose-500 font-medium" v-else>
                                    Deleted {{ formatDateTime(student.deleted_at!) }}
                                </td>
                                <!-- <td class="px-4 py-2 text-right text-xs text-muted-foreground" @click.stop>
                                    <button
                                        type="button"
                                        class="underline-offset-2 hover:underline"
                                        @click="openQrModal(student)"
                                    >
                                        View QR
                                    </button>
                                </td> -->
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <Dialog v-model:open="createModalOpen">
                <DialogContent class="max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            Add student
                        </DialogTitle>
                    </DialogHeader>

                    <form class="space-y-3" @submit.prevent="submitStudent">
                        <div class="space-y-1.5">
                            <label class="text-xs font-medium">
                                Full name
                            </label>
                            <Input v-model="name" placeholder="e.g. Juan Dela Cruz" />
                            <p
                                v-if="formErrors.name"
                                class="text-xs text-destructive"
                            >
                                {{ Array.isArray(formErrors.name) ? formErrors.name[0] : formErrors.name }}
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-medium">
                                Student number
                            </label>
                            <Input
                                v-model="studentNumber"
                                placeholder="e.g. 2026-0001"
                            />
                            <p
                                v-if="formErrors.student_number"
                                class="text-xs text-destructive"
                            >
                                {{ Array.isArray(formErrors.student_number) ? formErrors.student_number[0] : formErrors.student_number }}
                            </p>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="space-y-1.5">
                                <label class="text-xs font-medium">
                                    Section
                                </label>
                                <Input v-model="section" placeholder="e.g. BSIT-3A" />
                                <p
                                    v-if="formErrors.section"
                                    class="text-xs text-destructive"
                                >
                                    {{ Array.isArray(formErrors.section) ? formErrors.section[0] : formErrors.section }}
                                </p>
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-medium">
                                    Email
                                </label>
                                <Input
                                    v-model="email"
                                    type="email"
                                    placeholder="Optional"
                                />
                                <p
                                    v-if="formErrors.email"
                                    class="text-xs text-destructive"
                                >
                                    {{ Array.isArray(formErrors.email) ? formErrors.email[0] : formErrors.email }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-medium">
                                    Time slots
                                </label>
                                <Button
                                    type="button"
                                    size="icon-sm"
                                    variant="outline"
                                    @click="addScheduleSlot"
                                >
                                    +
                                </Button>
                            </div>
                            <p class="text-[11px] text-muted-foreground">
                                Example:
                                10:00–12:00,&nbsp;13:00–14:00,&nbsp;15:00–17:00.
                                A 15-minute grace period is applied.
                            </p>

                            <div class="space-y-2">
                                <div
                                    v-for="(slot, index) in schedules"
                                    :key="index"
                                    class="flex items-center gap-2"
                                >
                                    <select
                                        v-model="slot.day"
                                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-xs shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        <option v-for="d in daysOfWeek" :key="d" :value="d">{{ d }}</option>
                                    </select>
                                    <Input
                                        v-model="slot.start"
                                        type="time"
                                        class="text-xs"
                                    />
                                    <span class="text-xs text-muted-foreground px-1">
                                        to
                                    </span>
                                    <Input
                                        v-model="slot.end"
                                        type="time"
                                        class="text-xs"
                                    />
                                    <Button
                                        v-if="schedules.length > 1"
                                        type="button"
                                        size="icon-sm"
                                        variant="ghost"
                                        @click="removeScheduleSlot(index)"
                                    >
                                        ×
                                    </Button>
                                </div>
                            </div>
                            <div
                                v-if="Object.keys(formErrors).some(k => k.startsWith('schedule'))"
                                class="mt-2 space-y-1 rounded-md bg-destructive/5 p-2"
                            >
                                <p v-for="(err, key) in formErrors" :key="key" v-show="key.startsWith('schedule')" class="text-[10px] text-destructive leading-tight">
                                    • {{ Array.isArray(err) ? err[0] : err }}
                                </p>
                            </div>
                        </div>

                        <DialogFooter class="mt-4 flex justify-end gap-2">
                            <DialogClose as-child>
                                <Button type="button" variant="outline">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button type="submit" :disabled="submitting">
                                {{ submitting ? 'Saving…' : 'Save student' }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <!-- Student Info Modal -->
            <Dialog v-model:open="studentInfoModalOpen">
                <DialogContent class="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Student Info</DialogTitle>
                    </DialogHeader>

                    <div v-if="infoStudent" class="space-y-4">
                        <!-- Profile card -->
                        <div class="rounded-lg border bg-muted/30 p-4 space-y-2">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-base font-semibold leading-tight">
                                        {{ infoStudent.name }}
                                    </p>
                                    <p class="text-xs text-muted-foreground mt-0.5">
                                        {{ infoStudent.student_number }}
                                        <span v-if="infoStudent.section"> · {{ infoStudent.section }}</span>
                                    </p>
                                    <p v-if="infoStudent.email" class="text-xs text-muted-foreground">
                                        {{ infoStudent.email }}
                                    </p>
                                </div>
                                <!-- Today's status badge -->
                                <span
                                    v-if="infoStudent.latest_attendance"
                                    :class="[
                                        'shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-semibold',
                                        infoStudent.latest_attendance.status === 'Present' ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' :
                                        infoStudent.latest_attendance.status === 'Late'    ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400' :
                                        infoStudent.latest_attendance.status === 'Absent'  ? 'bg-red-500/15 text-red-600 dark:text-red-400' :
                                                                                             'bg-muted text-muted-foreground'
                                    ]"
                                >
                                    {{ infoStudent.latest_attendance.status }}
                                </span>
                                <span
                                    v-else
                                    class="shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-semibold bg-muted text-muted-foreground"
                                >
                                    No record today
                                </span>
                            </div>

                            <!-- Schedule -->
                            <div v-if="infoStudent.schedule && infoStudent.schedule.length > 0" class="pt-1">
                                <p class="text-[11px] font-medium uppercase text-muted-foreground mb-1">Schedule</p>
                                <div class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="(slot, i) in infoStudent.schedule"
                                        :key="i"
                                        class="rounded-md border px-2 py-0.5 text-[11px] font-mono"
                                    >
                                        {{ slot.day }}: {{ slot.start }} – {{ slot.end }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Attendance History -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    Attendance History
                                </p>
                                <button
                                    v-if="attendanceHistory.length > 5"
                                    type="button"
                                    class="flex items-center gap-1 text-[11px] text-primary hover:underline"
                                    @click="historyExpanded = !historyExpanded"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        width="12" height="12"
                                        viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        :class="['transition-transform', historyExpanded ? 'rotate-180' : '']"
                                    >
                                        <path d="m6 9 6 6 6-6"/>
                                    </svg>
                                    {{ historyExpanded ? 'Collapse' : `Show all (${attendanceHistory.length})` }}
                                </button>
                            </div>

                            <!-- Loading state -->
                            <div v-if="historyLoading" class="py-4 text-center text-xs text-muted-foreground">
                                Loading history…
                            </div>

                            <!-- Empty state -->
                            <div
                                v-else-if="attendanceHistory.length === 0"
                                class="py-4 text-center text-xs text-muted-foreground"
                            >
                                No attendance records found.
                            </div>

                            <!-- History list grouped by date -->
                            <div
                                v-else
                                :class="['overflow-y-auto rounded-lg border transition-all', historyExpanded ? 'max-h-72' : 'max-h-52']"
                            >
                                <template v-for="group in groupedAttendanceHistory" :key="group.date">
                                    <!-- Date header -->
                                    <div class="sticky top-0 z-10 flex items-center gap-2 bg-muted/80 backdrop-blur px-3 py-1.5 border-b">
                                        <span class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
                                            {{ group.label }}
                                        </span>
                                        <span class="ml-auto text-[10px] text-muted-foreground/60">
                                            {{ group.records.length }} record{{ group.records.length !== 1 ? 's' : '' }}
                                        </span>
                                    </div>
                                    <!-- Records for this date -->
                                    <div
                                        v-for="record in group.records"
                                        :key="record.id"
                                        class="flex items-center justify-between px-3 py-2 text-xs border-b last:border-b-0"
                                    >
                                        <span class="text-muted-foreground">
                                            {{ new Date(record.scanned_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                        </span>
                                        <span
                                            :class="[
                                                'rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                                record.status === 'Present'  ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' :
                                                record.status === 'Late'     ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400' :
                                                record.status === 'Time Out' ? 'bg-blue-500/15 text-blue-600 dark:text-blue-400' :
                                                record.status === 'Absent'   ? 'bg-red-500/15 text-red-600 dark:text-red-400' :
                                                                               'bg-muted text-muted-foreground'
                                            ]"
                                        >
                                            {{ record.status }}
                                        </span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <DialogFooter class="mt-2 flex flex-col gap-2 sm:flex-row sm:justify-between sm:items-center">
                        <div class="flex flex-wrap gap-2 w-full sm:w-auto">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="openEditFromInfo"
                            >
                                Edit student
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="openQrFromInfo"
                            >
                                View QR
                            </Button>
                                <Button
                                    v-if="infoStudent"
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="text-rose-600 border-rose-200 hover:bg-rose-50 hover:text-rose-700 hover:border-rose-300"
                                    @click="deleteStudent(infoStudent.id)"
                                >
                                    Delete
                                </Button>
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            class="w-full sm:w-auto"
                            @click="closeStudentInfoModal"
                        >
                            Close
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="editModalOpen">
                <DialogContent class="max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            Edit student
                        </DialogTitle>
                    </DialogHeader>

                    <form class="space-y-3" @submit.prevent="submitEditStudent">
                        <div class="space-y-1.5">
                            <label class="text-xs font-medium">
                                Full name
                            </label>
                            <Input
                                v-model="editName"
                                placeholder="e.g. Juan Dela Cruz"
                            />
                            <p
                                v-if="formErrors.name"
                                class="text-xs text-destructive"
                            >
                                {{ Array.isArray(formErrors.name) ? formErrors.name[0] : formErrors.name }}
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-medium">
                                Student number
                            </label>
                            <Input
                                v-model="editStudentNumber"
                                placeholder="e.g. 2026-0001"
                            />
                            <p
                                v-if="formErrors.student_number"
                                class="text-xs text-destructive"
                            >
                                {{ Array.isArray(formErrors.student_number) ? formErrors.student_number[0] : formErrors.student_number }}
                            </p>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="space-y-1.5">
                                <label class="text-xs font-medium">
                                    Section
                                </label>
                                <Input
                                    v-model="editSection"
                                    placeholder="e.g. BSIT-3A"
                                />
                                <p
                                    v-if="formErrors.section"
                                    class="text-xs text-destructive"
                                >
                                    {{ Array.isArray(formErrors.section) ? formErrors.section[0] : formErrors.section }}
                                </p>
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-medium">
                                    Email
                                </label>
                                <Input
                                    v-model="editEmail"
                                    type="email"
                                    placeholder="Optional"
                                />
                                <p
                                    v-if="formErrors.email"
                                    class="text-xs text-destructive"
                                >
                                    {{ Array.isArray(formErrors.email) ? formErrors.email[0] : formErrors.email }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-medium">
                                    Time slots
                                </label>
                                <Button
                                    type="button"
                                    size="icon-sm"
                                    variant="outline"
                                    @click="addEditScheduleSlot"
                                >
                                    +
                                </Button>
                            </div>
                            <p class="text-[11px] text-muted-foreground">
                                Update the student's schedule. A 15-minute grace
                                period is applied to each start time.
                            </p>

                            <div class="space-y-2">
                                <div
                                    v-for="(slot, index) in editSchedules"
                                    :key="index"
                                    class="flex items-center gap-2"
                                >
                                    <select
                                        v-model="slot.day"
                                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-xs shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        <option v-for="d in daysOfWeek" :key="d" :value="d">{{ d }}</option>
                                    </select>
                                    <Input
                                        v-model="slot.start"
                                        type="time"
                                        class="text-xs"
                                    />
                                    <span class="text-xs text-muted-foreground px-1">
                                        to
                                    </span>
                                    <Input
                                        v-model="slot.end"
                                        type="time"
                                        class="text-xs"
                                    />
                                    <Button
                                        v-if="editSchedules.length > 1"
                                        type="button"
                                        size="icon-sm"
                                        variant="ghost"
                                        @click="removeEditScheduleSlot(index)"
                                    >
                                        ×
                                    </Button>
                                </div>
                            </div>
                            <div
                                v-if="Object.keys(formErrors).some(k => k.startsWith('schedule'))"
                                class="mt-2 space-y-1 rounded-md bg-destructive/5 p-2"
                            >
                                <p v-for="(err, key) in formErrors" :key="key" v-show="key.startsWith('schedule')" class="text-[10px] text-destructive leading-tight">
                                    • {{ Array.isArray(err) ? err[0] : err }}
                                </p>
                            </div>
                        </div>

                        <DialogFooter class="mt-4 flex justify-end gap-2">
                            <DialogClose as-child>
                                <Button type="button" variant="outline">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button type="submit" :disabled="submitting">
                                {{ submitting ? 'Saving…' : 'Save changes' }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="qrModalOpen">
                <DialogContent class="max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            Student QR code
                        </DialogTitle>
                    </DialogHeader>

                    <div v-if="selectedStudent" class="space-y-4">
                        <div class="space-y-1">
                            <p class="text-sm font-semibold">
                                {{ selectedStudent.name }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ selectedStudent.student_number }}
                                ·
                                {{ selectedStudent.section || 'No section' }}
                            </p>
                        </div>

                        <div
                            class="flex items-center justify-center rounded-lg border bg-white p-4"
                        >
                            <canvas
                                id="qr-canvas"
                                ref="qrCanvas"
                                class="h-48 w-48"
                            ></canvas>
                        </div>

                        <p class="text-xs text-muted-foreground">
                            This QR encodes a secure token for this student. You
                            can print or share it, and regenerate it anytime to
                            invalidate older copies.
                        </p>

                        <div class="flex justify-between gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="regenerateQr"
                            >
                                Regenerate
                            </Button>
                            <div class="flex gap-2">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    @click="downloadQr"
                                >
                                    Download
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    @click="closeQrModal"
                                >
                                    Close
                                </Button>
                            </div>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="scanModalOpen">
                <DialogContent class="max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            Scan student QR code
                        </DialogTitle>
                    </DialogHeader>

                    <div class="space-y-3">
                        <div
                            class="relative overflow-hidden rounded-lg border bg-black/80"
                        >
                            <video
                                ref="videoRef"
                                class="h-64 w-full object-cover"
                                playsinline
                                muted
                            ></video>

                            <!-- Scanner Overlay -->
                            <div 
                                v-if="scanning"
                                class="absolute inset-0 pointer-events-none border-2 border-emerald-500/30 transition-all duration-300 z-10"
                                :class="{
                                    'border-emerald-500 scale-[1.02] bg-emerald-500/10': scanFeedback === 'success',
                                    'border-rose-500 scale-[1.02] bg-rose-500/10': scanFeedback === 'error',
                                }"
                            >
                                <!-- Scanning Line Animation -->
                                <div 
                                    v-if="!scanFeedback"
                                    class="absolute left-0 right-0 h-[2px] bg-gradient-to-r from-transparent via-emerald-500 to-transparent shadow-[0_0_15px_rgba(16,185,129,0.8)] animate-scan-line"
                                ></div>

                                <div class="absolute inset-0 flex items-center justify-center">
                                    <CheckCircle2 v-if="scanFeedback === 'success'" class="h-12 w-12 text-emerald-500 animate-in zoom-in duration-300" />
                                    <AlertCircle v-if="scanFeedback === 'error'" class="h-12 w-12 text-rose-500 animate-in zoom-in duration-300" />
                                </div>
                            </div>
                        </div>

                        <p class="text-xs text-muted-foreground">
                            Point the camera at a student QR code. Attendance
                            will be recorded automatically when the code is
                            detected.
                        </p>

                        <p
                            v-if="scanError && !scanResultModalOpen"
                            class="text-xs font-medium text-destructive"
                        >
                            {{ scanError }}
                        </p>

                        <DialogFooter class="mt-2 flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="closeScanModal"
                            >
                                Close Scanner
                            </Button>
                        </DialogFooter>
                    </div>
                </DialogContent>
            </Dialog>

            <!-- Scan Result Modal -->
            <Dialog v-model:open="scanResultModalOpen" @update:open="(val) => !val && closeScanResultModal()">
                <DialogContent class="max-w-xs sm:max-w-sm overflow-hidden p-0 rounded-2xl border-0 shadow-2xl">
                    <div 
                        class="p-6 text-center space-y-4"
                        :class="scanError ? 'bg-rose-50/50 dark:bg-rose-950/20' : 'bg-emerald-50/50 dark:bg-emerald-950/20'"
                    >
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full"
                             :class="scanError ? 'bg-rose-100 dark:bg-rose-900/50 text-rose-600' : 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600'"
                        >
                            <CheckCircle2 v-if="!scanError" class="h-8 w-8" />
                            <AlertCircle v-else class="h-8 w-8" />
                        </div>
                        
                        <div class="space-y-1">
                            <h3 class="text-lg font-bold">
                                {{ scanError ? 'Scan Failed' : 'Attendance Recorded' }}
                            </h3>
                            <p v-if="!scanError && lastScanResult" class="text-sm font-medium">
                                {{ lastScanResult.student.name }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ scanError || (lastScanResult ? `Status: ${lastScanResult.status}` : '') }}
                            </p>
                            <p v-if="!scanError && lastScanResult" class="text-[10px] text-muted-foreground/60">
                                {{ formatDateTime(lastScanResult.scanned_at) }}
                            </p>
                        </div>

                        <Button 
                            class="w-full rounded-xl py-6 text-base font-semibold transition-all hover:scale-[1.02] active:scale-[0.98]"
                            :variant="scanError ? 'destructive' : 'default'"
                            @click="closeScanResultModal"
                        >
                            Close
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>

            <!-- Generic Confirmation Modal -->
            <Dialog v-model:open="confirmModalOpen">
                <DialogContent class="max-w-sm">
                    <DialogHeader>
                        <DialogTitle>{{ confirmTitle }}</DialogTitle>
                    </DialogHeader>
                    <div class="py-2">
                        <p class="text-sm text-muted-foreground">
                            {{ confirmDescription }}
                        </p>
                    </div>
                    <DialogFooter class="flex gap-2">
                        <Button variant="outline" @click="confirmModalOpen = false">
                            Cancel
                        </Button>
                        <Button 
                            :variant="confirmIsDestructive ? 'destructive' : 'default'"
                            @click="handleConfirm"
                        >
                            Confirm
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>

        <!-- Floating Scan Widget -->
        <div class="fixed bottom-6 right-6 z-50">
            <Button
                size="lg"
                class="group h-14 rounded-full shadow-[0_8px_30px_rgb(0,0,0,0.12)] hover:shadow-[0_8px_30px_rgb(0,0,0,0.2)] hover:-translate-y-1 transition-all duration-300 flex items-center gap-2 pr-6 pl-5 dark:shadow-[0_8px_30px_rgb(255,255,255,0.1)] dark:hover:shadow-[0_8px_30px_rgb(255,255,255,0.15)] bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white border-0"
                @click="openScanModal"
            >
                <div class="relative flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="transition-transform duration-300 group-hover:scale-110"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><rect x="7" y="7" width="10" height="10" rx="1"/></svg>
                </div>
                <span class="hidden sm:inline font-semibold">Scan</span>
                <span class="sm:hidden font-semibold">Scan</span>
            </Button>
        </div>
    </AppLayout>
</template>

<style scoped>
@keyframes scan {
    0% { top: 0; opacity: 0; }
    10% { opacity: 1; }
    90% { opacity: 1; }
    100% { top: 100%; opacity: 0; }
}

.animate-scan-line {
    animation: scan 2s linear infinite;
}

.status-pulse {
    transition: all 0.3s ease;
}

.glass-card {
    background: rgba(var(--background), 0.6);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(var(--foreground), 0.1);
}
</style>
