import athlete from '../../artwork/stage-athlete.svg';
import equipment from '../../artwork/training-equipment.svg';

// Original Stage-Ready artwork, published with versioned asset URLs.
export const athleteImages: Record<
    'hero' | 'login' | 'dashboard' | 'detail' | 'front',
    string | null
> = {
    hero: athlete,
    login: athlete,
    dashboard: equipment,
    detail: equipment,
    front: athlete,
};
