<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    BedDouble,
    Download,
    Edit2,
    ExternalLink,
    Landmark,
    MapPin,
    MoreHorizontal,
    Music,
    Plus,
    ShoppingBag,
    Trash2,
    TreePine,
    Upload,
    UtensilsCrossed,
} from 'lucide-vue-next';
import { computed, markRaw, ref } from 'vue';
import TravelSpotController from '@/actions/App/Http/Controllers/TravelSpotController';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import InputError from '@/components/InputError.vue';
import { index } from '@/routes/travel-spots';

type Category = {
    value: string;
    label: string;
    color: string;
    icon: string;
};

type TravelSpot = {
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
};

const props = defineProps<{
    spots: TravelSpot[];
    categories: Category[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Seyahat Planım', href: index() }],
    },
});

const iconMap: Record<string, ReturnType<typeof markRaw>> = {
    UtensilsCrossed: markRaw(UtensilsCrossed),
    Landmark: markRaw(Landmark),
    MapPin: markRaw(MapPin),
    TreePine: markRaw(TreePine),
    ShoppingBag: markRaw(ShoppingBag),
    Music: markRaw(Music),
    BedDouble: markRaw(BedDouble),
    MoreHorizontal: markRaw(MoreHorizontal),
};

const colorClassMap: Record<string, string> = {
    orange: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
    blue: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    green: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    emerald: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    pink: 'bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-400',
    purple: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
    slate: 'bg-slate-100 text-slate-700 dark:bg-slate-700/30 dark:text-slate-400',
    gray: 'bg-gray-100 text-gray-700 dark:bg-gray-700/30 dark:text-gray-400',
};

const importInput = ref<HTMLInputElement | null>(null);
const isImporting = ref(false);

function triggerImport() {
    importInput.value?.click();
}

function handleImportFile(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) {
        return;
    }

    isImporting.value = true;
    const formData = new FormData();
    formData.append('file', file);

    router.post(TravelSpotController.importMethod.url(), formData, {
        onFinish: () => {
            isImporting.value = false;
            if (importInput.value) {
                importInput.value.value = '';
            }
        },
    });
}

const activeFilter = ref<string | null>(null);

const filteredSpots = computed(() =>
    activeFilter.value
        ? props.spots.filter((s) => s.category === activeFilter.value)
        : props.spots,
);

const showDialog = ref(false);
const editingSpot = ref<TravelSpot | null>(null);
const isFetchingMeta = ref(false);

const form = useForm({
    title: '',
    address: '',
    category: 'other',
    maps_url: '',
    preview_image: '',
    lat: '',
    lng: '',
    visit_time: '',
    is_visited: false,
    _meta_url: '',
});

function openCreate() {
    editingSpot.value = null;
    form.title = '';
    form.address = '';
    form.category = 'other';
    form.maps_url = '';
    form.preview_image = '';
    form.lat = '';
    form.lng = '';
    form.visit_time = '';
    form.is_visited = false;
    form._meta_url = '';
    form.clearErrors();
    showDialog.value = true;
}

function openEdit(spot: TravelSpot) {
    editingSpot.value = spot;
    form.title = spot.title;
    form.address = spot.address ?? '';
    form.category = spot.category;
    form.maps_url = spot.maps_url ?? '';
    form.preview_image = spot.preview_image ?? '';
    form.lat = spot.lat ?? '';
    form.lng = spot.lng ?? '';
    form.visit_time = spot.visit_time ? spot.visit_time.slice(0, 16) : '';
    form.is_visited = spot.is_visited;
    form._meta_url = spot.maps_url ?? '';
    form.clearErrors();
    showDialog.value = true;
}

async function fetchMeta() {
    if (!form._meta_url) {
        return;
    }
    isFetchingMeta.value = true;
    try {
        const response = await fetch(TravelSpotController.fetchMeta.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
                        ?.content ?? '',
            },
            body: JSON.stringify({ url: form._meta_url }),
        });
        const data = await response.json();
        if (data.title && !form.title) {
            form.title = data.title;
        }
        if (data.address && !form.address) {
            form.address = data.address;
        }
        if (data.image) {
            form.preview_image = data.image;
        }
        if (data.lat && !form.lat) {
            form.lat = String(data.lat);
        }
        if (data.lng && !form.lng) {
            form.lng = String(data.lng);
        }
        if (data.category && form.category === 'other') {
            form.category = data.category;
        }
        if (!form.maps_url) {
            form.maps_url = form._meta_url;
        }
    } finally {
        isFetchingMeta.value = false;
    }
}

function submitForm() {
    const { _meta_url, ...payload } = form.data();
    if (editingSpot.value) {
        form.transform(() => payload).put(
            TravelSpotController.update.url(editingSpot.value!.id),
            {
                onSuccess: () => {
                    showDialog.value = false;
                },
            },
        );
    } else {
        form.transform(() => payload).post(TravelSpotController.store.url(), {
            onSuccess: () => {
                showDialog.value = false;
            },
        });
    }
}

function deleteSpot(spot: TravelSpot) {
    if (!confirm(`"${spot.title}" mekanını silmek istediğinize emin misiniz?`)) {
        return;
    }
    router.delete(TravelSpotController.destroy.url(spot.id));
}

function toggleVisited(spot: TravelSpot) {
    router.patch(
        TravelSpotController.update.url(spot.id),
        {
            title: spot.title,
            address: spot.address,
            category: spot.category,
            maps_url: spot.maps_url,
            preview_image: spot.preview_image,
            lat: spot.lat,
            lng: spot.lng,
            visit_time: spot.visit_time,
            is_visited: !spot.is_visited,
        },
        { preserveScroll: true },
    );
}

function formatDate(iso: string | null): string {
    if (!iso) {
        return '';
    }
    return new Intl.DateTimeFormat('tr-TR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(iso));
}
</script>

<template>
    <Head title="Seyahat Planım" />

    <div class="flex flex-col gap-6 p-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Seyahat Planım</h1>
                <p class="text-sm text-muted-foreground">
                    Gitmek istediğin yerleri planla ve takip et
                </p>
            </div>
            <div class="flex items-center gap-2">
                <input
                    ref="importInput"
                    type="file"
                    accept=".json"
                    class="hidden"
                    @change="handleImportFile"
                />
                <Button variant="outline" size="sm" :disabled="isImporting" @click="triggerImport">
                    <Upload class="mr-2 h-4 w-4" />
                    {{ isImporting ? 'Aktarılıyor...' : 'İçe Aktar' }}
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    as="a"
                    :href="TravelSpotController.exportMethod.url()"
                >
                    <Download class="mr-2 h-4 w-4" />
                    Dışa Aktar
                </Button>
                <Button @click="openCreate">
                    <Plus class="mr-2 h-4 w-4" />
                    Mekan Ekle
                </Button>
            </div>
        </div>

        <!-- Category filters -->
        <div class="flex flex-wrap gap-2">
            <button
                class="rounded-full border px-3 py-1 text-sm transition-colors"
                :class="
                    activeFilter === null
                        ? 'bg-primary text-primary-foreground border-primary'
                        : 'hover:bg-muted border-border'
                "
                @click="activeFilter = null"
            >
                Tümü ({{ spots.length }})
            </button>
            <button
                v-for="cat in categories"
                :key="cat.value"
                class="rounded-full border px-3 py-1 text-sm transition-colors"
                :class="
                    activeFilter === cat.value
                        ? 'bg-primary text-primary-foreground border-primary'
                        : 'hover:bg-muted border-border'
                "
                @click="activeFilter = activeFilter === cat.value ? null : cat.value"
            >
                {{ cat.label }} ({{ spots.filter((s) => s.category === cat.value).length }})
            </button>
        </div>

        <!-- Empty state -->
        <div
            v-if="filteredSpots.length === 0"
            class="flex flex-col items-center justify-center rounded-xl border border-dashed py-16 text-center"
        >
            <MapPin class="mb-4 h-12 w-12 text-muted-foreground" />
            <p class="text-lg font-medium">Henüz mekan yok</p>
            <p class="mt-1 text-sm text-muted-foreground">
                İlk mekanını eklemek için "Mekan Ekle" butonuna tıkla
            </p>
        </div>

        <!-- Spots grid -->
        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <div
                v-for="spot in filteredSpots"
                :key="spot.id"
                class="group relative flex flex-col overflow-hidden rounded-xl border bg-card transition-shadow hover:shadow-md"
                :class="spot.is_visited ? 'opacity-60' : ''"
            >
                <!-- Preview image -->
                <div class="relative aspect-video overflow-hidden bg-muted">
                    <img
                        v-if="spot.preview_image"
                        :src="spot.preview_image"
                        :alt="spot.title"
                        class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                    />
                    <div
                        v-else
                        class="flex h-full w-full items-center justify-center text-muted-foreground"
                    >
                        <MapPin class="h-10 w-10" />
                    </div>

                    <!-- Visited badge -->
                    <div
                        v-if="spot.is_visited"
                        class="absolute left-2 top-2 rounded-full bg-green-500 px-2 py-0.5 text-xs font-medium text-white"
                    >
                        Gidildi ✓
                    </div>

                    <!-- Category badge -->
                    <div
                        class="absolute right-2 top-2 flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="colorClassMap[spot.category_color] ?? colorClassMap['gray']"
                    >
                        <component :is="iconMap[spot.category_icon]" class="h-3 w-3" />
                        {{ spot.category_label }}
                    </div>
                </div>

                <!-- Content -->
                <div class="flex flex-1 flex-col gap-2 p-3">
                    <h3
                        class="font-semibold leading-tight"
                        :class="spot.is_visited ? 'line-through text-muted-foreground' : ''"
                    >
                        {{ spot.title }}
                    </h3>

                    <p v-if="spot.address" class="text-xs text-muted-foreground leading-tight">
                        📍 {{ spot.address }}
                    </p>

                    <p v-if="spot.visit_time" class="text-xs text-muted-foreground">
                        🗓 {{ formatDate(spot.visit_time) }}
                    </p>

                    <!-- Actions -->
                    <div class="mt-auto flex items-center justify-between pt-2">
                        <div class="flex items-center gap-1.5">
                            <Checkbox
                                :id="`visited-${spot.id}`"
                                :checked="spot.is_visited"
                                @update:checked="toggleVisited(spot)"
                            />
                            <label
                                :for="`visited-${spot.id}`"
                                class="cursor-pointer text-xs text-muted-foreground"
                            >
                                Gidildi
                            </label>
                        </div>

                        <div class="flex items-center gap-1">
                            <a
                                v-if="spot.maps_url"
                                :href="spot.maps_url"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <Button variant="ghost" size="icon" class="h-7 w-7">
                                    <ExternalLink class="h-3.5 w-3.5" />
                                </Button>
                            </a>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-7 w-7"
                                @click="openEdit(spot)"
                            >
                                <Edit2 class="h-3.5 w-3.5" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-7 w-7 text-destructive hover:text-destructive"
                                @click="deleteSpot(spot)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Dialog -->
    <Dialog v-model:open="showDialog">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ editingSpot ? 'Mekanı Düzenle' : 'Yeni Mekan Ekle' }}
                </DialogTitle>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submitForm">
                <!-- Meta URL fetcher -->
                <div class="rounded-lg border bg-muted/40 p-3">
                    <Label class="mb-1.5 block text-xs font-medium text-muted-foreground">
                        URL'den otomatik doldur (Google Maps, web sitesi vb.)
                    </Label>
                    <div class="flex gap-2">
                        <Input
                            v-model="form._meta_url"
                            placeholder="https://..."
                            class="text-sm"
                        />
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            :disabled="isFetchingMeta || !form._meta_url"
                            @click="fetchMeta"
                        >
                            {{ isFetchingMeta ? 'Çekiliyor...' : 'Çek' }}
                        </Button>
                    </div>
                </div>

                <!-- Title -->
                <div class="grid gap-1.5">
                    <Label for="title">Başlık *</Label>
                    <Input id="title" v-model="form.title" placeholder="Mekan adı" required />
                    <InputError :message="form.errors.title" />
                </div>

                <!-- Address -->
                <div class="grid gap-1.5">
                    <Label for="address">Adres</Label>
                    <Input id="address" v-model="form.address" placeholder="Sokak, Şehir, Ülke" />
                    <InputError :message="form.errors.address" />
                </div>

                <!-- Category -->
                <div class="grid gap-1.5">
                    <Label>Kategori *</Label>
                    <Select v-model="form.category">
                        <SelectTrigger>
                            <SelectValue placeholder="Kategori seç" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="cat in categories"
                                :key="cat.value"
                                :value="cat.value"
                            >
                                {{ cat.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.category" />
                </div>

                <!-- Maps URL -->
                <div class="grid gap-1.5">
                    <Label for="maps_url">Google Maps Linki</Label>
                    <Input
                        id="maps_url"
                        v-model="form.maps_url"
                        placeholder="https://maps.google.com/..."
                    />
                    <InputError :message="form.errors.maps_url" />
                </div>

                <!-- Preview image -->
                <div class="grid gap-1.5">
                    <Label for="preview_image">Görsel URL</Label>
                    <Input
                        id="preview_image"
                        v-model="form.preview_image"
                        placeholder="https://..."
                    />
                    <InputError :message="form.errors.preview_image" />
                    <img
                        v-if="form.preview_image"
                        :src="form.preview_image"
                        alt="Önizleme"
                        class="mt-1 h-24 w-full rounded-md object-cover"
                    />
                </div>

                <!-- Coordinates -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label for="lat">Enlem</Label>
                        <Input id="lat" v-model="form.lat" type="number" step="any" placeholder="41.0082" />
                        <InputError :message="form.errors.lat" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="lng">Boylam</Label>
                        <Input id="lng" v-model="form.lng" type="number" step="any" placeholder="28.9784" />
                        <InputError :message="form.errors.lng" />
                    </div>
                </div>

                <!-- Visit time -->
                <div class="grid gap-1.5">
                    <Label for="visit_time">Ziyaret Tarihi ve Saati</Label>
                    <Input id="visit_time" v-model="form.visit_time" type="datetime-local" />
                    <InputError :message="form.errors.visit_time" />
                </div>

                <!-- Is visited -->
                <div class="flex items-center gap-2">
                    <Checkbox
                        id="is_visited"
                        :checked="form.is_visited"
                        @update:checked="(v) => (form.is_visited = !!v)"
                    />
                    <Label for="is_visited" class="cursor-pointer">Ziyaret edildi</Label>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="showDialog = false">
                        İptal
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ editingSpot ? 'Güncelle' : 'Ekle' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
