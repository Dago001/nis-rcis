"use client";

import Image from "next/image";
import { useParams } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { api } from "@/lib/api-client";

type Display = { center: string; serving: { ticket_number: string; desk: string; called_at: string }[]; waiting: number; next: string[]; time: string };

/**
 * "Now serving" screen for the enrollment centre waiting room (a TV or
 * monitor in full-screen mode). Shows ticket numbers and desks only.
 */
export default function QueueDisplayPage() {
  const { center } = useParams<{ center: string }>();
  const [data, setData] = useState<Display | null>(null);
  const [error, setError] = useState(false);
  const [flash, setFlash] = useState<string | null>(null);
  const last = useRef<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    const load = () =>
      api<Display>("public", `enrollment-centers/${center}/queue`)
        .then((d) => {
          if (cancelled) return;
          setError(false);
          setData(d);
          // Highlight (and chime for) a newly called ticket.
          const newest = d.serving[0] ? `${d.serving[0].ticket_number}@${d.serving[0].called_at}` : null;
          if (newest && last.current && newest !== last.current) {
            setFlash(d.serving[0].ticket_number);
            chime();
            setTimeout(() => setFlash(null), 6000);
          }
          last.current = newest;
        })
        .catch(() => !cancelled && setError(true));
    load();
    const timer = setInterval(load, 4000);
    return () => {
      cancelled = true;
      clearInterval(timer);
    };
  }, [center]);

  const current = data?.serving[0];

  return (
    <main id="main" className="flex min-h-screen flex-col bg-nis-primary-dark text-white">
      <header className="flex items-center justify-between gap-4 bg-black/20 px-8 py-4">
        <div className="flex items-center gap-4">
          <Image src="/images/nis-logo.png" alt="" width={56} height={56} />
          <div>
            <div className="text-xl font-semibold">NIGERIA IMMIGRATION SERVICE</div>
            <div className="text-white/80">{data?.center ?? "Enrollment centre"} · Residence cards</div>
          </div>
        </div>
        <Clock />
      </header>

      {error && <p className="bg-red-700 px-8 py-2 text-center">Connection lost — retrying…</p>}

      <div className="grid flex-1 gap-8 p-8 lg:grid-cols-[1.4fr_1fr]">
        <section aria-live="polite" className={`flex flex-col items-center justify-center rounded-3xl bg-white/10 p-8 text-center transition ${flash ? "ring-8 ring-nis-orange" : ""}`}>
          <div className="text-2xl uppercase tracking-[0.3em] text-white/80">Now serving</div>
          {current ? (
            <>
              <div className="mt-4 font-mono text-[9rem] font-bold leading-none">{current.ticket_number}</div>
              <div className="mt-6 text-5xl font-semibold text-nis-orange">{current.desk}</div>
            </>
          ) : (
            <div className="mt-6 text-4xl text-white/80">Please wait to be called</div>
          )}
        </section>

        <section className="flex flex-col gap-6">
          <div className="rounded-3xl bg-white/10 p-6">
            <h2 className="text-xl uppercase tracking-widest text-white/80">Recently called</h2>
            <ul className="mt-4 space-y-3">
              {(data?.serving.slice(1, 6) ?? []).map((s) => (
                <li key={`${s.ticket_number}-${s.called_at}`} className="flex justify-between font-mono text-3xl">
                  <span>{s.ticket_number}</span><span className="text-nis-orange">{s.desk}</span>
                </li>
              ))}
            </ul>
          </div>
          <div className="rounded-3xl bg-white/10 p-6">
            <h2 className="text-xl uppercase tracking-widest text-white/80">Next</h2>
            <p className="mt-3 font-mono text-3xl">{data?.next.join("  ") || "—"}</p>
            <p className="mt-4 text-xl text-white/80">{data?.waiting ?? 0} waiting</p>
          </div>
        </section>
      </div>
      <footer className="px-8 pb-4 text-center text-white/70">Have your appointment slip and original passport ready. Listen for your ticket number.</footer>
    </main>
  );
}

function Clock() {
  const [now, setNow] = useState<Date | null>(null);
  useEffect(() => {
    const tick = () => setNow(new Date());
    tick();
    const timer = setInterval(tick, 1000);
    return () => clearInterval(timer);
  }, []);
  return <div className="font-mono text-3xl tabular-nums">{now?.toLocaleTimeString("en-GB", { hour: "2-digit", minute: "2-digit" })}</div>;
}

/** Short two-tone chime (Web Audio; silently skipped where the browser blocks sound). */
function chime() {
  try {
    const ctx = new AudioContext();
    [880, 660].forEach((freq, i) => {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.frequency.value = freq;
      gain.gain.setValueAtTime(0.2, ctx.currentTime + i * 0.35);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + i * 0.35 + 0.3);
      osc.connect(gain).connect(ctx.destination);
      osc.start(ctx.currentTime + i * 0.35);
      osc.stop(ctx.currentTime + i * 0.35 + 0.32);
    });
  } catch {
    // no sound available
  }
}
