<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import gsap from 'gsap';
import jsQR from 'jsqr';
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

type Student = {
    id: number;
    name: string;
    student_number: string;
    email?: string | null;
    section?: string | null;
    qr_token: string;
    schedule?: { start: string; end: string }[];
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
const scanModalOpen = ref(false);
const selectedStudent = ref<Student | null>(null);
const qrModalOpen = ref(false);

const name = ref('');
const studentNumber = ref('');
const email = ref('');
const section = ref('');
const schedules = ref<{ start: string; end: string }[]>([
    { start: '', end: '' },
]);

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
        route('students.store'),
        {
            name: name.value,
            student_number: studentNumber.value,
            email: email.value || null,
            section: section.value || null,
            schedule: schedules.value,
        },
        {
            onError: (errors) => {
                formErrors.value = errors;
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
        route('students.qr.regenerate', {
            student: selectedStudent.value.id,
        }),
        {},
        {
            onSuccess: () => {
                router.visit(route('dashboard'), {
                    only: ['students'],
                    preserveScroll: true,
                    onSuccess: (page) => {
                        const updated = (page.props as unknown as PageProps)
                            .students.find(
                                (s) => s.id === selectedStudent.value?.id,
                            );
                        if (updated) {
                            selectedStudent.value = updated;
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
            const response = await window.fetch(route('attendance.scan'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document
                        .querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)
                        ?.content ?? '',
                },
                body: JSON.stringify({ token }),
            });

            if (!response.ok) {
                throw new Error('Invalid or expired QR code.');
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
    if (cardsRef.value) {
        const cards = cardsRef.value.querySelectorAll<HTMLElement>('[data-card]');
        gsap.from(cards, {
            opacity: 0,
            y: 40,
            duration: 0.8,
            stagger: 0.1,
            ease: 'power3.out',
        });
    }

    if (tableRef.value) {
        gsap.from(tableRef.value, {
            opacity: 0,
            y: 30,
            duration: 0.9,
            delay: 0.3,
            ease: 'power3.out',
        });
    }
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
            <div
                ref="cardsRef"
                class="grid auto-rows-min gap-4 md:grid-cols-3"
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
                                Quickly manage students and scan attendance
                                codes.
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button size="sm" @click="openCreateModal">
                                Add student
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                @click="openScanModal"
                            >
                                Scan attendance
                            </Button>
                        </div>
                    </div>
                </div>

                <div
                    data-card
                    class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-gradient-to-br from-indigo-500/10 via-background to-background p-4 shadow-sm dark:border-sidebar-border"
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

                <div class="max-h-[520px] overflow-y-auto">
                    <table class="min-w-full text-left text-sm">
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
                                    Section
                                </th>
                                <th class="px-4 py-2 text-xs font-medium">
                                    Email
                                </th>
                                <th class="px-4 py-2 text-right text-xs font-medium">
                                    QR
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-if="students.length === 0"
                                class="border-b last:border-b-0"
                            >
                                <td
                                    colspan="5"
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
                                class="cursor-pointer border-b transition-colors hover:bg-muted/40 last:border-b-0"
                                @click="openQrModal(student)"
                            >
                                <td class="px-4 py-2 text-sm font-medium">
                                    {{ student.name }}
                                </td>
                                <td class="px-4 py-2 text-xs text-muted-foreground">
                                    {{ student.student_number }}
                                </td>
                                <td class="px-4 py-2 text-xs text-muted-foreground">
                                    {{ student.section || '—' }}
                                </td>
                                <td class="px-4 py-2 text-xs text-muted-foreground">
                                    {{ student.email || '—' }}
                                </td>
                                <td class="px-4 py-2 text-right text-xs text-muted-foreground">
                                    View QR
                                </td>
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
    </AppLayout>
</template>
