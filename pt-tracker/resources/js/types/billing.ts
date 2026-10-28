export type CoachPlan = {
    discipline: 'powerlifting' | 'bodybuilding';
    label: string;
    amount_pence: number;
    price_label: string;
    currency: 'gbp';
    interval: 'month';
};

export type CoachPlans = Record<'powerlifting' | 'bodybuilding', CoachPlan>;
