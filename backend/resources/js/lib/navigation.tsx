import React from 'react';
import { Link as InertiaLink, router, usePage } from '@inertiajs/react';

export function Link({ href, children, className, style, onClick, target, rel, prefetch, ...props }: any) {
  const isExternal =
    typeof href === 'string' &&
    (href.startsWith('http://') ||
      href.startsWith('https://') ||
      href.startsWith('mailto:') ||
      href.startsWith('tel:') ||
      href.startsWith('#'));

  if (isExternal || target === '_blank') {
    return (
      <a
        href={href}
        className={className}
        style={style}
        onClick={onClick}
        target={target}
        rel={rel}
        {...props}
      >
        {children}
      </a>
    );
  }

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

export function Image({
  src,
  alt,
  width,
  height,
  className,
  priority,
  fill,
  sizes,
  style,
  ...props
}: any) {
  const fillStyle: React.CSSProperties = fill
    ? {
        position: 'absolute',
        height: '100%',
        width: '100%',
        left: 0,
        top: 0,
        right: 0,
        bottom: 0,
        objectFit: 'cover',
        ...style,
      }
    : style;

  return (
    <img
      src={src}
      alt={alt || ''}
      width={!fill ? width : undefined}
      height={!fill ? height : undefined}
      className={className}
      style={fillStyle}
      loading={priority ? 'eager' : 'lazy'}
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
