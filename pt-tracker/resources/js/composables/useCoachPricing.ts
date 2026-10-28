import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useCoachPricing() {
    const page = usePage();
    const plans = computed(() => page.props.coachPlans);
    const coachPlan = computed(() => {
        const user = page.props.auth.user;
        return user?.role === 'coach'
            ? plans.value[
                  user.is_bodybuilding_coach ? 'bodybuilding' : 'powerlifting'
              ]
            : null;
    });

    return { plans, coachPlan };
}
