import React from 'react';
import { Link as InertiaLink } from '@inertiajs/react';

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

export default Link;
