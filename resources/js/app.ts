import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { initializeTheme } from './composables/useAppearance';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load. Wrapped defensively —
// this must NEVER be able to throw and interfere with the Inertia app
// above successfully mounting. If localStorage/matchMedia access ever
// fails (sandboxed context, privacy mode, stale cached page state,
// etc.), the appearance just falls back to default rather than risking
// leaving the page stuck on its pre-mount loading placeholder.
try {
    initializeTheme();
} catch (error) {
    console.warn('initializeTheme() failed — continuing with default appearance.', error);
}