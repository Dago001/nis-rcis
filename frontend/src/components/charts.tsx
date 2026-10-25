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

/**
 * Vertical two-bar chart for category comparison (e.g. New vs Renewal).
 * Uses portal primary green for first bar and lighter shade for second bar.
 */
export function VerticalTwoBarChart({
  items,
  format = String,
}: {
  items: [
    { label: string; value: number; color?: string },
    { label: string; value: number; color?: string }
  ];
  format?: (n: number) => string;
}) {
  const [hover, setHover] = useState<number | null>(null);
  const [ref, W] = useWidth(400);
  const max = Math.max(1, items[0].value, items[1].value);
  const ticks = niceTicks(max);
  const top = ticks[ticks.length - 1];
  const chartHeight = 220;
  const pad = { top: 20, right: 24, bottom: 36, left: 48 };
  const iw = W - pad.left - pad.right;
  const ih = chartHeight - pad.top - pad.bottom;
  const bw = Math.min(52, Math.max(36, iw * 0.24));
  const cx0 = pad.left + iw * 0.3;
  const cx1 = pad.left + iw * 0.7;
  const centers = [cx0, cx1];
  const y = (v: number) => pad.top + ih - (top > 0 ? (v / top) * ih : 0);
  const colors = [items[0].color ?? "#2b892b", items[1].color ?? "#58b258"];
  const id = useId();

  return (
    <div ref={ref} className="relative flex flex-col items-center justify-center">
      <svg viewBox={`0 0 ${W} ${chartHeight}`} className="h-auto w-full select-none" role="img" aria-label="Applications by Type">
        <defs>
          <clipPath id={`${id}-clip`}>
            <rect x={0} y={0} width={W} height={pad.top + ih} />
          </clipPath>
        </defs>

        {/* Numeric Y-Axis Gridlines & Ticks */}
        {ticks.map((t) => (
          <g key={t}>
            <line x1={pad.left} x2={W - pad.right} y1={y(t)} y2={y(t)} stroke={t === 0 ? AXIS : GRID} strokeWidth={1} />
            <text x={pad.left - 10} y={y(t) + 4} textAnchor="end" fontSize={11} fill={MUTED} className="tabular-nums">
              {format(t)}
            </text>
          </g>
        ))}

        {/* Vertical Bars */}
        {items.map((item, i) => {
          const cx = centers[i];
          const val = item.value;
          const h = top > 0 ? (val / top) * ih : 0;
          const isHovered = hover === null || hover === i;
          return (
            <g
              key={item.label}
              onPointerEnter={() => setHover(i)}
              onPointerLeave={() => setHover(null)}
              onFocus={() => setHover(i)}
              onBlur={() => setHover(null)}
              tabIndex={0}
              className="cursor-pointer outline-none"
              aria-label={`${item.label}: ${format(val)}`}
            >
              {/* Invisible touch/hover target */}
              <rect x={cx - bw} y={pad.top} width={bw * 2} height={ih} fill="transparent" />

              {/* Bar rectangle with rounded top */}
              {val > 0 ? (
                <rect
                  x={cx - bw / 2}
                  y={y(val)}
                  width={bw}
                  height={h + 4}
                  rx={6}
                  fill={colors[i]}
                  opacity={isHovered ? 1 : 0.6}
                  clipPath={`url(#${id}-clip)`}
                  className="transition-opacity duration-150"
                />
              ) : (
                /* Subtle baseline indicator for 0 records */
                <rect
                  x={cx - bw / 2}
                  y={pad.top + ih - 2}
                  width={bw}
                  height={2}
                  rx={1}
                  fill={colors[i]}
                  opacity={0.4}
                />
              )}

              {/* Exact Value above bar */}
              <text
                x={cx}
                y={val > 0 ? Math.max(pad.top + 14, y(val) - 6) : pad.top + ih - 6}
                textAnchor="middle"
                fontSize={12}
                fontWeight={600}
                fill="#1e293b"
                className="tabular-nums"
              >
                {format(val)}
              </text>

              {/* Label below bar */}
              <text x={cx} y={chartHeight - 12} textAnchor="middle" fontSize={12} fontWeight={500} fill="#475569">
                {item.label}
              </text>
            </g>
          );
        })}
      </svg>

      {hover !== null && (
        <Tooltip x={centers[hover]} y={((Math.max(10, y(items[hover].value) - 10)) / chartHeight) * 100} width={W}>
          <div className="font-semibold text-slate-900">{format(items[hover].value)} applications</div>
          <div className="text-slate-500">{items[hover].label}</div>
        </Tooltip>
      )}
    </div>
  );
}

/**
 * Doughnut chart with empty centre for distributions (e.g. Applications by Sex).
 * Segments represent Male and Female, with center text "Sex Distribution" and legend.
 */
export function DoughnutChart({
  items,
  centerTitle = "Gender",
  centerSubtitle = "Distribution",
  format = String,
}: {
  items: [
    { label: string; value: number; color?: string },
    { label: string; value: number; color?: string }
  ];
  centerTitle?: string;
  centerSubtitle?: string;
  format?: (n: number) => string;
}) {
  const [hover, setHover] = useState<number | null>(null);
  const total = items.reduce((sum, it) => sum + it.value, 0);
  const colors = items.map((it, idx) => {
    const l = (it.label || "").toLowerCase();
    if (l.includes("female") || l.includes("féminin") || l.includes("feminin") || l === "f") return "#8E44AD";
    if (l.includes("male") || l.includes("masculin") || l === "m") return "#2E7D32";
    return it.color ?? (idx === 0 ? "#2E7D32" : "#8E44AD");
  });

  const size = 200;
  const cx = size / 2;
  const cy = size / 2;
  const r = 74;
  const strokeWidth = 24;
  const circumference = 2 * Math.PI * r;

  // Compute strokeDasharray and offsets
  const pct0 = total > 0 ? items[0].value / total : 0;
  const pct1 = total > 0 ? items[1].value / total : 0;
  const len0 = circumference * pct0;
  const len1 = circumference * pct1;
  const offset0 = 0;
  const offset1 = -len0;

  return (
    <div className="flex flex-col items-center justify-center">
      <div className="relative flex items-center justify-center" style={{ width: size, height: size }}>
        <svg viewBox={`0 0 ${size} ${size}`} className="h-full w-full -rotate-90 select-none" role="img" aria-label="Applications by Gender">
          {/* Background track if 0 records */}
          <circle cx={cx} cy={cy} r={r} fill="none" stroke="#f1f5f9" strokeWidth={strokeWidth} />

          {total > 0 ? (
            <>
              {/* Segment 0 (Male) */}
              <circle
                cx={cx}
                cy={cy}
                r={r}
                fill="none"
                stroke={colors[0]}
                strokeWidth={strokeWidth}
                strokeDasharray={`${len0} ${circumference - len0}`}
                strokeDashoffset={offset0}
                opacity={hover === null || hover === 0 ? 1 : 0.6}
                className="cursor-pointer transition-opacity duration-150"
                onPointerEnter={() => setHover(0)}
                onPointerLeave={() => setHover(null)}
              />
              {/* Segment 1 (Female) */}
              <circle
                cx={cx}
                cy={cy}
                r={r}
                fill="none"
                stroke={colors[1]}
                strokeWidth={strokeWidth}
                strokeDasharray={`${len1} ${circumference - len1}`}
                strokeDashoffset={offset1}
                opacity={hover === null || hover === 1 ? 1 : 0.6}
                className="cursor-pointer transition-opacity duration-150"
                onPointerEnter={() => setHover(1)}
                onPointerLeave={() => setHover(null)}
              />
            </>
          ) : (
            <circle cx={cx} cy={cy} r={r} fill="none" stroke="#e2e8f0" strokeWidth={strokeWidth} strokeDasharray="4 4" />
          )}
        </svg>

        {/* Centre Content */}
        <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
          <span className="text-xs font-semibold uppercase tracking-wider text-slate-800 leading-tight">
            {centerTitle}
          </span>
          <span className="text-[11px] font-medium text-slate-500 leading-tight">
            {centerSubtitle}
          </span>
          {hover !== null && (
            <span className="mt-1 text-xs font-bold text-slate-900 tabular-nums">
              {items[hover].label}: {format(items[hover].value)} ({total > 0 ? Math.round((items[hover].value / total) * 100) : 0}%)
            </span>
          )}
        </div>
      </div>

      {/* Legend below the doughnut chart */}
      <ul className="mt-4 flex flex-wrap items-center justify-center gap-6 text-xs text-slate-700">
        {items.map((item, i) => {
          const pct = total > 0 ? Math.round((item.value / total) * 100) : 0;
          return (
            <li
              key={item.label}
              className={`flex items-center gap-2 cursor-pointer transition-opacity ${
                hover === null || hover === i ? "opacity-100" : "opacity-50"
              }`}
              onPointerEnter={() => setHover(i)}
              onPointerLeave={() => setHover(null)}
            >
              <span className="inline-block h-3 w-3 rounded-full shrink-0" style={{ background: colors[i] }} aria-hidden="true" />
              <span className="font-medium text-slate-800">{item.label}:</span>
              <span className="tabular-nums font-semibold text-slate-900">{format(item.value)}</span>
              <span className="tabular-nums text-slate-500">({pct}%)</span>
            </li>
          );
        })}
      </ul>
    </div>
  );
}

/** Horizontal bar chart for Nigerian States with hover tooltips and scrollable capacity. */
export function StateHorizontalBarChart({
  items,
  format = String,
  notAssignedLabel = "Not Yet Assigned",
}: {
  items: { state: string; total: number }[];
  format?: (n: number) => string;
  notAssignedLabel?: string;
}) {
  const [hoveredState, setHoveredState] = useState<string | null>(null);
  const max = Math.max(1, ...items.map((i) => i.total));

  if (!items.length) {
    return <p className="py-8 text-center text-sm text-slate-500">No state application records available yet.</p>;
  }

  return (
    <div className="space-y-2.5">
      {items.map((item) => {
        const isNotAssigned = item.state.toLowerCase() === "not yet assigned";
        const label = isNotAssigned ? notAssignedLabel : item.state;
        const widthPct = Math.max(item.total ? 3 : 0, (item.total / max) * 100);
        const isHovered = hoveredState === item.state;

        return (
          <div
            key={item.state}
            className="group relative flex items-center gap-3 text-xs sm:text-sm"
            onMouseEnter={() => setHoveredState(item.state)}
            onMouseLeave={() => setHoveredState(null)}
          >
            <span
              className={`w-28 sm:w-36 shrink-0 truncate font-medium ${
                isNotAssigned ? "text-slate-500 italic" : "text-slate-700"
              }`}
              title={label}
            >
              {label}
            </span>

            <div className="relative h-4 flex-1 rounded bg-slate-100 overflow-hidden">
              <div
                className={`h-full rounded-r transition-all duration-300 ${
                  isNotAssigned ? "bg-slate-400" : isHovered ? "bg-nis-primary-dark" : "bg-nis-primary"
                }`}
                style={{ width: `${widthPct}%` }}
              />
            </div>

            <span className="w-12 shrink-0 text-right font-medium tabular-nums text-slate-700">
              {format(item.total)}
            </span>

            {isHovered && (
              <div className="pointer-events-none absolute -top-8 left-1/2 z-20 -translate-x-1/2 rounded bg-slate-900 px-2.5 py-1 text-xs font-medium text-white shadow-md whitespace-nowrap">
                {label}: {format(item.total)} {item.total === 1 ? "application" : "applications"}
              </div>
            )}
          </div>
        );
      })}
    </div>
  );
}

/** Top 5 States ranking chart with rank badges (1-5), progress bars, and tooltips. */
export function Top5StatesChart({
  items,
  format = String,
}: {
  items: { rank: number; state: string; total: number }[];
  format?: (n: number) => string;
}) {
  const [hoveredRank, setHoveredRank] = useState<number | null>(null);
  const max = Math.max(1, ...items.map((i) => i.total));

  if (!items.length) {
    return <p className="py-8 text-center text-sm text-slate-500">No state ranking data available yet.</p>;
  }

  const badgeColors: Record<number, { bg: string; text: string; bar: string }> = {
    1: { bg: "bg-amber-100 text-amber-800 border-amber-300", text: "text-amber-900", bar: "bg-[#2b892b]" },
    2: { bg: "bg-slate-100 text-slate-700 border-slate-300", text: "text-slate-800", bar: "bg-[#349734]" },
    3: { bg: "bg-orange-100 text-orange-800 border-orange-300", text: "text-orange-900", bar: "bg-[#3da53d]" },
    4: { bg: "bg-slate-50 text-slate-600 border-slate-200", text: "text-slate-700", bar: "bg-[#48b348]" },
    5: { bg: "bg-slate-50 text-slate-600 border-slate-200", text: "text-slate-700", bar: "bg-[#54c054]" },
  };

  return (
    <div className="space-y-4 py-1">
      {items.map((item) => {
        const badge = badgeColors[item.rank] || badgeColors[5];
        const widthPct = Math.max(item.total ? 4 : 0, (item.total / max) * 100);
        const isHovered = hoveredRank === item.rank;

        return (
          <div
            key={item.rank}
            className="group relative"
            onMouseEnter={() => setHoveredRank(item.rank)}
            onMouseLeave={() => setHoveredRank(null)}
          >
            <div className="mb-1.5 flex items-center justify-between gap-2 text-xs sm:text-sm">
              <div className="flex items-center gap-2.5 min-w-0">
                <span
                  className={`inline-flex h-5 w-5 sm:h-6 sm:w-6 shrink-0 items-center justify-center rounded-full border text-[11px] sm:text-xs font-bold ${badge.bg}`}
                >
                  {item.rank}
                </span>
                <span className="truncate font-medium text-slate-800 text-sm">
                  {item.state}
                </span>
              </div>
              <span className="shrink-0 font-semibold tabular-nums text-slate-900 text-sm">
                {format(item.total)}
              </span>
            </div>

            <div className="relative h-3 w-full rounded-full bg-slate-100 overflow-hidden">
              <div
                className={`h-full rounded-full transition-all duration-300 ${badge.bar} ${
                  isHovered ? "brightness-110" : ""
                }`}
                style={{ width: `${widthPct}%` }}
              />
            </div>

            {isHovered && (
              <div className="pointer-events-none absolute -top-7 right-0 z-20 rounded bg-slate-900 px-2 py-0.5 text-xs font-medium text-white shadow-md whitespace-nowrap">
                Rank #{item.rank}: {item.state} ({format(item.total)} apps)
              </div>
            )}
          </div>
        );
      })}
    </div>
  );
}

/** Structured breakdown for Application Processing Overview */
export function ProcessingOverviewChart({
  data,
  t,
}: {
  data: {
    cards_produced: number;
    cards_issued: number;
    new_cards_issued: number;
    renewal_cards_issued: number;
    queried: number;
    rejected: number;
  };
  t: (key: string) => string;
}) {
  const max = Math.max(
    1,
    data.cards_produced,
    data.cards_issued,
    data.queried,
    data.rejected
  );

  const getWidth = (val: number) => Math.max(val ? 3 : 0, (val / max) * 100);

  return (
    <div className="space-y-4 py-1 text-xs sm:text-sm">
      {/* 1. Total Cards Produced */}
      <div>
        <div className="mb-1 flex items-center justify-between text-slate-700">
          <span className="font-medium text-slate-800">{t("Total Cards Produced")}</span>
          <span className="font-semibold tabular-nums text-slate-900">{data.cards_produced}</span>
        </div>
        <div className="h-3 w-full rounded-full bg-slate-100 overflow-hidden">
          <div className="h-full rounded-full bg-emerald-600 transition-all" style={{ width: `${getWidth(data.cards_produced)}%` }} />
        </div>
      </div>

      {/* 2. Total Cards Issued & Breakdown */}
      <div className="rounded-xl border border-slate-200/80 bg-slate-50/70 p-3">
        <div className="mb-1 flex items-center justify-between text-slate-700">
          <span className="font-medium text-slate-800">{t("Total Cards Issued")}</span>
          <span className="font-semibold tabular-nums text-slate-900">{data.cards_issued}</span>
        </div>
        <div className="h-3 w-full rounded-full bg-slate-200 overflow-hidden">
          <div className="h-full rounded-full bg-nis-primary transition-all" style={{ width: `${getWidth(data.cards_issued)}%` }} />
        </div>

        {/* Breakdown sub-bars */}
        <div className="mt-3 pl-3 space-y-2 border-l-2 border-slate-300">
          <div>
            <div className="mb-0.5 flex items-center justify-between text-xs text-slate-600">
              <span className="flex items-center gap-1">
                <span className="text-slate-400">↳</span> {t("New Cards Issued")}
              </span>
              <span className="font-medium tabular-nums text-slate-800">{data.new_cards_issued}</span>
            </div>
            <div className="h-2 w-full rounded-full bg-slate-200/80 overflow-hidden">
              <div
                className="h-full rounded-full bg-blue-500 transition-all"
                style={{ width: `${getWidth(data.new_cards_issued)}%` }}
              />
            </div>
          </div>

          <div>
            <div className="mb-0.5 flex items-center justify-between text-xs text-slate-600">
              <span className="flex items-center gap-1">
                <span className="text-slate-400">↳</span> {t("Renewal Cards Issued")}
              </span>
              <span className="font-medium tabular-nums text-slate-800">{data.renewal_cards_issued}</span>
            </div>
            <div className="h-2 w-full rounded-full bg-slate-200/80 overflow-hidden">
              <div
                className="h-full rounded-full bg-indigo-500 transition-all"
                style={{ width: `${getWidth(data.renewal_cards_issued)}%` }}
              />
            </div>
          </div>
        </div>
      </div>

      {/* 3. Queried Applications */}
      <div>
        <div className="mb-1 flex items-center justify-between text-slate-700">
          <span className="font-medium text-amber-800">{t("Queried Applications")}</span>
          <span className="font-semibold tabular-nums text-amber-900">{data.queried}</span>
        </div>
        <div className="h-3 w-full rounded-full bg-slate-100 overflow-hidden">
          <div className="h-full rounded-full bg-amber-500 transition-all" style={{ width: `${getWidth(data.queried)}%` }} />
        </div>
      </div>

      {/* 4. Rejected Applications */}
      <div>
        <div className="mb-1 flex items-center justify-between text-slate-700">
          <span className="font-medium text-rose-800">{t("Rejected Applications")}</span>
          <span className="font-semibold tabular-nums text-rose-900">{data.rejected}</span>
        </div>
        <div className="h-3 w-full rounded-full bg-slate-100 overflow-hidden">
          <div className="h-full rounded-full bg-rose-500 transition-all" style={{ width: `${getWidth(data.rejected)}%` }} />
        </div>
      </div>
    </div>
  );
}

export { compact };
