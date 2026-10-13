import { router, usePage } from '@inertiajs/react';

export function useRouter() {
  return {
    push: (href: string, options?: any) => router.visit(href, options),
    replace: (href: string, options?: any) => router.replace(href, options),
    back: () => window.history.back(),
    forward: () => window.history.forward(),
    refresh: () => router.reload(),
    prefetch: () => {},
  };
}

export function usePathname(): string {
  try {
    const { url } = usePage();
    return url ? url.split('?')[0] : (typeof window !== 'undefined' ? window.location.pathname : '/');
  } catch {
    return typeof window !== 'undefined' ? window.location.pathname : '/';
  }
}

export function useSearchParams(): URLSearchParams {
  try {
    const { url } = usePage();
    const query = url && url.includes('?') ? url.split('?')[1] : '';
    return new URLSearchParams(query);
  } catch {
    return new URLSearchParams(typeof window !== 'undefined' ? window.location.search : '');
  }
}

export function useParams(): Record<string, string> {
  const pathname = usePathname();
  const segments = pathname.split('/').filter(Boolean);
  try {
    const { props } = usePage<{ [key: string]: any }>();
    if (props && typeof props === 'object') {
      const result: Record<string, string> = {};
      for (const [k, v] of Object.entries(props)) {
        if (typeof v === 'string' || typeof v === 'number') {
          result[k] = String(v);
        }
      }
      if (result.id || result.center) return result;
    }
  } catch {}
  return { id: segments[segments.length - 1] || '' };
}

export function redirect(href: string) {
  router.visit(href);
}

export function notFound() {
  router.visit('/404');
}
