export type CoachPlan = {
    discipline: 'powerlifting' | 'bodybuilding';
    label: string;
    amount_pence: number;
    price_label: string;
    currency: 'gbp';
    interval: 'month';
    client_limit: number;
};

export type CoachPlans = Record<'powerlifting' | 'bodybuilding', CoachPlan>;
