import './app/globals.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { I18nProvider } from '@/components/I18nProvider';

function ensureFavicon() {
  if (typeof document === 'undefined') return;
  const iconUrl = '/images/nis-logo.png';
  
  let link = document.querySelector("link[rel*='icon']") as HTMLLinkElement | null;
  if (!link) {
    link = document.createElement('link');
    link.rel = 'icon';
    document.head.appendChild(link);
  }
  link.type = 'image/png';
  link.href = iconUrl;

  let shortcut = document.querySelector("link[rel='shortcut icon']") as HTMLLinkElement | null;
  if (!shortcut) {
    shortcut = document.createElement('link');
    shortcut.rel = 'shortcut icon';
    document.head.appendChild(shortcut);
  }
  shortcut.type = 'image/png';
  shortcut.href = iconUrl;

  let apple = document.querySelector("link[rel='apple-touch-icon']") as HTMLLinkElement | null;
  if (!apple) {
    apple = document.createElement('link');
    apple.rel = 'apple-touch-icon';
    document.head.appendChild(apple);
  }
  apple.href = '/icons/apple-touch-icon.png';
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
