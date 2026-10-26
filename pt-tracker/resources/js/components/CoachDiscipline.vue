<script setup lang="ts">
import type { TrackerUser } from '@/lib/tracker-api';

defineProps<{ user: TrackerUser }>();
</script>
<template>
    <section class="panel" aria-label="Your coaching discipline">
        <h2>
            {{
                user.is_bodybuilding_coach
                    ? 'Bodybuilding coach'
                    : 'Powerlifting coach'
            }}
        </h2>
        <p class="form-help">
            {{
                user.is_bodybuilding_coach
                    ? 'Your coaching includes private check-in photos and posing reviews.'
                    : 'Your coaching includes training, nutrition and progress tracking.'
            }}
        </p>
    </section>
</template>
