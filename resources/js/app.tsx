import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { rememberCurrentUrlAsPrevious } from '@/Components/ui/BackLink';
import QueryProvider from '@/Providers/QueryProvider';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

router.on('before', () => {
    rememberCurrentUrlAsPrevious();
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <QueryProvider>
                <App {...props} />
            </QueryProvider>,
        );
    },
    progress: {
        color: '#16a34a',
    },
});
