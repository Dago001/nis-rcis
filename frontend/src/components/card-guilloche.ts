function gcd(a: number, b: number): number {
  return b ? gcd(b, a % b) : a;
}

function rosette(cx: number, cy: number, R: number, r: number, d: number, copies: number, col: string, sw: number, op: number): string {
  let out = "";
  for (let c = 0; c < copies; c++) {
    let p = "";
    const rot = (c * 2 * Math.PI) / copies / 3;
    for (let i = 0; i <= 1400; i++) {
      const t = ((i / 1400) * 2 * Math.PI * r) / gcd(R, r);
      const x = (R - r) * Math.cos(t) + d * Math.cos(((R - r) / r) * t);
      const y = (R - r) * Math.sin(t) - d * Math.sin(((R - r) / r) * t);
      const X = cx + x * Math.cos(rot) - y * Math.sin(rot);
      const Y = cy + x * Math.sin(rot) + y * Math.cos(rot);
      p += (i ? "L" : "M") + X.toFixed(1) + " " + Y.toFixed(1);
    }
    out += `<path d="${p}" fill="none" stroke="${col}" stroke-width="${sw}" opacity="${op}"/>`;
  }
  return out;
}

function waves(w: number, n: number, amp: number, col: string, sw: number, op: number, y0: number, y1: number): string {
  let out = "";
  for (let k = 0; k < n; k++) {
    let p = "";
    const base = y0 + ((y1 - y0) * k) / n;
    const ph = k * 0.35;
    for (let x = 0; x <= w; x += 6) {
      const y = base + amp * Math.sin(x / 38 + ph) + amp * 0.5 * Math.sin(x / 17 - ph * 1.7);
      p += (x ? "L" : "M") + x + " " + y.toFixed(1);
    }
    out += `<path d="${p}" fill="none" stroke="${col}" stroke-width="${sw}" opacity="${op}"/>`;
  }
  return out;
}

function svgUri(w: number, h: number, body: string): string {
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${w} ${h}" preserveAspectRatio="none">${body}</svg>`;
  return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;
}

/** Card body security pattern (856 × 540 design units). Built once and shared by every card on the page. */
export const bodyPattern = svgUri(
  856,
  540,
  waves(856, 46, 7, "#1f8f59", 0.5, 0.22, 110, 540) +
    rosette(690, 330, 150, 55, 80, 3, "#15784a", 0.45, 0.28) +
    rosette(690, 330, 96, 35, 52, 2, "#c9a13b", 0.45, 0.35) +
    rosette(120, 470, 120, 44, 62, 2, "#15784a", 0.4, 0.16),
);

function headerPattern(h: number): string {
  return svgUri(856, h, waves(856, 18, 5, "#7fd1a4", 0.5, 0.35, -6, h + 6) + rosette(428, h / 2, 190, 70, 110, 2, "#e8cf7a", 0.4, 0.35));
}

export const frontHeaderPattern = headerPattern(104);
export const backHeaderPattern = headerPattern(56);
