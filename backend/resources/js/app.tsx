import './app/globals.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { I18nProvider } from '@/components/I18nProvider';

createInertiaApp({
  title: (title) => title ? `${title} — Nigeria Immigration Service` : 'Nigeria Immigration Service - ECOWAS Residence Card Portal',
  resolve: (name) => resolvePageComponent(`./Pages/${name}.tsx`, import.meta.glob('./Pages/**/*.tsx')),
  setup({ el, App, props }) {
    createRoot(el).render(
      <I18nProvider>
        <App {...props} />
      </I18nProvider>
    );
  },
});
