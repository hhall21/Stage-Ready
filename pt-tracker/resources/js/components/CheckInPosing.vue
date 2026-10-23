<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import SlidePanel from '@/components/SlidePanel.vue';
import { motionScrollBehavior } from '@/lib/motion';
import { api, ApiError, displayDate, message } from '@/lib/tracker-api';
import type { CheckIn, CheckInPosing, TrackerUser } from '@/lib/tracker-api';

const props = defineProps<{
    clientId: number;
    checkIn: CheckIn;
    user: TrackerUser;
    initiallyOpen?: boolean;
}>();
const emit = defineEmits<{ updated: [record: Partial<CheckIn>] }>();
const open = ref(false);
const loading = ref(false);
const busy = ref('');
const error = ref('');
const success = ref('');
const data = ref<CheckInPosing | null>(null);
const panel = ref<HTMLElement | null>(null);
const reviewVersion = ref<number | null>(null);
const reviewNeedsReset = ref(false);
const reviewDirty = ref(false);
const review = reactive({
    posing_rating: '' as number | '',
    feedback: '',
    is_competition_prep: false,
    flatness_rating: '' as number | '',
    fullness_rating: '' as number | '',
});
type Selection = {
    file: File;
    url: string;
    error: string;
    preparing: boolean;
    prepared: boolean;
};
const pending = reactive<Record<string, Selection>>({});
const poseErrors = reactive<Record<string, string>>({});
let alive = true;
let request = 0;
const base = computed(
    () =>
        '/api/clients/' +
        props.clientId +
        '/daily-check-ins/' +
        props.checkIn.id +
        '/posing',
);
const isClient = computed(() => props.user.role === 'client');
const poses = computed(() => [
    ...(data.value?.standard_poses ?? []),
    ...(data.value?.additional_poses ?? []),
]);
const missing = computed(() =>
    poses.value.filter((pose) => !photoFor(pose.key)),
);
const hasPending = computed(() => Object.keys(pending).length > 0);
const currentReviews = computed(
    () => data.value?.reviews.filter((item) => item.is_current) ?? [],
);
const status = computed(() => {
    if (!data.value && props.checkIn.submitted_at)
        return 'Submitted — view feedback';
    if (currentReviews.value.length) return 'Reviewed';
    if (data.value?.reviews.length && !data.value.submitted_at)
        return 'Photos changed — submit again';
    if (data.value ? data.value.submitted_at : props.checkIn.submitted_at)
        return 'Ready for review';
    return 'Draft — submit when ready';
});
const photoCount = computed(
    () => data.value?.photos.length ?? props.checkIn.pose_photos_count ?? 0,
);
const reviewAllowed = computed(
    () =>
        !!data.value?.submitted_at &&
        !reviewNeedsReset.value &&
        reviewVersion.value === data.value.photo_version,
);
function photoFor(key: string) {
    return data.value?.photos.find((photo) => photo.pose_key === key);
}
function imageUrl(photo: CheckInPosing['photos'][number]) {
    return (
        photo.url +
        (photo.url.includes('?') ? '&' : '?') +
        'v=' +
        data.value?.photo_version
    );
}
function resetReview() {
    const own = data.value?.reviews.find(
        (item) => item.coach_id === props.user.id && item.is_current,
    );
    review.posing_rating = own?.posing_rating ?? '';
    review.feedback = own?.feedback ?? '';
    review.is_competition_prep = own?.is_competition_prep ?? false;
    review.flatness_rating = own?.flatness_rating ?? '';
    review.fullness_rating = own?.fullness_rating ?? '';
    reviewVersion.value = data.value?.photo_version ?? null;
    reviewNeedsReset.value = false;
    reviewDirty.value = false;
}
function acknowledgeLatestPhotos() {
    reviewVersion.value = data.value?.photo_version ?? null;
    reviewNeedsReset.value = false;
}
function apply(result: CheckInPosing, preserveReview: boolean) {
    data.value = result;
    emit('updated', {
        photo_version: result.photo_version,
        submitted_at: result.submitted_at,
        pose_photos_count: result.photos.length,
    });
    if (!isClient.value) {
        if (preserveReview && reviewVersion.value !== null) {
            if (reviewVersion.value !== result.photo_version)
                reviewNeedsReset.value = true;
        } else resetReview();
    }
}
async function load(preserveReview = false, clearError = true) {
    const current = ++request;
    loading.value = true;
    if (clearError) error.value = '';
    try {
        const result = await api<{ data: CheckInPosing }>(base.value);
        if (alive && current === request) apply(result.data, preserveReview);
    } catch (cause) {
        if (alive && current === request) error.value = message(cause);
    } finally {
        if (alive && current === request) loading.value = false;
    }
}
async function toggle() {
    open.value = !open.value;
    if (open.value && !data.value && !loading.value) await load();
}
function discard(key: string) {
    const selection = pending[key];
    if (selection) URL.revokeObjectURL(selection.url);
    delete pending[key];
}
async function prepareImage(file: File): Promise<File> {
    const bitmap = await createImageBitmap(file, {
        imageOrientation: 'from-image',
    });
    try {
        const scale = Math.min(1, 2000 / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(bitmap.width * scale));
        canvas.height = Math.max(1, Math.round(bitmap.height * scale));
        const context = canvas.getContext('2d');
        if (!context)
            throw new Error(
                'Image preparation is unavailable in this browser.',
            );
        context.fillStyle = '#111';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        for (const quality of [0.9, 0.8, 0.7]) {
            const blob = await new Promise<Blob | null>((resolve) =>
                canvas.toBlob(resolve, 'image/jpeg', quality),
            );
            if (blob && blob.size <= 1_800_000) {
                const filename = file.name.replace(/\.[^.]*$/, '') + '.jpg';
                return new File([blob], filename, { type: 'image/jpeg' });
            }
        }
        throw new Error(
            'This photo could not be made small enough. Choose a smaller image.',
        );
    } finally {
        bitmap.close();
    }
}
async function selectPhoto(key: string, event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file) return;
    poseErrors[key] = '';
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        poseErrors[key] = 'Choose a JPEG, PNG or WebP photo.';
        return;
    }
    if (file.size > 20 * 1024 * 1024) {
        poseErrors[key] = 'Choose an original photo no larger than 20 MB.';
        return;
    }
    discard(key);
    pending[key] = {
        file,
        url: URL.createObjectURL(file),
        error: '',
        preparing: true,
        prepared: false,
    };
    const selection = pending[key];
    success.value = '';
    try {
        const prepared = await prepareImage(file);
        if (!alive || pending[key] !== selection) return;
        URL.revokeObjectURL(selection.url);
        selection.file = prepared;
        selection.url = URL.createObjectURL(prepared);
        selection.prepared = true;
    } catch (cause) {
        if (alive && pending[key] === selection)
            selection.error =
                cause instanceof Error
                    ? cause.message +
                      ' Your original selection is still shown; choose another photo to try again.'
                    : 'Could not prepare this photo. Choose another JPEG, PNG or WebP image.';
    } finally {
        if (alive && pending[key] === selection) selection.preparing = false;
    }
}
async function mutate(
    action: string,
    url: string,
    method: string,
    body: unknown,
    successMessage: string,
): Promise<boolean> {
    if (busy.value || loading.value) return false;
    busy.value = action;
    error.value = '';
    success.value = '';
    const current = request;
    try {
        const result = await api<{ data: CheckInPosing }>(url, method, body);
        if (!alive || current !== request) return false;
        apply(result.data, false);
        success.value = successMessage;
        return true;
    } catch (cause) {
        if (!alive || current !== request) return false;
        error.value = message(cause);
        if (cause instanceof ApiError && cause.status === 409) {
            error.value =
                'The photos changed in another session. The latest photos have been reloaded. Your selected files and unsaved feedback are still here.';
            await load(true, false);
        }
        return false;
    } finally {
        if (alive) busy.value = '';
    }
}
async function upload(key: string) {
    const selected = pending[key];
    const version = data.value?.photo_version;
    if (!selected?.prepared || selected.preparing || version === undefined)
        return;
    selected.error = '';
    const body = new FormData();
    body.append('photo', selected.file);
    body.append('photo_version', String(version));
    const saved = await mutate(
        'upload:' + key,
        base.value + '/photos/' + key,
        'POST',
        body,
        'Photo uploaded. You can submit your check-in whenever you are ready.',
    );
    if (!alive) return;
    if (saved) discard(key);
    else if (pending[key]) pending[key].error = error.value;
}
async function remove(key: string) {
    if (
        !data.value ||
        !window.confirm(
            'Remove this pose photo? Submit your check-in again if you want a fresh review.',
        )
    )
        return;
    await mutate(
        'remove:' + key,
        base.value + '/photos/' + key,
        'DELETE',
        { photo_version: data.value.photo_version },
        'Photo removed. Previous feedback is kept with its original photo version.',
    );
}
async function submit() {
    if (!data.value || hasPending.value) return;
    await mutate(
        'submit',
        base.value + '/submit',
        'POST',
        { photo_version: data.value.photo_version },
        'Check-in submitted for your coach to review.',
    );
}
async function saveReview() {
    if (!reviewAllowed.value || !review.feedback.trim()) return;
    await mutate(
        'review',
        base.value + '/review',
        'PUT',
        {
            photo_version: reviewVersion.value,
            posing_rating: review.posing_rating,
            feedback: review.feedback.trim(),
            is_competition_prep: review.is_competition_prep,
            flatness_rating: review.is_competition_prep
                ? review.flatness_rating || null
                : null,
            fullness_rating: review.is_competition_prep
                ? review.fullness_rating || null
                : null,
        },
        'Your posing review has been saved for this photo set.',
    );
}
watch(
    () => props.checkIn.photo_version,
    (version) => {
        if (data.value && version !== data.value.photo_version && !busy.value)
            void load(true);
    },
);
watch(
    () => props.initiallyOpen,
    async (value) => {
        if (!value) return;
        open.value = true;
        if (!data.value) await load();
        await nextTick();
        if (alive)
            panel.value?.scrollIntoView({
                behavior: motionScrollBehavior(),
                block: 'start',
            });
    },
    { immediate: true },
);
onBeforeUnmount(() => {
    alive = false;
    request++;
    Object.keys(pending).forEach(discard);
});
</script>

<template>
    <section ref="panel" class="checkin-posing">
        <div class="section-bar">
            <div>
                <span class="status-pill">{{ status }}</span>
                <span class="posing-count"
                    >{{ photoCount }} photos<template v-if="data">
                        · {{ poses.length }} poses</template
                    ></span
                >
            </div>
            <button
                type="button"
                class="text-link"
                :aria-expanded="open"
                :aria-controls="'posing-' + checkIn.id"
                @click="toggle"
            >
                {{
                    open
                        ? 'Hide poses and feedback'
                        : 'Poses and coach feedback ↗'
                }}
            </button>
        </div>
        <SlidePanel :open="open" :id="'posing-' + checkIn.id">
            <p v-if="error" class="notice" role="alert">{{ error }}</p>
            <p v-if="success" class="notice" role="status">{{ success }}</p>
            <p v-if="loading" class="loading-message" role="status">
                Loading posing check-in…
            </p>
            <button
                v-if="!data && !loading"
                type="button"
                class="text-link"
                @click="load()"
            >
                Retry loading poses
            </button>
            <template v-if="data">
                <div class="section-bar posing-intro">
                    <div>
                        <h4>Bodybuilding and relaxed poses</h4>
                        <p class="form-help">
                            One clear, full-body photo for each pose. Choose
                            JPEG, PNG or WebP photos up to 20 MB each. Photos
                            are resized for clear check-ins and smaller uploads.
                        </p>
                        <p v-if="isClient" class="form-help">
                            Upload any photos you want to share, or submit
                            without photos. Your coach can follow up on anything
                            left blank. Replacing or removing a photo requires a
                            fresh submission and review.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="text-link"
                        :disabled="!!busy || loading"
                        @click="load(reviewDirty)"
                    >
                        Refresh photos
                    </button>
                </div>
                <p v-if="missing.length" class="form-help">
                    Not uploaded:
                    {{ missing.map((pose) => pose.label).join(', ') }}.
                </p>
                <div class="posing-grid">
                    <article
                        v-for="pose in poses"
                        :key="pose.key"
                        class="pose-card"
                    >
                        <h5>{{ pose.label }}</h5>
                        <template v-if="photoFor(pose.key)">
                            <a
                                :href="imageUrl(photoFor(pose.key)!)"
                                target="_blank"
                                rel="noopener"
                                :aria-label="
                                    'Open ' + pose.label + ' photo in a new tab'
                                "
                            >
                                <img
                                    :src="imageUrl(photoFor(pose.key)!)"
                                    :alt="pose.label + ' check-in photo'"
                                    loading="lazy"
                                    decoding="async"
                                />
                            </a>
                            <span class="form-help">Uploaded</span>
                        </template>
                        <p v-else class="pose-placeholder">Not uploaded</p>
                        <template v-if="isClient">
                            <label class="pose-file-label"
                                >{{
                                    photoFor(pose.key)
                                        ? 'Choose replacement'
                                        : 'Choose photo'
                                }}
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    :disabled="!!busy || loading"
                                    @change="selectPhoto(pose.key, $event)"
                                />
                            </label>
                            <p
                                v-if="poseErrors[pose.key]"
                                class="form-help"
                                role="alert"
                            >
                                {{ poseErrors[pose.key] }}
                            </p>
                            <template v-if="pending[pose.key]">
                                <img
                                    :src="pending[pose.key].url"
                                    :alt="
                                        'Selected ' +
                                        pose.label +
                                        ' photo, not uploaded yet'
                                    "
                                    class="pose-pending-preview"
                                />
                                <p class="form-help pose-filename">
                                    Not uploaded:
                                    {{ pending[pose.key].file.name }}
                                </p>
                                <p
                                    v-if="pending[pose.key].preparing"
                                    class="form-help"
                                    role="status"
                                >
                                    Preparing photo…
                                </p>
                                <progress
                                    v-if="busy === 'upload:' + pose.key"
                                    :aria-label="'Uploading ' + pose.label"
                                />
                                <p
                                    v-if="pending[pose.key].error"
                                    class="form-help"
                                    role="alert"
                                >
                                    {{ pending[pose.key].error }}
                                </p>
                                <div class="row-actions">
                                    <button
                                        type="button"
                                        class="button button-small"
                                        :disabled="
                                            !!busy ||
                                            loading ||
                                            !pending[pose.key].prepared ||
                                            pending[pose.key].preparing
                                        "
                                        @click="upload(pose.key)"
                                    >
                                        {{
                                            busy === 'upload:' + pose.key
                                                ? 'Uploading…'
                                                : 'Upload photo'
                                        }}
                                    </button>
                                    <button
                                        type="button"
                                        class="text-link"
                                        :disabled="!!busy || loading"
                                        @click="discard(pose.key)"
                                    >
                                        Discard selection
                                    </button>
                                </div>
                            </template>
                            <button
                                v-if="photoFor(pose.key)"
                                type="button"
                                class="text-link pose-remove"
                                :disabled="!!busy || loading"
                                @click="remove(pose.key)"
                            >
                                {{
                                    busy === 'remove:' + pose.key
                                        ? 'Removing…'
                                        : 'Remove uploaded photo'
                                }}
                            </button>
                        </template>
                    </article>
                </div>
                <div v-if="isClient" class="form-actions">
                    <button
                        type="button"
                        class="button"
                        :disabled="
                            !!busy ||
                            loading ||
                            hasPending ||
                            !!data.submitted_at
                        "
                        @click="submit"
                    >
                        {{
                            busy === 'submit'
                                ? 'Submitting…'
                                : data.submitted_at
                                  ? 'Submitted for review'
                                  : 'Submit check-in ↗'
                        }}
                    </button>
                    <p v-if="hasPending" class="form-help">
                        Upload or discard your selected photos before
                        submitting.
                    </p>
                </div>
                <section class="posing-reviews">
                    <div class="section-bar">
                        <h4>Coach feedback</h4>
                        <span class="eyebrow">DAILY POSING REVIEW</span>
                    </div>
                    <p v-if="!data.reviews.length" class="form-help">
                        {{
                            data.submitted_at
                                ? 'This photo set is ready for your coach to review.'
                                : 'Submit whenever you are ready to request a review.'
                        }}
                    </p>
                    <p
                        v-if="
                            data.reviews.some(
                                (item) => item.is_competition_prep,
                            )
                        "
                        class="form-help"
                    >
                        Coach assessment: flatness 1 = not flat, 10 = very flat;
                        fullness 1 = low fullness, 10 = very full.
                    </p>
                    <article
                        v-for="item in data.reviews"
                        :key="item.id"
                        class="posing-review"
                    >
                        <div class="section-bar">
                            <strong>{{ item.coach_name }}</strong
                            ><span class="status-pill">{{
                                item.is_current
                                    ? 'Current photo set'
                                    : 'Earlier photo set — feedback may no longer apply'
                            }}</span>
                        </div>
                        <p class="form-help">
                            {{ displayDate(item.reviewed_at) }} · Posing
                            {{ item.posing_rating }}/10
                        </p>
                        <p v-if="item.is_competition_prep" class="form-help">
                            Competition prep · Flatness
                            {{ item.flatness_rating ?? 'Not rated'
                            }}{{ item.flatness_rating === null ? '' : '/10' }} ·
                            Fullness {{ item.fullness_rating ?? 'Not rated'
                            }}{{ item.fullness_rating === null ? '' : '/10' }}
                        </p>
                        <p class="posing-feedback">{{ item.feedback }}</p>
                    </article>
                </section>
                <form
                    v-if="!isClient"
                    class="posing-review-form"
                    @submit.prevent="saveReview"
                    @input="reviewDirty = true"
                    @change="reviewDirty = true"
                >
                    <h4>
                        {{
                            currentReviews.some(
                                (item) => item.coach_id === user.id,
                            )
                                ? 'Edit your review'
                                : 'Add your review'
                        }}
                    </h4>
                    <p v-if="reviewNeedsReset" class="notice" role="alert">
                        The photo set changed while you were reviewing it. Your
                        draft feedback is preserved below. Check the latest
                        photos, then continue your draft for this photo set.
                        <button
                            type="button"
                            class="text-link"
                            :disabled="!!busy || loading"
                            @click="acknowledgeLatestPhotos"
                        >
                            Continue draft for latest photos
                        </button>
                    </p>
                    <p v-else-if="!data.submitted_at" class="form-help">
                        The client must submit their check-in before you can
                        save a review.
                    </p>
                    <div class="form-grid">
                        <label
                            >Overall posing (1–10)<input
                                v-model.number="review.posing_rating"
                                type="number"
                                min="1"
                                max="10"
                                step="1"
                                required
                                :disabled="!!busy || loading || !reviewAllowed"
                            /><span class="form-help"
                                >1 = needs work · 10 = stage-ready</span
                            ></label
                        >
                        <label class="posing-prep-option"
                            ><input
                                v-model="review.is_competition_prep"
                                type="checkbox"
                                :disabled="!!busy || loading || !reviewAllowed"
                            />Competition prep check-in</label
                        >
                        <template v-if="review.is_competition_prep">
                            <label
                                >Flatness (1–10)<input
                                    v-model.number="review.flatness_rating"
                                    type="number"
                                    min="1"
                                    max="10"
                                    step="1"
                                    :disabled="
                                        !!busy || loading || !reviewAllowed
                                    "
                                /><span class="form-help"
                                    >1 = not flat · 10 = very flat</span
                                ></label
                            >
                            <label
                                >Fullness (1–10)<input
                                    v-model.number="review.fullness_rating"
                                    type="number"
                                    min="1"
                                    max="10"
                                    step="1"
                                    :disabled="
                                        !!busy || loading || !reviewAllowed
                                    "
                                /><span class="form-help"
                                    >1 = low fullness · 10 = very full</span
                                ></label
                            >
                        </template>
                        <label class="full-width"
                            >Posing feedback<textarea
                                v-model="review.feedback"
                                rows="6"
                                maxlength="10000"
                                required
                                :disabled="!!busy || loading"
                                placeholder="What improved today, and what should the client practise next?"
                            /><span class="form-help"
                                >{{ review.feedback.length.toLocaleString() }} /
                                10,000 characters</span
                            ></label
                        >
                    </div>
                    <button
                        class="button"
                        :disabled="
                            !!busy ||
                            loading ||
                            !reviewAllowed ||
                            !review.feedback.trim()
                        "
                    >
                        {{
                            busy === 'review'
                                ? 'Saving review…'
                                : 'Save coach review ↗'
                        }}
                    </button>
                </form>
            </template>
        </SlidePanel>
    </section>
</template>
