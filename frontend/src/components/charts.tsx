"use client";

import { useEffect, useId, useRef, useState, type ReactNode } from "react";

/*
 * Small, dependency-free SVG charts for the staff dashboard.
 *
 * Colours: series 1 = NIS green, series 2 = blue. The pair was checked with
 * the colour-vision-deficiency validator (worst ΔE 25 deutan) and both clear
 * 3:1 contrast on white. Text always uses ink colours, never series colours.
 * Every chart has a hover/focus tooltip and a data table, so nothing depends
 * on colour or hovering alone.
 */
export const SERIES = ["#2b892b", "#2a78d6"] as const;
const GRID = "#e5e7eb";
const AXIS = "#cbd5e1";
const MUTED = "#64748b";

const compact = (n: number) => new Intl.NumberFormat("en-NG", { notation: "compact", maximumFractionDigits: 1 }).format(n);

/** Round the axis maximum up to a clean number and give 4 ticks. */
function niceTicks(max: number): number[] {
  if (max <= 0) return [0, 1];
  const rough = max / 4;
  const pow = 10 ** Math.floor(Math.log10(rough));
  const step = [1, 2, 2.5, 5, 10].map((m) => m * pow).find((s) => s >= rough) ?? rough;
  return Array.from({ length: Math.ceil(max / step) + 1 }, (_, i) => Math.round(i * step * 100) / 100);
}

export function ChartCard({ title, subtitle, children, table, legend }: { title: string; subtitle?: string; children: ReactNode; table?: ReactNode; legend?: ReactNode }) {
  return (
    <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <header className="flex flex-wrap items-start justify-between gap-2 border-b border-slate-200 bg-nis-mint/60 px-5 py-3.5">
        <div>
          <h2 className="text-[15px] font-medium text-nis-primary-dark">{title}</h2>
          {subtitle && <p className="mt-0.5 text-xs text-slate-500">{subtitle}</p>}
        </div>
        {legend}
      </header>
      <div className="p-5">{children}</div>
      {table && (
        <details className="border-t border-slate-100 px-5 py-2.5 text-sm">
          <summary className="cursor-pointer text-xs font-medium text-nis-primary">Show data table</summary>
          <div className="mt-2 overflow-x-auto">{table}</div>
        </details>
      )}
    </section>
  );
}

export function Legend({ items }: { items: { label: string; color: string; kind?: "line" | "box" }[] }) {
  return (
    <ul className="flex flex-wrap gap-4 text-xs text-slate-600">
      {items.map((i) => (
        <li key={i.label} className="flex items-center gap-1.5">
          {i.kind === "line" ? (
            <span className="inline-block h-0.5 w-4 rounded" style={{ background: i.color }} aria-hidden />
          ) : (
            <span className="inline-block h-2.5 w-2.5 rounded-sm" style={{ background: i.color }} aria-hidden />
          )}
          {i.label}
        </li>
      ))}
    </ul>
  );
}

export function DataTable({ head, rows }: { head: string[]; rows: (string | number)[][] }) {
  return (
    <table className="w-full text-xs">
      <thead>
        <tr>{head.map((h) => <th key={h} className="py-1 pr-4 text-left font-medium text-slate-500">{h}</th>)}</tr>
      </thead>
      <tbody>
        {rows.map((r, i) => (
          <tr key={i} className="border-t border-slate-100">
            {r.map((c, j) => <td key={j} className={`py-1 pr-4 ${j > 0 ? "tabular-nums" : ""}`}>{c}</td>)}
          </tr>
        ))}
      </tbody>
    </table>
  );
}

function Tooltip({ x, y, width, children }: { x: number; y: number; width: number; children: ReactNode }) {
  const left = Math.min(Math.max(x, 70), width - 70);
  return (
    <div
      role="status"
      className="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs shadow-lg"
      style={{ left: `${(left / width) * 100}%`, top: y }}
    >
      {children}
    </div>
  );
}

const H = 240;

/**
 * Width of the chart's container in CSS pixels, so the SVG is drawn 1:1 and
 * its text stays a readable 11px at any card width.
 */
function useWidth(fallback = 640) {
  const ref = useRef<HTMLDivElement>(null);
  const [width, setWidth] = useState(fallback);
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const observer = new ResizeObserver(([entry]) => setWidth(Math.max(240, Math.round(entry.contentRect.width))));
    observer.observe(el);
    return () => observer.disconnect();
  }, []);
  return [ref, width] as const;
}
const PAD = { top: 16, right: 16, bottom: 28, left: 44 };

/**
 * Lines over time (one shared y-axis), with a crosshair that snaps to the
 * nearest month and lists every series.
 */
export function LineChart({ labels, series, format = String }: { labels: string[]; series: { name: string; values: number[] }[]; format?: (n: number) => string }) {
  const [hover, setHover] = useState<number | null>(null);
  const [ref, W] = useWidth();
  const max = Math.max(1, ...series.flatMap((s) => s.values));
  const ticks = niceTicks(max);
  const top = ticks[ticks.length - 1];
  const iw = W - PAD.left - PAD.right;
  const ih = H - PAD.top - PAD.bottom;
  const x = (i: number) => PAD.left + (labels.length === 1 ? iw / 2 : (i / (labels.length - 1)) * iw);
  const y = (v: number) => PAD.top + ih - (v / top) * ih;

  function onMove(e: React.PointerEvent<SVGSVGElement>) {
    const rect = e.currentTarget.getBoundingClientRect();
    const px = ((e.clientX - rect.left) / rect.width) * W;
    const i = Math.round(((px - PAD.left) / iw) * (labels.length - 1));
    setHover(Math.max(0, Math.min(labels.length - 1, i)));
  }

  return (
    <div ref={ref} className="relative">
      <svg viewBox={`0 0 ${W} ${H}`} className="h-auto w-full touch-none" onPointerMove={onMove} onPointerLeave={() => setHover(null)} role="img" aria-label={series.map((s) => s.name).join(" and ") + " by month"}>
        {ticks.map((t) => (
          <g key={t}>
            <line x1={PAD.left} x2={W - PAD.right} y1={y(t)} y2={y(t)} stroke={t === 0 ? AXIS : GRID} strokeWidth={1} />
            <text x={PAD.left - 8} y={y(t) + 4} textAnchor="end" fontSize={11} fill={MUTED} className="tabular-nums">{format(t)}</text>
          </g>
        ))}
        {labels.map((l, i) => (iw / labels.length >= 34 || (labels.length - 1 - i) % 2 === 0 ? (
          <text key={l} x={x(i)} y={H - 8} textAnchor="middle" fontSize={11} fill={MUTED}>{l}</text>
        ) : null))}
        {hover !== null && <line x1={x(hover)} x2={x(hover)} y1={PAD.top} y2={PAD.top + ih} stroke={AXIS} strokeWidth={1} />}
        {series.map((s, si) => (
          <g key={s.name}>
            <path d={s.values.map((v, i) => `${i ? "L" : "M"}${x(i)},${y(v)}`).join(" ")} fill="none" stroke={SERIES[si]} strokeWidth={2} strokeLinejoin="round" strokeLinecap="round" />
            {/* end marker with a surface ring */}
            <circle cx={x(s.values.length - 1)} cy={y(s.values[s.values.length - 1])} r={4} fill={SERIES[si]} stroke="#fff" strokeWidth={2} />
            {hover !== null && <circle cx={x(hover)} cy={y(s.values[hover])} r={4} fill={SERIES[si]} stroke="#fff" strokeWidth={2} />}
          </g>
        ))}
      </svg>
      {hover !== null && (
        <Tooltip x={x(hover)} y={((PAD.top + 4) / H) * 100} width={W}>
          <div className="mb-1 font-medium text-slate-900">{labels[hover]}</div>
          {series.map((s, si) => (
            <div key={s.name} className="flex items-center gap-2 whitespace-nowrap">
              <span className="inline-block h-0.5 w-3 rounded" style={{ background: SERIES[si] }} aria-hidden />
              <span className="font-semibold text-slate-900">{format(s.values[hover])}</span>
              <span className="text-slate-500">{s.name}</span>
            </div>
          ))}
        </Tooltip>
      )}
    </div>
  );
}

/** Single-series columns (one per month / day), value on hover and on the tallest column. */
export function ColumnChart({ labels, values, format = String, name }: { labels: string[]; values: number[]; format?: (n: number) => string; name: string }) {
  const [hover, setHover] = useState<number | null>(null);
  const [ref, W] = useWidth();
  const max = Math.max(1, ...values);
  const ticks = niceTicks(max);
  const top = ticks[ticks.length - 1];
  const iw = W - PAD.left - PAD.right;
  const ih = H - PAD.top - PAD.bottom;
  const band = iw / values.length;
  const bw = Math.min(24, band * 0.6);
  const y = (v: number) => PAD.top + ih - (v / top) * ih;
  const peak = values.indexOf(Math.max(...values));
  const id = useId();

  return (
    <div ref={ref} className="relative">
      <svg viewBox={`0 0 ${W} ${H}`} className="h-auto w-full" role="img" aria-label={name}>
        <defs>
          <clipPath id={`${id}-clip`}><rect x={0} y={0} width={W} height={PAD.top + ih} /></clipPath>
        </defs>
        {ticks.map((t) => (
          <g key={t}>
            <line x1={PAD.left} x2={W - PAD.right} y1={y(t)} y2={y(t)} stroke={t === 0 ? AXIS : GRID} strokeWidth={1} />
            <text x={PAD.left - 8} y={y(t) + 4} textAnchor="end" fontSize={11} fill={MUTED} className="tabular-nums">{format(t)}</text>
          </g>
        ))}
        {values.map((v, i) => {
          const cx = PAD.left + band * i + band / 2;
          const h = (v / top) * ih;
          return (
            <g key={labels[i]} onPointerEnter={() => setHover(i)} onPointerLeave={() => setHover(null)} onFocus={() => setHover(i)} onBlur={() => setHover(null)} tabIndex={0} aria-label={`${labels[i]}: ${format(v)}`}>
              <rect x={PAD.left + band * i} y={PAD.top} width={band} height={ih} fill="transparent" />
              {v > 0 && (
                // 4px rounded data end, square at the baseline (extra height hidden by the clip)
                <rect x={cx - bw / 2} y={y(v)} width={bw} height={h + 4} rx={4} fill={SERIES[0]} opacity={hover === null || hover === i ? 1 : 0.55} clipPath={`url(#${id}-clip)`} />
              )}
              {i === peak && v > 0 && <text x={cx} y={y(v) - 6} textAnchor="middle" fontSize={11} fill="#334155" className="tabular-nums">{format(v)}</text>}
              {(band >= 34 || (values.length - 1 - i) % 2 === 0) && <text x={cx} y={H - 8} textAnchor="middle" fontSize={11} fill={MUTED}>{labels[i]}</text>}
            </g>
          );
        })}
      </svg>
      {hover !== null && (
        <Tooltip x={PAD.left + band * hover + band / 2} y={((y(values[hover]) - 8) / H) * 100} width={W}>
          <div className="font-semibold text-slate-900">{format(values[hover])}</div>
          <div className="text-slate-500">{labels[hover]}</div>
        </Tooltip>
      )}
    </div>
  );
}

/** Horizontal bars with the value at the tip (ranked categories, pipeline stages). */
export function BarList({ items, format = String, stacked = false }: { items: { label: string; value: number; href?: string }[]; format?: (n: number) => string; stacked?: boolean }) {
  const max = Math.max(1, ...items.map((i) => i.value));
  if (!items.length) return <p className="text-sm text-slate-500">No data yet.</p>;
  if (stacked) {
    // Label and value above a full-width bar: for narrow cards.
    return (
      <ul className="space-y-3.5">
        {items.map((i) => (
          <li key={i.label} className="text-sm">
            <div className="mb-1 flex justify-between gap-3">
              <span className="text-slate-700">{i.label}</span>
              <span className="whitespace-nowrap tabular-nums text-slate-600">{format(i.value)}</span>
            </div>
            <div className="h-3 rounded-r-[4px] bg-slate-100">
              <div className="h-3 rounded-r-[4px]" style={{ width: `${Math.max(i.value ? 3 : 0, (i.value / max) * 100)}%`, background: SERIES[0] }} />
            </div>
          </li>
        ))}
      </ul>
    );
  }
  return (
    <ul className="space-y-3">
      {items.map((i) => (
        <li key={i.label} className="grid grid-cols-[minmax(0,10rem)_minmax(0,1fr)_auto] items-center gap-3 text-sm" title={`${i.label}: ${format(i.value)}`}>
          <span className="truncate text-slate-700">{i.href ? <a href={i.href} className="hover:text-nis-primary hover:underline">{i.label}</a> : i.label}</span>
          <span className="block h-3">
            <span className="block h-3 rounded-r-[4px]" style={{ width: `${Math.max(i.value ? 3 : 0, (i.value / max) * 100)}%`, background: SERIES[0] }} />
          </span>
          <span className="whitespace-nowrap text-right tabular-nums text-slate-600">{format(i.value)}</span>
        </li>
      ))}
    </ul>
  );
}

/** One 100% bar split into two parts, with a legend and labelled shares. */
export function SplitBar({ title, parts }: { title: string; parts: { label: string; value: number }[] }) {
  const total = parts.reduce((a, p) => a + p.value, 0);
  const pct = (v: number) => (total ? Math.round((v / total) * 100) : 0);
  return (
    <div>
      <div className="mb-1.5 flex justify-between text-xs text-slate-500">
        <span className="font-medium text-slate-700">{title}</span>
        <span className="tabular-nums">{total} total</span>
      </div>
      <div className="flex h-3 gap-[2px] overflow-hidden rounded-[4px] bg-slate-100">
        {total > 0 && parts.map((p, i) => (
          <span key={p.label} title={`${p.label}: ${p.value} (${pct(p.value)}%)`} style={{ width: `${pct(p.value)}%`, background: SERIES[i % 2] }} />
        ))}
      </div>
      <ul className="mt-1.5 flex flex-wrap gap-4 text-xs text-slate-600">
        {parts.map((p, i) => (
          <li key={p.label} className="flex items-center gap-1.5">
            <span className="inline-block h-2.5 w-2.5 rounded-sm" style={{ background: SERIES[i % 2] }} aria-hidden />
            {p.label} <span className="tabular-nums text-slate-900">{p.value}</span> <span className="tabular-nums">({pct(p.value)}%)</span>
          </li>
        ))}
      </ul>
    </div>
  );
}

/** Donut-free meter for a single percentage (e.g. approval rate). */
export function Meter({ value, label }: { value: number | null; label: string }) {
  return (
    <div>
      <div className="flex items-baseline justify-between">
        <span className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</span>
        <span className="text-2xl font-medium text-slate-900">{value === null ? "—" : `${value}%`}</span>
      </div>
      <div className="mt-2 h-2 rounded-full bg-nis-mint">
        <div className="h-2 rounded-full" style={{ width: `${value ?? 0}%`, background: SERIES[0] }} />
      </div>
    </div>
  );
}

export { compact };
