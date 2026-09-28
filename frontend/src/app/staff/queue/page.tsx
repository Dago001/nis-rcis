"use client";

import Link from "next/link";
import { useEffect, useRef, useState, type FormEvent } from "react";
import { PageTitle } from "@/components/PageTitle";
import { StatusBadge } from "@/components/StatusBadge";
import { Alert, Button, Field, Input, Panel, Select, Spinner } from "@/components/ui";
import { api, ApiError } from "@/lib/api-client";
import { useFetch } from "@/lib/use-fetch";

type Ticket = {
  id: number;
  ticket_number: string;
  kind: "APPOINTMENT" | "WALK_IN";
  name: string;
  purpose: string | null;
  status: "WAITING" | "CALLED" | "DONE" | "NO_SHOW";
  desk: string | null;
  application: { id: number; application_number: string; status: string } | null;
  checked_in_at: string;
  wait_minutes: number;
};
type QueueData = {
  center: { id: number; code: string; name: string };
  centers: { id: number; code: string; name: string }[];
  data: Ticket[];
  stats: { waiting: number; called: number; done: number; no_show: number; walk_ins: number };
};

const BADGE: Record<Ticket["status"], string> = { WAITING: "PENDING_APPROVAL", CALLED: "APPROVED_FOR_BIOMETRICS", DONE: "ISSUED", NO_SHOW: "REJECTED" };
const LABEL: Record<Ticket["status"], string> = { WAITING: "Waiting", CALLED: "Called", DONE: "Served", NO_SHOW: "No-show" };

function read(key: string, fallback: string) {
  try {
    return localStorage.getItem(key) ?? fallback;
  } catch {
    return fallback;
  }
}

/** Enrollment-centre queue desk: scan slips, add walk-ins, call the next person. */
export default function QueueDeskPage() {
  const [center, setCenter] = useState<string>("");
  const [desk, setDesk] = useState("Desk 1");
  const { data, reload } = useFetch<QueueData>("staff", `queue${center ? `?enrollment_center_id=${center}` : ""}`);
  const [message, setMessage] = useState<{ tone: "success" | "warning" | "danger"; text: string } | null>(null);
  const [busy, setBusy] = useState(false);
  const scan = useRef<HTMLInputElement>(null);

  // Remember this desk's name and centre on this computer.
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- one-time read of a per-computer preference
    setDesk(read("nis_queue_desk", "Desk 1"));
    setCenter(read("nis_queue_center", ""));
  }, []);
  useEffect(() => {
    const timer = setInterval(reload, 10000);
    return () => clearInterval(timer);
  }, [reload]);

  const centerId = data?.center.id;
  const body = (extra: object) => ({ ...extra, enrollment_center_id: centerId });

  async function run(fn: () => Promise<string | { tone: "success" | "warning"; text: string }>) {
    setBusy(true);
    try {
      const result = await fn();
      setMessage(typeof result === "string" ? { tone: "success", text: result } : result);
      reload();
    } catch (e) {
      setMessage({ tone: "danger", text: e instanceof ApiError ? Object.values(e.fieldErrors())[0] ?? e.message : String(e) });
    } finally {
      setBusy(false);
      scan.current?.focus();
    }
  }

  function checkIn(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const code = String(new FormData(form).get("code") ?? "").trim();
    if (!code) return;
    void run(async () => {
      const r = await api<{ data: Ticket; warning: string | null; existing: boolean }>("staff", "queue/check-in", { method: "POST", json: body({ code }) });
      form.reset();
      const text = `${r.existing ? "Already checked in" : "Checked in"}: ticket ${r.data.ticket_number} — ${r.data.name} (${r.data.purpose}).${r.warning ? ` ${r.warning}` : ""}`;
      return r.warning ? { tone: "warning", text } : text;
    });
  }

  function walkIn(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const f = new FormData(form);
    void run(async () => {
      const r = await api<{ data: Ticket }>("staff", "queue/walk-in", { method: "POST", json: body({ name: f.get("name"), purpose: f.get("purpose") }) });
      form.reset();
      return `Walk-in ticket ${r.data.ticket_number} issued to ${r.data.name}.`;
    });
  }

  const call = (ticketId?: number) =>
    run(async () => {
      const r = await api<{ data: Ticket }>("staff", "queue/call", { method: "POST", json: body({ desk, ticket_id: ticketId }) });
      return `Now serving ${r.data.ticket_number} at ${desk}: ${r.data.name}.`;
    });
  const finish = (t: Ticket, status: "DONE" | "NO_SHOW") =>
    run(async () => {
      await api("staff", `queue/${t.id}/finish`, { method: "POST", json: { status } });
      return `Ticket ${t.ticket_number} ${status === "DONE" ? "served" : "marked as no-show"}.`;
    });

  if (!data) return <Spinner />;
  const active = data.data.filter((t) => t.status === "WAITING" || t.status === "CALLED");
  const closed = data.data.filter((t) => t.status === "DONE" || t.status === "NO_SHOW");

  return (
    <div className="space-y-6">
      <PageTitle
        title="Centre queue"
        subtitle={`${data.center.name} · today: ${data.stats.waiting} waiting, ${data.stats.called} being served, ${data.stats.done} served, ${data.stats.no_show} no-shows, ${data.stats.walk_ins} walk-ins`}
        actions={
          <a href={`/queue-display/${data.center.id}`} target="_blank" rel="noopener" className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium">
            Open “now serving” screen ↗
          </a>
        }
      />
      {message && <Alert tone={message.tone === "warning" ? "warning" : message.tone}>{message.text}</Alert>}

      <div className="grid gap-6 lg:grid-cols-3">
        <Panel title="Check in (scan the slip)">
          <form onSubmit={checkIn} className="space-y-3">
            <Field label="Barcode or QR code on the slip" hint="Scan with the barcode reader, or type the application or reference number.">
              <Input ref={scan} name="code" autoFocus autoComplete="off" />
            </Field>
            <Button type="submit" disabled={busy}>Check in</Button>
          </form>
        </Panel>
        <Panel title="Walk-in">
          <form onSubmit={walkIn} className="space-y-3">
            <Field label="Name" required><Input name="name" required maxLength={150} /></Field>
            <Field label="Purpose" required>
              <Select name="purpose" required defaultValue="Enquiry">
                <option>Enquiry</option>
                <option>Assisted application</option>
                <option>Card collection</option>
                <option>Document submission</option>
                <option>Complaint</option>
              </Select>
            </Field>
            <Button type="submit" variant="secondary" disabled={busy}>Issue ticket</Button>
          </form>
        </Panel>
        <Panel title="This desk">
          <div className="space-y-3">
            {data.centers.length > 1 && (
              <Field label="Centre">
                <Select value={String(data.center.id)} onChange={(e) => { setCenter(e.target.value); try { localStorage.setItem("nis_queue_center", e.target.value); } catch {} }}>
                  {data.centers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                </Select>
              </Field>
            )}
            <Field label="Desk name">
              <Input value={desk} maxLength={20} onChange={(e) => { setDesk(e.target.value); try { localStorage.setItem("nis_queue_desk", e.target.value); } catch {} }} />
            </Field>
            <Button onClick={() => call()} disabled={busy || data.stats.waiting === 0 || !desk.trim()} className="w-full">Call next person</Button>
          </div>
        </Panel>
      </div>

      <Panel title="In the queue">
        {active.length === 0 ? <p className="text-sm text-slate-600">Nobody is waiting.</p> : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="text-left text-xs uppercase tracking-wide text-slate-500">
                <tr><th className="py-2">Ticket</th><th>Name</th><th>Purpose</th><th>Waiting</th><th>Status</th><th /></tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {active.map((t) => (
                  <tr key={t.id}>
                    <td className="py-2 font-mono text-base font-semibold">{t.ticket_number}</td>
                    <td>{t.name}{t.application && <div className="text-xs"><Link href={`/staff/applications/${t.application.id}`} className="text-nis-primary hover:underline">{t.application.application_number}</Link></div>}</td>
                    <td>{t.purpose}</td>
                    <td className="tabular-nums">{t.wait_minutes} min</td>
                    <td><StatusBadge status={BADGE[t.status]} label={t.status === "CALLED" ? `Called · ${t.desk}` : LABEL[t.status]} /></td>
                    <td className="space-x-1 whitespace-nowrap text-right">
                      <Button variant="secondary" className="!px-3 !py-1.5" disabled={busy} onClick={() => call(t.id)}>{t.status === "CALLED" ? "Recall" : "Call"}</Button>
                      <Button className="!px-3 !py-1.5" disabled={busy} onClick={() => finish(t, "DONE")}>Served</Button>
                      <Button variant="ghost" className="!px-3 !py-1.5" disabled={busy} onClick={() => finish(t, "NO_SHOW")}>No-show</Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>

      {closed.length > 0 && (
        <Panel title="Finished today">
          <ul className="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2 lg:grid-cols-3">
            {closed.map((t) => <li key={t.id}><span className="font-mono font-semibold">{t.ticket_number}</span> {t.name} · <span className="text-slate-500">{LABEL[t.status]}</span></li>)}
          </ul>
        </Panel>
      )}
    </div>
  );
}
