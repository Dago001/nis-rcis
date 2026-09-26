"use client";

import { useCallback, useEffect, useRef, useState, type FormEvent, type ReactNode } from "react";
import { PageTitle } from "@/components/PageTitle";
import { useStaff } from "@/components/StaffShell";
import { Alert, Button, Field, Input, Panel, Select, Spinner } from "@/components/ui";
import { api, ApiError } from "@/lib/api-client";
import { dateTime } from "@/lib/format";
import type { StaffUser } from "@/lib/types";

const ROLES = [["SuperAdmin", "Super Administrator"], ["ApprovingOfficer", "Approving Officer"], ["IssuingOfficer", "Issuing Officer"], ["Inspector", "Inspector"], ["Auditor", "Auditor"]];

export default function UsersPage() {
  const me = useStaff();
  const [users, setUsers] = useState<StaffUser[] | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [secret, setSecret] = useState<{ who: string; password: string } | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [query, setQuery] = useState("");
  const [roleFilter, setRoleFilter] = useState("");
  const [showForm, setShowForm] = useState(false);

  const load = useCallback(() => api<{ data: StaffUser[] }>("staff", "users").then((r) => setUsers(r.data)), []);
  useEffect(() => {
    void load();
  }, [load]);

  async function create(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    setErrors({});
    try {
      const r = await api<{ data: StaffUser; temporary_password: string }>("staff", "users", { method: "POST", json: Object.fromEntries(new FormData(form)) });
      setSecret({ who: r.data.fullname, password: r.temporary_password });
      form.reset();
      setShowForm(false);
      await load();
    } catch (e) {
      if (e instanceof ApiError) setErrors(e.fieldErrors());
    }
  }

  async function toggle(user: StaffUser) {
    if (user.is_active && !confirm(`Deactivate ${user.fullname}? They will be signed out and cannot sign in until the account is activated again.`)) return;
    await api("staff", `users/${user.id}`, { method: "PATCH", json: { is_active: !user.is_active } });
    await load();
  }

  async function reset(user: StaffUser) {
    if (!confirm(`Issue a new temporary password for ${user.fullname}? Their current sessions will be signed out.`)) return;
    const r = await api<{ temporary_password: string }>("staff", `users/${user.id}/reset-password`, { method: "POST" });
    setSecret({ who: user.fullname, password: r.temporary_password });
  }

  async function resetTwoFactor(user: StaffUser) {
    if (!confirm(`Reset the authenticator app for ${user.fullname}? They will be signed out and must set it up again at their next sign-in.`)) return;
    const r = await api<{ message: string }>("staff", `users/${user.id}/reset-two-factor`, { method: "POST" });
    setNotice(`${user.fullname}: ${r.message}`);
    await load();
  }

  async function signOut(user: StaffUser) {
    if (!confirm(`Sign ${user.fullname} out of every session now?`)) return;
    const r = await api<{ message: string }>("staff", `users/${user.id}/revoke-sessions`, { method: "POST" });
    setNotice(`${user.fullname}: ${r.message}`);
  }

  async function changeRole(user: StaffUser, role: string) {
    if (role === user.role) return;
    const label = roleLabel(role);
    if (!confirm(`Change ${user.fullname}'s role to ${label}? They will be signed out so the new permissions apply immediately.`)) {
      await load();
      return;
    }
    await api("staff", `users/${user.id}`, { method: "PATCH", json: { role } });
    setNotice(`${user.fullname} is now ${label}.`);
    await load();
  }


  const q = query.trim().toLowerCase();
  const shown = (users ?? []).filter(
    (u) =>
      (!roleFilter || u.role === roleFilter) &&
      (!q || [u.fullname, u.username, u.service_number, u.email, u.command].some((v) => v?.toLowerCase().includes(q))),
  );
  const activeCount = (users ?? []).filter((u) => u.is_active).length;

  return (
    <div className="space-y-6">
      <PageTitle title="Staff accounts" subtitle="Create officers, change or revoke roles, reset passwords and authenticator apps, and sign officers out." />
      {notice && <Alert tone="success">{notice}</Alert>}
      {secret && (
        <Alert tone="warning">
          Temporary password for <strong>{secret.who}</strong>: <code className="rounded bg-white px-2 py-0.5 font-mono">{secret.password}</code>
          <br />It is shown only once. Give it to the officer in person; they must change it at first sign-in.
        </Alert>
      )}
      {showForm && (
        <Panel title="Add officer" actions={<Button variant="ghost" className="!px-3 !py-1.5" onClick={() => setShowForm(false)}>Close</Button>}>
          <form onSubmit={create} className="grid gap-3 sm:grid-cols-3">
            <Field label="Full name" error={errors.fullname}><Input name="fullname" required /></Field>
            <Field label="Username" error={errors.username}><Input name="username" required /></Field>
            <Field label="Service number" error={errors.service_number}><Input name="service_number" required /></Field>
            <Field label="E-mail" error={errors.email}><Input name="email" type="email" required /></Field>
            <Field label="Role" error={errors.role}><Select name="role">{ROLES.map(([v, l]) => <option key={v} value={v}>{l}</option>)}</Select></Field>
            <Field label="Command" error={errors.command}><Input name="command" defaultValue="National Processing Center" required /></Field>
            <div className="flex gap-3 sm:col-span-3">
              <Button type="submit">Create account</Button>
              <Button type="button" variant="secondary" onClick={() => setShowForm(false)}>Cancel</Button>
            </div>
          </form>
        </Panel>
      )}

      <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <header className="flex flex-wrap items-center gap-3 border-b border-slate-200 px-5 py-4">
          <div className="mr-auto">
            <h2 className="text-[15px] font-semibold text-slate-900">Officers</h2>
            {users && <p className="text-xs text-slate-500">{users.length} accounts · {activeCount} active</p>}
          </div>
          <Input type="search" placeholder="Search officers…" aria-label="Search officers" value={query} onChange={(e) => setQuery(e.target.value)} className="!w-full !py-2 sm:!w-72" />
          <Select aria-label="Filter by role" value={roleFilter} onChange={(e) => setRoleFilter(e.target.value)} className="!w-auto !py-2">
            <option value="">All roles</option>
            {ROLES.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
          </Select>
          {!showForm && <Button className="!py-2" onClick={() => setShowForm(true)}>+ Add officer</Button>}
        </header>

        {!users ? <div className="px-5"><Spinner /></div> : shown.length === 0 ? (
          <p className="px-5 py-10 text-center text-sm text-slate-500">No officers match your search.</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[760px] text-sm">
              <thead className="text-left">
                <tr className="[&>th]:whitespace-nowrap [&>th]:px-4 [&>th]:py-3 [&>th:first-child]:pl-5 [&>th:last-child]:pr-5">
                  <th>Officer</th><th>Role</th><th>Command</th><th>Access</th><th className="text-right"><span className="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
                {users && shown.map((u) => (
                  <tr key={u.id} className="align-middle [&>td]:px-4 [&>td]:py-3.5 [&>td:first-child]:pl-5 [&>td:last-child]:pr-5">
                    <td>
                      <div className="flex items-center gap-3">
                        <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold ${u.is_active ? "bg-nis-mint text-nis-primary-dark" : "bg-slate-100 text-slate-400"}`}>{initials(u.fullname)}</span>
                        <div className="min-w-0">
                          <div className={`flex items-center gap-2 font-medium ${u.is_active ? "text-slate-900" : "text-slate-400"}`}>
                            <span className="truncate">{u.fullname}</span>
                            {u.id === me.id && <Badge tone="slate">You</Badge>}
                            {!u.is_active && <Badge tone="red">Deactivated</Badge>}
                          </div>
                          <div className="truncate text-xs text-slate-500">{u.service_number} · {u.username}</div>
                        </div>
                      </div>
                    </td>
                    <td>
                      {u.id === me.id ? (
                        <span className="text-slate-700">{roleLabel(u.role)}</span>
                      ) : (
                        <Select aria-label={`Role of ${u.fullname}`} value={u.role} disabled={!u.is_active} onChange={(e) => void changeRole(u, e.target.value)} className="!w-44 !py-1.5">
                          {ROLES.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                        </Select>
                      )}
                    </td>
                    <td><div className="line-clamp-2 max-w-[15rem] text-slate-700" title={u.command}>{u.command}</div></td>
                    <td className="whitespace-nowrap">
                      {u.two_factor_enabled ? <Badge tone="green">Authenticator set up</Badge> : <Badge tone="amber">No authenticator</Badge>}
                      <div className="mt-1 text-xs text-slate-500">{u.last_login_at ? `Last sign-in ${dateTime(u.last_login_at)}` : "Never signed in"}</div>
                    </td>
                    <td className="text-right">
                      {u.id !== me.id && (
                        <RowMenu
                          label={`Actions for ${u.fullname}`}
                          items={[
                            { label: "Reset password", onSelect: () => void reset(u) },
                            ...(u.two_factor_enabled ? [{ label: "Reset authenticator", onSelect: () => void resetTwoFactor(u) }] : []),
                            { label: "Sign out everywhere", onSelect: () => void signOut(u) },
                            { label: u.is_active ? "Deactivate account" : "Activate account", onSelect: () => void toggle(u), danger: u.is_active },
                          ]}
                        />
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </div>
  );
}

function roleLabel(role: string) {
  return ROLES.find(([v]) => v === role)?.[1] ?? role;
}

function initials(name: string) {
  return name.split(/\s+/).filter(Boolean).slice(0, 2).map((p) => p[0]!.toUpperCase()).join("");
}

function Badge({ tone, children }: { tone: "green" | "amber" | "red" | "slate"; children: ReactNode }) {
  const tones = {
    green: "bg-emerald-50 text-emerald-700 ring-emerald-600/20",
    amber: "bg-amber-50 text-amber-800 ring-amber-600/20",
    red: "bg-red-50 text-red-700 ring-red-600/20",
    slate: "bg-slate-100 text-slate-600 ring-slate-500/20",
  };
  return <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset ${tones[tone]}`}>{children}</span>;
}

type MenuItem = { label: string; onSelect: () => void; danger?: boolean };

/** "Manage" button with a dropdown; fixed-positioned so the table's horizontal scroll container doesn't clip it. */
function RowMenu({ label, items }: { label: string; items: MenuItem[] }) {
  const [pos, setPos] = useState<{ top: number; right: number } | null>(null);
  const button = useRef<HTMLButtonElement>(null);
  const menu = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!pos) return;
    const close = () => setPos(null);
    const onPointer = (e: PointerEvent) => {
      if (!menu.current?.contains(e.target as Node) && !button.current?.contains(e.target as Node)) close();
    };
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") {
        close();
        button.current?.focus();
      }
    };
    document.addEventListener("pointerdown", onPointer);
    document.addEventListener("keydown", onKey);
    window.addEventListener("scroll", close, true);
    window.addEventListener("resize", close);
    menu.current?.querySelector("button")?.focus({ preventScroll: true });
    return () => {
      document.removeEventListener("pointerdown", onPointer);
      document.removeEventListener("keydown", onKey);
      window.removeEventListener("scroll", close, true);
      window.removeEventListener("resize", close);
    };
  }, [pos]);

  function open() {
    if (pos) return setPos(null);
    const r = button.current!.getBoundingClientRect();
    const height = items.length * 38 + 12;
    const top = r.bottom + 6 + height > window.innerHeight ? r.top - 6 - height : r.bottom + 6;
    setPos({ top, right: window.innerWidth - r.right });
  }

  return (
    <>
      <button
        ref={button}
        type="button"
        aria-label={label}
        aria-haspopup="menu"
        aria-expanded={!!pos}
        onClick={open}
        className="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 transition-colors hover:border-nis-primary hover:text-nis-primary"
      >
        Manage
        <svg aria-hidden viewBox="0 0 20 20" className="h-4 w-4" fill="currentColor"><path d="M5.3 7.3a1 1 0 0 1 1.4 0L10 10.6l3.3-3.3a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 0-1.4Z" /></svg>
      </button>
      {pos && (
        <div ref={menu} role="menu" style={{ top: pos.top, right: pos.right }} className="fixed z-50 w-56 rounded-lg border border-slate-200 bg-white py-1.5 text-left shadow-lg">
          {items.map((item) => (
            <button
              key={item.label}
              type="button"
              role="menuitem"
              onClick={() => {
                setPos(null);
                item.onSelect();
              }}
              className={`block w-full px-4 py-2 text-left text-sm focus:outline-none ${item.danger ? "text-red-700 hover:bg-red-50 focus:bg-red-50" : "text-slate-700 hover:bg-slate-50 focus:bg-slate-50"}`}
            >
              {item.label}
            </button>
          ))}
        </div>
      )}
    </>
  );
}
