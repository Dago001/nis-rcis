import './app/globals.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { I18nProvider } from '@/components/I18nProvider';

function ensureFavicon() {
  if (typeof document === 'undefined') return;
  if (!document.querySelector("link[rel*='icon']")) {
    const link = document.createElement('link');
    link.rel = 'icon';
    link.type = 'image/x-icon';
    link.href = '/favicon.ico?v=1.1';
    document.head.appendChild(link);
  }
}

ensureFavicon();

createInertiaApp({
  title: (title) => title ? `${title} — Nigeria Immigration Service` : 'Nigeria Immigration Service - ECOWAS Residence Card Portal',
  resolve: (name) => resolvePageComponent(`./Pages/${name}.tsx`, import.meta.glob('./Pages/**/*.tsx')),
  setup({ el, App, props }) {
    ensureFavicon();
    const initialLang = (props.initialPage.props as any)?.locale;
    createRoot(el).render(
      <I18nProvider lang={initialLang}>
        <App {...props} />
      </I18nProvider>
    );
  },
});
