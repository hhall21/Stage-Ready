import hero from '../../levronemmside.jpg';
import login from '../../frankzanefront.jpg';
import dashboard from '../../bb1back.webp';
import detail from '../../levronethreequarter.jpg';
import front from '../../levronefront.jpg';

// Vite publishes these resource images with versioned asset URLs.
export const athleteImages: Record<
    'hero' | 'login' | 'dashboard' | 'detail' | 'front',
    string | null
> = {
    hero,
    login,
    dashboard,
    detail,
    front,
};
