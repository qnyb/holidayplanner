<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import 'leaflet/dist/leaflet.css';

interface Spot {
    id: number;
    title: string;
    address: string | null;
    category: string;
    category_label: string;
    category_color: string;
    category_icon: string;
    maps_url: string | null;
    preview_image: string | null;
    lat: string | null;
    lng: string | null;
    visit_time: string | null;
    is_visited: boolean;
}

const props = defineProps<{ spots: Spot[] }>();

const spots = ref<Spot[]>(props.spots.map((s) => ({ ...s })));
const selectedSpot = ref<Spot | null>(null);
const mapContainer = ref<HTMLElement | null>(null);
let leafletMap: ReturnType<typeof import('leaflet')['map']> | null = null;
const markers = new Map<number, ReturnType<typeof import('leaflet')['circleMarker']>>();

const visitedCount = computed(() => spots.value.filter((s) => s.is_visited).length);
const progressPercent = computed(() => Math.round((visitedCount.value / spots.value.length) * 100));

const categoryColors: Record<string, string> = {
    food: '#f97316',
    museum: '#3b82f6',
    landmark: '#22c55e',
    nature: '#10b981',
    shopping: '#ec4899',
    entertainment: '#a855f7',
    accommodation: '#64748b',
    other: '#6b7280',
};

const undatedSpots = computed(() => spots.value.filter((s) => !s.visit_time));

const groupedByDate = computed(() => {
    const groups: Record<string, Spot[]> = {};
    for (const spot of spots.value) {
        if (!spot.visit_time) {
            continue;
        }
        const date = new Date(spot.visit_time).toLocaleDateString('tr-TR', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
        });
        if (!groups[date]) {
            groups[date] = [];
        }
        groups[date].push(spot);
    }
    return groups;
});

function formatTime(iso: string): string {
    return new Date(iso).toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
}

function openModal(spot: Spot) {
    selectedSpot.value = spot;
}

function closeModal() {
    selectedSpot.value = null;
}

async function toggleVisited(spot: Spot) {
    const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
    const res = await fetch(`/spots/${spot.id}/toggle`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' },
    });
    const data = await res.json();
    const local = spots.value.find((s) => s.id === spot.id);
    if (local) {
        local.is_visited = data.is_visited;
    }
    if (selectedSpot.value?.id === spot.id) {
        selectedSpot.value = { ...selectedSpot.value, is_visited: data.is_visited };
    }
    updateMarker(spot.id, data.is_visited);
}

function updateMarker(id: number, isVisited: boolean) {
    const marker = markers.get(id);
    if (!marker) {
        return;
    }
    (marker as ReturnType<typeof import('leaflet')['circleMarker']>).setStyle({
        fillColor: isVisited ? '#22c55e' : '#3b82f6',
        color: isVisited ? '#15803d' : '#1d4ed8',
    });
}

onMounted(async () => {
    const L = (await import('leaflet')).default;

    const spotsWithCoords = spots.value.filter((s) => s.lat && s.lng);
    if (!mapContainer.value || spotsWithCoords.length === 0) {
        return;
    }

    const center = spotsWithCoords.reduce(
        (acc, s) => [acc[0] + parseFloat(s.lat!), acc[1] + parseFloat(s.lng!)],
        [0, 0],
    );
    const avgLat = center[0] / spotsWithCoords.length;
    const avgLng = center[1] / spotsWithCoords.length;

    leafletMap = L.map(mapContainer.value).setView([avgLat, avgLng], 11);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap',
    }).addTo(leafletMap);

    for (const spot of spotsWithCoords) {
        const color = spot.is_visited ? '#22c55e' : '#3b82f6';
        const border = spot.is_visited ? '#15803d' : '#1d4ed8';

        const marker = L.circleMarker([parseFloat(spot.lat!), parseFloat(spot.lng!)], {
            radius: 9,
            fillColor: color,
            color: border,
            weight: 2,
            opacity: 1,
            fillOpacity: 0.85,
        })
            .addTo(leafletMap!)
            .bindTooltip(spot.title, { permanent: false, direction: 'top' })
            .on('click', () => openModal(spot));

        markers.set(spot.id, marker as ReturnType<typeof import('leaflet')['circleMarker']>);
    }
});

onUnmounted(() => {
    leafletMap?.remove();
});

const handleKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Escape') {
        closeModal();
    }
};

onMounted(() => window.addEventListener('keydown', handleKeydown));
onUnmounted(() => window.removeEventListener('keydown', handleKeydown));
</script>

<template>
    <Head title="Azerbaycan Gezisi 🇦🇿" />

    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <header class="bg-white border-b border-gray-200 px-4 py-4">
            <div class="max-w-4xl mx-auto flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-gray-900">🇦🇿 Azerbaycan Gezisi</h1>
                    <p class="text-sm text-gray-500">23 Mayıs – 1 Haziran 2026</p>
                </div>
                <div class="text-right">
                    <p class="text-2xl font-bold text-blue-600">{{ visitedCount }}/{{ spots.length }}</p>
                    <p class="text-xs text-gray-500">ziyaret edildi</p>
                </div>
            </div>
            <!-- Progress bar -->
            <div class="max-w-4xl mx-auto mt-3">
                <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                    <div
                        class="h-full bg-green-500 rounded-full transition-all duration-500"
                        :style="{ width: progressPercent + '%' }"
                    />
                </div>
                <p class="text-xs text-gray-400 mt-1 text-right">{{ progressPercent }}% tamamlandı</p>
            </div>
        </header>

        <!-- Map -->
        <div class="max-w-4xl mx-auto px-4 mt-4">
            <div class="rounded-xl overflow-hidden shadow border border-gray-200">
                <div ref="mapContainer" style="height: 420px; width: 100%;" />
            </div>
            <div class="flex gap-4 mt-2 text-xs text-gray-500">
                <span class="flex items-center gap-1">
                    <span class="w-3 h-3 rounded-full bg-blue-500 inline-block" /> Planlandı
                </span>
                <span class="flex items-center gap-1">
                    <span class="w-3 h-3 rounded-full bg-green-500 inline-block" /> Ziyaret edildi
                </span>
            </div>
        </div>

        <!-- Timeline -->
        <main class="max-w-4xl mx-auto px-4 py-6">
            <!-- Undated spots -->
            <div v-if="undatedSpots.length > 0" class="mb-8">
                <div class="flex items-center gap-3 mb-4">
                    <div class="h-px flex-1 bg-gray-200" />
                    <span class="text-sm font-semibold text-gray-400 whitespace-nowrap px-2 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        Tarihi belirlenmemiş
                    </span>
                    <div class="h-px flex-1 bg-gray-200" />
                </div>
                <div class="relative ml-3">
                    <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-200" />
                    <div v-for="spot in undatedSpots" :key="spot.id" class="relative flex gap-4 mb-4">
                        <div class="relative z-10 flex-shrink-0 flex items-start pt-1">
                            <button
                                @click="toggleVisited(spot)"
                                :class="[
                                    'w-8 h-8 rounded-full border-2 flex items-center justify-center transition-all duration-200',
                                    spot.is_visited
                                        ? 'bg-green-500 border-green-600 text-white'
                                        : 'bg-white border-gray-300 text-gray-400 hover:border-blue-400',
                                ]"
                                :title="spot.is_visited ? 'Ziyaret edildi - kaldırmak için tıkla' : 'Ziyaret edildi olarak işaretle'"
                            >
                                <svg v-if="spot.is_visited" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <svg v-else class="w-4 h-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </button>
                        </div>
                        <div
                            @click="openModal(spot)"
                            :class="[
                                'flex-1 rounded-xl border cursor-pointer transition-all duration-200 hover:shadow-md overflow-hidden',
                                spot.is_visited ? 'bg-green-50 border-green-200 opacity-70' : 'bg-white border-gray-200 hover:border-blue-300',
                            ]"
                        >
                            <div v-if="spot.preview_image" class="relative h-36 overflow-hidden">
                                <img :src="spot.preview_image" :alt="spot.title" class="w-full h-full object-cover" />
                                <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent" />
                                <span class="absolute bottom-2 left-2 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium text-white"
                                    :style="{ backgroundColor: categoryColors[spot.category] + 'cc' }">
                                    {{ spot.category_label }}
                                </span>
                            </div>
                            <div v-else class="h-20 flex items-center justify-center text-4xl"
                                :style="{ backgroundColor: categoryColors[spot.category] + '15' }">
                                {{ spot.category === 'food' ? '🍽️' : spot.category === 'museum' ? '🏛️' : spot.category === 'nature' ? '🌿' : spot.category === 'shopping' ? '🛍️' : '📍' }}
                            </div>
                            <div class="p-3">
                                <h3 :class="['font-semibold text-sm leading-tight', spot.is_visited ? 'line-through text-gray-400' : 'text-gray-900']">
                                    {{ spot.title }}
                                </h3>
                                <p v-if="spot.address" class="text-xs text-gray-500 mt-1 truncate">{{ spot.address }}</p>
                                <span v-if="spot.is_visited" class="inline-flex items-center gap-1 text-xs text-green-600 font-medium mt-1">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                    Ziyaret edildi
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-for="(daySpots, dateLabel) in groupedByDate" :key="dateLabel" class="mb-8">
                <!-- Day header -->
                <div class="flex items-center gap-3 mb-4">
                    <div class="h-px flex-1 bg-gray-200" />
                    <span class="text-sm font-semibold text-gray-600 whitespace-nowrap px-2">{{ dateLabel }}</span>
                    <div class="h-px flex-1 bg-gray-200" />
                </div>

                <!-- Spots for this day -->
                <div class="relative ml-3">
                    <!-- Vertical line -->
                    <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-200" />

                    <div v-for="(spot, idx) in daySpots" :key="spot.id" class="relative flex gap-4 mb-4">
                        <!-- Timeline dot -->
                        <div class="relative z-10 flex-shrink-0 flex items-start pt-1">
                            <button
                                @click="toggleVisited(spot)"
                                :class="[
                                    'w-8 h-8 rounded-full border-2 flex items-center justify-center transition-all duration-200',
                                    spot.is_visited
                                        ? 'bg-green-500 border-green-600 text-white'
                                        : 'bg-white border-gray-300 text-gray-400 hover:border-blue-400',
                                ]"
                                :title="spot.is_visited ? 'Ziyaret edildi - kaldırmak için tıkla' : 'Ziyaret edildi olarak işaretle'"
                            >
                                <svg v-if="spot.is_visited" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span v-else class="text-xs font-bold">{{ idx + 1 }}</span>
                            </button>
                        </div>

                        <!-- Card -->
                        <div
                            @click="openModal(spot)"
                            :class="[
                                'flex-1 rounded-xl border cursor-pointer transition-all duration-200 hover:shadow-md overflow-hidden',
                                spot.is_visited
                                    ? 'bg-green-50 border-green-200 opacity-70'
                                    : 'bg-white border-gray-200 hover:border-blue-300',
                            ]"
                        >
                            <!-- Image banner -->
                            <div v-if="spot.preview_image" class="relative h-36 overflow-hidden">
                                <img
                                    :src="spot.preview_image"
                                    :alt="spot.title"
                                    class="w-full h-full object-cover"
                                />
                                <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent" />
                                <span
                                    class="absolute bottom-2 left-2 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium text-white"
                                    :style="{ backgroundColor: categoryColors[spot.category] + 'cc' }"
                                >
                                    {{ spot.category_label }}
                                </span>
                                <span v-if="spot.visit_time" class="absolute bottom-2 right-2 text-xs text-white font-medium bg-black/50 backdrop-blur-sm px-2 py-0.5 rounded-full">
                                    {{ formatTime(spot.visit_time) }}
                                </span>
                            </div>
                            <div v-else class="h-20 flex items-center justify-center text-4xl"
                                :style="{ backgroundColor: categoryColors[spot.category] + '15' }">
                                {{ spot.category === 'food' ? '🍽️' : spot.category === 'museum' ? '🏛️' : spot.category === 'nature' ? '🌿' : spot.category === 'shopping' ? '🛍️' : '📍' }}
                            </div>

                            <!-- Text content -->
                            <div class="p-3">
                                <div class="flex items-start justify-between gap-2">
                                    <h3 :class="['font-semibold text-sm leading-tight', spot.is_visited ? 'line-through text-gray-400' : 'text-gray-900']">
                                        {{ spot.title }}
                                    </h3>
                                    <span v-if="!spot.preview_image && spot.visit_time" class="text-xs text-gray-400 flex-shrink-0 mt-0.5">
                                        {{ formatTime(spot.visit_time) }}
                                    </span>
                                </div>
                                <p v-if="spot.address" class="text-xs text-gray-500 mt-1 truncate">{{ spot.address }}</p>
                                <div v-if="!spot.preview_image" class="flex items-center gap-2 mt-1.5">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                        :style="{
                                            backgroundColor: categoryColors[spot.category] + '20',
                                            color: categoryColors[spot.category],
                                        }"
                                    >
                                        {{ spot.category_label }}
                                    </span>
                                </div>
                                <span v-if="spot.is_visited" class="inline-flex items-center gap-1 text-xs text-green-600 font-medium mt-1">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                    Ziyaret edildi
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Detail Modal -->
    <Teleport to="body">
        <Transition name="modal">
            <div
                v-if="selectedSpot"
                class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
                @click.self="closeModal"
            >
                <div class="absolute inset-0 bg-black/50" @click="closeModal" />
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
                    <!-- Image -->
                    <div v-if="selectedSpot.preview_image" class="h-72 overflow-hidden">
                        <img :src="selectedSpot.preview_image" :alt="selectedSpot.title" class="w-full h-full object-cover" />
                        <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent" style="height: 18rem;" />
                    </div>
                    <div v-else class="h-32 flex items-center justify-center text-6xl"
                        :style="{ backgroundColor: categoryColors[selectedSpot.category] + '15' }">
                        {{ selectedSpot.category === 'food' ? '🍽️' : selectedSpot.category === 'museum' ? '🏛️' : selectedSpot.category === 'nature' ? '🌿' : selectedSpot.category === 'shopping' ? '🛍️' : '📍' }}
                    </div>

                    <!-- Close button -->
                    <button
                        @click="closeModal"
                        class="absolute top-3 right-3 bg-white/90 rounded-full w-8 h-8 flex items-center justify-center shadow hover:bg-white transition-colors"
                    >
                        <svg class="w-4 h-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                    <!-- Content -->
                    <div class="p-5">
                        <div class="flex items-start justify-between gap-2 mb-1">
                            <h2 class="text-lg font-bold text-gray-900 leading-tight">{{ selectedSpot.title }}</h2>
                            <span
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium flex-shrink-0 mt-0.5"
                                :style="{
                                    backgroundColor: categoryColors[selectedSpot.category] + '20',
                                    color: categoryColors[selectedSpot.category],
                                }"
                            >
                                {{ selectedSpot.category_label }}
                            </span>
                        </div>
                        <p v-if="selectedSpot.address" class="text-sm text-gray-500 mb-3">{{ selectedSpot.address }}</p>
                        <p v-if="selectedSpot.visit_time" class="text-sm text-gray-500 mb-4">
                            📅 {{ new Date(selectedSpot.visit_time).toLocaleDateString('tr-TR', { weekday: 'long', day: 'numeric', month: 'long' }) }}
                            · {{ formatTime(selectedSpot.visit_time) }}
                        </p>

                        <div class="flex gap-2">
                            <button
                                @click="toggleVisited(selectedSpot!)"
                                :class="[
                                    'flex-1 py-2.5 px-4 rounded-xl text-sm font-semibold transition-all',
                                    selectedSpot.is_visited
                                        ? 'bg-green-100 text-green-700 hover:bg-green-200'
                                        : 'bg-blue-600 text-white hover:bg-blue-700',
                                ]"
                            >
                                {{ selectedSpot.is_visited ? '✓ Ziyaret edildi (geri al)' : 'Ziyaret edildi olarak işaretle' }}
                            </button>
                            <a
                                v-if="selectedSpot.maps_url"
                                :href="selectedSpot.maps_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="py-2.5 px-4 rounded-xl text-sm font-semibold bg-gray-100 text-gray-700 hover:bg-gray-200 transition-colors"
                            >
                                🗺️ Harita
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.2s ease;
}
.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
.modal-enter-active .relative,
.modal-leave-active .relative {
    transition: transform 0.2s ease;
}
.modal-enter-from .relative,
.modal-leave-to .relative {
    transform: scale(0.95);
}
</style>
