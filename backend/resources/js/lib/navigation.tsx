import React from 'react';
import { Link as InertiaLink, router, usePage } from '@inertiajs/react';

export function Link({ href, children, className, style, onClick, target, rel, prefetch, ...props }: any) {
  return (
    <InertiaLink
      href={href}
      className={className}
      style={style}
      onClick={onClick}
      target={target}
      rel={rel}
      {...props}
    >
      {children}
    </InertiaLink>
  );
}

export function Image({ src, alt, width, height, className, priority, fill, ...props }: any) {
  return (
    <img
      src={src}
      alt={alt || ''}
      width={width}
      height={height}
      className={className}
      {...props}
    />
  );
}

export default Link;

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
  return { id: segments[segments.length - 1] || '' };
}
