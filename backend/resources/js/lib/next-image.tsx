import React from 'react';

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

export default Image;
