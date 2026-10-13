"use client";

import QRCode from "qrcode";
import { useEffect, useState } from "react";

export function QrCode({ value, size = 120 }: { value: string; size?: number }) {
  const [src, setSrc] = useState<string | null>(null);
  useEffect(() => {
    QRCode.toDataURL(value, { margin: 1, width: size * 2, errorCorrectionLevel: "M" }).then(setSrc);
  }, [value, size]);
  // eslint-disable-next-line @next/next/no-img-element
  return src ? <img src={src} alt="QR code" width={size} height={size} /> : <div style={{ width: size, height: size }} />;
}
