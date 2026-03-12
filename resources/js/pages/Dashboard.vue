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
    schedule?: { start: string; end: string }[];
    latest_attendance?: {
        id: number;
        status: string;
        scanned_at: string;
    } | null;
};

type PageProps = {
    students: Student[];
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
const selectedStudent = ref<Student | null>(null);
const qrModalOpen = ref(false);

const studentInfoModalOpen = ref(false);
const infoStudent = ref<Student | null>(null);
const attendanceHistory = ref<AttendanceRecord[]>([]);
const historyExpanded = ref(false);
const historyLoading = ref(false);

const name = ref('');
const studentNumber = ref('');
const email = ref('');
const section = ref('');
const schedules = ref<{ start: string; end: string }[]>([
    { start: '', end: '' },
]);

const editName = ref('');
const editStudentNumber = ref('');
const editEmail = ref('');
const editSection = ref('');
const editSchedules = ref<{ start: string; end: string }[]>([
    { start: '', end: '' },
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
let scanInterval: number | null = null;
let mediaStream: MediaStream | null = null;

function resetForm() {
    name.value = '';
    studentNumber.value = '';
    email.value = '';
    section.value = '';
    schedules.value = [{ start: '', end: '' }];
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
    schedules.value.push({ start: '', end: '' });
}

function removeScheduleSlot(index: number) {
    if (schedules.value.length === 1) return;
    schedules.value.splice(index, 1);
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
            ? student.schedule.map((s) => ({ start: s.start, end: s.end }))
            : [{ start: '', end: '' }];
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
    editSchedules.value.push({ start: '', end: '' });
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
            scanError.value = null;
        } catch (error) {
            scanError.value =
                error instanceof Error
                    ? error.message
                    : 'Failed to record attendance.';
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
            x: -20,
            rotationY: 15,
            z: -20,
            duration: 0.6,
            stagger: 0.05,
            delay: 0.4,
            ease: 'power2.out',
        });
    }

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
                class="grid auto-rows-min gap-4 grid-cols-2 md:grid-cols-3"
            >
                <div
                    data-card
                    class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-gradient-to-br from-primary/10 via-background to-background p-4 shadow-sm dark:border-sidebar-border"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p
                                class="text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                            >
                                Total students
                            </p>
                            <p class="mt-2 text-3xl font-semibold">
                                {{ students.length }}
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    data-card
                    class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-gradient-to-br from-emerald-500/10 via-background to-background p-4 shadow-sm dark:border-sidebar-border"
                >
                    <div class="flex h-full flex-col justify-between gap-3">
                        <div>
                            <p
                                class="text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                            >
                                Actions
                            </p>
                            <p class="mt-2 text-sm text-muted-foreground">
                                Quickly manage students.
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button size="sm" @click="openCreateModal">
                                Add student
                            </Button>
                        </div>
                    </div>
                </div>

                <div
                    data-card
                    class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-gradient-to-br from-indigo-500/10 via-background to-background p-4 shadow-sm dark:border-sidebar-border col-span-2 md:col-span-1"
                >
                    <div class="flex h-full flex-col justify-center gap-2">
                        <p
                            class="text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                        >
                            Hint
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Each student gets a unique QR code based on a
                            secure token. You can regenerate it anytime.
                        </p>
                    </div>
                </div>
            </div>

            <div
                ref="tableRef"
                class="relative flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 bg-background/60 shadow-sm backdrop-blur md:min-h-min dark:border-sidebar-border"
            >
                <div class="flex items-center justify-between border-b p-4">
                    <div>
                        <h2 class="text-base font-semibold">
                            Students
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Click a student row to view and download their QR
                            code.
                        </p>
                    </div>
                    <Button size="sm" @click="openCreateModal">
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
                                <th class="px-4 py-2 text-xs font-medium">
                                    Status
                                </th>
                                <th class="px-4 py-2 text-xs font-medium">
                                    Section
                                </th>
                                <th class="px-4 py-2 text-xs font-medium">
                                    Email
                                </th>
                                <!-- <th class="px-4 py-2 text-right text-xs font-medium">
                                    QR
                                </th> -->
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-if="students.length === 0"
                                class="border-b last:border-b-0"
                            >
                                <td
                                    colspan="6"
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
                                v-for="student in students"
                                :key="student.id"
                                class="border-b transition-colors hover:bg-muted/40 last:border-b-0 cursor-pointer"
                                @click="openStudentInfoModal(student)"
                            >
                                <td class="px-4 py-2 text-sm font-medium">
                                    <span class="flex items-center gap-1.5">
                                        {{ student.name }}
                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground opacity-60"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-xs text-muted-foreground">
                                    {{ student.student_number }}
                                </td>
                                <td class="px-4 py-2" @click.stop>
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <span
                                                v-if="!student.latest_attendance"
                                                class="text-xs font-medium text-muted-foreground"
                                            >
                                                Absent
                                            </span>
                                            <select
                                                v-else
                                                class="h-8 rounded-md border bg-background px-2 text-xs text-foreground"
                                                :value="student.latest_attendance.status"
                                                @change="
                                                    updateLatestStatus(
                                                        student,
                                                        ($event.target as HTMLSelectElement)
                                                            .value,
                                                    )
                                                "
                                            >
                                                <option value="Present">
                                                    Present
                                                </option>
                                                <option value="Late">Late</option>
                                                <option value="Time Out">
                                                    Time Out
                                                </option>
                                                <option value="Absent">
                                                    Absent
                                                </option>
                                            </select>
                                        </div>
                                        <span
                                            v-if="student.latest_attendance"
                                            class="text-[11px] text-muted-foreground"
                                        >
                                            {{
                                                formatDateTime(
                                                    student.latest_attendance.scanned_at,
                                                )
                                            }}
                                        </span>
                                        <span
                                            v-else
                                            class="text-[11px] text-muted-foreground"
                                        >
                                            {{ new Date().toLocaleDateString() }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-xs text-muted-foreground">
                                    {{ student.section || '—' }}
                                </td>
                                <td class="px-4 py-2 text-xs text-muted-foreground">
                                    {{ student.email || '—' }}
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
                                {{ formErrors.name[0] }}
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
                                {{ formErrors.student_number[0] }}
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
                                    {{ formErrors.section[0] }}
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
                                    {{ formErrors.email[0] }}
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
                                    <Input
                                        v-model="slot.start"
                                        type="time"
                                        class="text-xs"
                                    />
                                    <span class="text-xs text-muted-foreground">
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
                            <p
                                v-if="formErrors.schedule"
                                class="text-xs text-destructive"
                            >
                                {{ formErrors.schedule[0] }}
                            </p>
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
                                        {{ slot.start }} – {{ slot.end }}
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

                            <!-- History list -->
                            <div
                                v-else
                                :class="['overflow-y-auto rounded-lg border divide-y transition-all', historyExpanded ? 'max-h-64' : '']" 
                            >
                                <div
                                    v-for="record in historyExpanded ? attendanceHistory : attendanceHistory.slice(0, 5)"
                                    :key="record.id"
                                    class="flex items-center justify-between px-3 py-2 text-xs"
                                >
                                    <span class="text-muted-foreground">
                                        {{ formatDateTime(record.scanned_at) }}
                                    </span>
                                    <span
                                        :class="[
                                            'rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                            record.status === 'Present' ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' :
                                            record.status === 'Late'    ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400' :
                                            record.status === 'Absent'  ? 'bg-red-500/15 text-red-600 dark:text-red-400' :
                                                                          'bg-muted text-muted-foreground'
                                        ]"
                                    >
                                        {{ record.status }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <DialogFooter class="mt-2 flex flex-col gap-2 sm:flex-row sm:justify-between sm:items-center">
                        <div class="flex gap-2 w-full sm:w-auto">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="flex-1 sm:flex-none"
                                @click="openEditFromInfo"
                            >
                                Edit student
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="flex-1 sm:flex-none"
                                @click="openQrFromInfo"
                            >
                                View QR
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
                                {{ formErrors.name[0] }}
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
                                {{ formErrors.student_number[0] }}
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
                                    {{ formErrors.section[0] }}
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
                                    {{ formErrors.email[0] }}
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
                                    <Input
                                        v-model="slot.start"
                                        type="time"
                                        class="text-xs"
                                    />
                                    <span class="text-xs text-muted-foreground">
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
                            <p
                                v-if="formErrors.schedule"
                                class="text-xs text-destructive"
                            >
                                {{ formErrors.schedule[0] }}
                            </p>
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
                            class="overflow-hidden rounded-lg border bg-black/80"
                        >
                            <video
                                ref="videoRef"
                                class="h-64 w-full object-cover"
                                playsinline
                                muted
                            ></video>
                        </div>

                        <p class="text-xs text-muted-foreground">
                            Point the camera at a student QR code. Attendance
                            will be recorded automatically when the code is
                            detected.
                        </p>

                        <p
                            v-if="scanError"
                            class="text-xs font-medium text-destructive"
                        >
                            {{ scanError }}
                        </p>

                        <div
                            v-if="lastScanResult"
                            class="rounded-lg border bg-muted/60 p-3 text-xs"
                        >
                            <p class="text-[11px] font-semibold uppercase">
                                Last scan
                            </p>
                            <p class="mt-1 text-sm font-medium">
                                {{ lastScanResult.student.name }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ lastScanResult.student.student_number }}
                                ·
                                {{ lastScanResult.student.section || 'No section' }}
                            </p>
                            <p class="mt-1 text-[11px] text-muted-foreground">
                                Status:
                                {{ lastScanResult.status }}
                            </p>
                            <p class="text-[11px] text-muted-foreground">
                                Scanned at:
                                {{ lastScanResult.scanned_at }}
                            </p>
                        </div>

                        <DialogFooter class="mt-2 flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="closeScanModal"
                            >
                                Close
                            </Button>
                        </DialogFooter>
                    </div>
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