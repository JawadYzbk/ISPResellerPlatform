import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import type { ResolvedComponent } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

const pages = import.meta.glob<{ default: ResolvedComponent }>('./pages/**/*.tsx', { eager: true });

createInertiaApp({
    resolve: (name) => pages[`./pages/${name}.tsx`] as { default: ResolvedComponent },
    setup({ el, App, props }) {
        const root = createRoot(el!);
        root.render(<App {...props} />);
    },
    progress: {
        color: '#1b6b63',
    },
});
