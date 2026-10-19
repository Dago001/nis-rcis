"use client";

import { useCallback, useEffect, useRef, useState, type FormEvent, type ReactNode } from "react";
import { PageTitle } from "@/components/PageTitle";
import { useStaff } from "@/components/StaffShell";
import { Alert, Button, Field, Input, Panel, Select, Spinner } from "@/components/ui";
import { api, ApiError } from "@/lib/api-client";
import { dateTime } from "@/lib/format";
import { NIGERIA_LGAS } from "@/lib/nigeria-lgas";
import type { StaffRole, StaffUser } from "@/lib/types";

const ALL_ROLES: [StaffRole, string][] = [
  ["SuperAdmin", "Super Administrator"],
  ["Admin", "Admin"],
  ["Supervisor", "Supervisor"],
  ["VettingOfficer", "Vetting Officer"],
  ["ApprovingOfficer", "Approving Officer"],
  ["EnrollmentOfficer", "Enrollment Officer"],
  ["ProductionOfficer", "Production Officer"],
  ["IssuingOfficer", "Issuing Officer"],
];

const STATES = Object.keys(NIGERIA_LGAS).sort();

export default function UsersPage() {
  const me = useStaff();
  const [users, setUsers] = useState<StaffUser[] | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [notice, setNotice] = useState<string | null>(null);
  const [query, setQuery] = useState("");
  const [roleFilter, setRoleFilter] = useState("");
  const [showForm, setShowForm] = useState(false);

  // Form State for dynamic Command / State / LGA
  const [selectedRole, setSelectedRole] = useState<StaffRole>("VettingOfficer");
  const [selectedState, setSelectedState] = useState<string>("");
  const [selectedLga, setSelectedLga] = useState<string>("");
  const [commandType, setCommandType] = useState<"STATE_LGA" | "HEADQUARTERS">("STATE_LGA");

  // Filter roles based on logged in user: Admin cannot see or add SuperAdmin
  const availableRoles = ALL_ROLES.filter(([role]) => {
    if (me.role === "Admin" && role === "SuperAdmin") return false;
    return true;
  });

  const load = useCallback(() => api<{ data: StaffUser[] }>("staff", "users").then((r) => setUsers(r.data)), []);
  useEffect(() => {
    void load();
  }, [load]);

  async function create(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    setErrors({});
    const formData = new FormData(form);
    const payload: Record<string, any> = Object.fromEntries(formData);

    if (commandType === "HEADQUARTERS") {
      payload.command = "National Headquarters, Abuja";
      payload.state = "";
      payload.lga = "";
    } else {
      payload.state = selectedState;
      payload.lga = selectedLga;
      payload.command = selectedLga ? `${selectedLga} LGA, ${selectedState} State Command` : `${selectedState} State Command`;
    }

    try {
      const r = await api<{ data: StaffUser; message?: string }>("staff", "users", { method: "POST", json: payload });
      setNotice(r.message ?? `Account created. Temporary password and login credentials have been emailed to ${r.data.email}.`);
      form.reset();
      setShowForm(false);
      setSelectedState("");
      setSelectedLga("");
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
    if (!confirm(`Issue a new temporary password for ${user.fullname}? Their current sessions will be signed out and the temporary password will be emailed to ${user.email}.`)) return;
    const r = await api<{ message: string }>("staff", `users/${user.id}/reset-password`, { method: "POST" });
    setNotice(r.message ?? `A new temporary password has been sent to ${user.fullname} (${user.email}).`);
    await load();
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
      (!q || [u.fullname, u.username, u.service_number, u.email, u.command, u.state, u.lga].some((v) => v?.toLowerCase().includes(q))),
  );
  const activeCount = (users ?? []).filter((u) => u.is_active).length;
  const lgas = selectedState ? NIGERIA_LGAS[selectedState] ?? [] : [];

  return (
    <div className="space-y-6">
      <PageTitle title="Staff accounts & roles" subtitle="Create and assign officers to Headquarters, State Commands, and Local Government Area centres." />
      {notice && <Alert tone="success">{notice}</Alert>}
      {showForm && (
        <Panel title="Add new officer" actions={<Button variant="ghost" className="!px-3 !py-1.5" onClick={() => setShowForm(false)}>Close</Button>}>
          <form onSubmit={create} className="space-y-4">
            <div className="grid gap-3 sm:grid-cols-3">
              <Field label="Full name" error={errors.fullname}><Input name="fullname" required /></Field>
              <Field label="Username" error={errors.username}><Input name="username" required /></Field>
              <Field label="Service number" error={errors.service_number}><Input name="service_number" placeholder="e.g. NIS-10492" required /></Field>
              <Field label="E-mail" error={errors.email}><Input name="email" type="email" required /></Field>
              <Field label="Role" error={errors.role}>
                <Select name="role" value={selectedRole} onChange={(e) => setSelectedRole(e.target.value as StaffRole)}>
                  {availableRoles.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                </Select>
              </Field>
              <Field label="Assignment level">
                <Select value={commandType} onChange={(e) => setCommandType(e.target.value as any)}>
                  <option value="STATE_LGA">State & LGA Command</option>
                  <option value="HEADQUARTERS">National Headquarters (Abuja)</option>
                </Select>
              </Field>
            </div>

            {commandType === "STATE_LGA" && (
              <div className="rounded-xl border border-slate-200 bg-slate-50/75 p-4">
                <div className="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-600">Command Location & LGA Allocation</div>
                <div className="grid gap-3 sm:grid-cols-2">
                  <Field label="State" error={errors.state}>
                    <Select
                      name="state"
                      value={selectedState}
                      onChange={(e) => {
                        setSelectedState(e.target.value);
                        setSelectedLga("");
                      }}
                      required
                    >
                      <option value="">Select State…</option>
                      {STATES.map((s) => <option key={s} value={s}>{s}</option>)}
                    </Select>
                  </Field>

                  {selectedRole !== "ApprovingOfficer" && (
                    <Field label="Local Government Area (LGA)" error={errors.lga}>
                      <Select
                        name="lga"
                        value={selectedLga}
                        disabled={!selectedState}
                        onChange={(e) => setSelectedLga(e.target.value)}
                        required
                      >
                        <option value="">{selectedState ? "Select LGA…" : "Select a State first"}</option>
                        {lgas.map((l) => <option key={l} value={l}>{l}</option>)}
                      </Select>
                    </Field>
                  )}
                </div>

                <div className="mt-3 text-xs text-slate-500">
                  {selectedRole === "ApprovingOfficer" ? (
                    <span>• <strong>State Approving Officer:</strong> Maximum 1 active Approving Officer per State.</span>
                  ) : (
                    <span>• <strong>LGA Capacity Rule:</strong> Maximum 10 officers per LGA (5 Data Entry Officers and 5 Enrollment Officers).</span>
                  )}
                </div>
              </div>
            )}

            <div className="flex gap-3 pt-2">
              <Button type="submit">Create account</Button>
              <Button type="button" variant="secondary" onClick={() => setShowForm(false)}>Cancel</Button>
            </div>
          </form>
        </Panel>
      )}

      <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <header className="flex flex-wrap items-center gap-3 border-b border-slate-200 px-5 py-4">
          <div className="mr-auto">
            <h2 className="text-[15px] font-semibold text-slate-900">Officers & Commands</h2>
            {users && <p className="text-xs text-slate-500">{users.length} accounts · {activeCount} active</p>}
          </div>
          <Input type="search" placeholder="Search name, role, LGA, command…" aria-label="Search officers" value={query} onChange={(e) => setQuery(e.target.value)} className="!w-full !py-2 sm:!w-72" />
          <Select aria-label="Filter by role" value={roleFilter} onChange={(e) => setRoleFilter(e.target.value)} className="!w-auto !py-2">
            <option value="">All roles</option>
            {availableRoles.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
          </Select>
          {!showForm && <Button className="!py-2" onClick={() => setShowForm(true)}>+ Add officer</Button>}
        </header>

        {!users ? <div className="px-5"><Spinner /></div> : shown.length === 0 ? (
          <p className="px-5 py-10 text-center text-sm text-slate-500">No officers match your search.</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[840px] text-sm">
              <thead className="text-left">
                <tr className="[&>th]:whitespace-nowrap [&>th]:px-4 [&>th]:py-3 [&>th:first-child]:pl-5 [&>th:last-child]:pr-5">
                  <th className="w-12 text-slate-500">S/N</th><th>Officer</th><th>Role</th><th>Command / Jurisdiction</th><th>Access</th><th className="text-right"><span className="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
                {users && shown.map((u, idx) => (
                  <tr key={u.id} className="align-middle [&>td]:px-4 [&>td]:py-3.5 [&>td:first-child]:pl-5 [&>td:last-child]:pr-5">
                    <td className="font-mono text-xs text-slate-500">{idx + 1}</td>
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
                        <span className="font-medium text-slate-800">{roleLabel(u.role)}</span>
                      ) : (
                        <Select aria-label={`Role of ${u.fullname}`} value={u.role} disabled={!u.is_active} onChange={(e) => void changeRole(u, e.target.value)} className="!w-48 !py-1.5">
                          {availableRoles.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                        </Select>
                      )}
                    </td>
                    <td>
                      <div className="font-medium text-slate-800">{u.command}</div>
                      {(u.state || u.lga) && (
                        <div className="text-xs text-slate-500">
                          {u.lga ? `${u.lga} LGA · ` : ""}{u.state ? `${u.state} State` : ""}
                        </div>
                      )}
                    </td>
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
  return ALL_ROLES.find(([v]) => v === role)?.[1] ?? role;
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
        className="inline-flex items-center gap-1.5 px-2.5 py-1 text-sm font-medium text-slate-600 hover:text-nis-primary transition-colors focus:outline-none rounded hover:bg-slate-50"
      >
        <svg aria-hidden viewBox="0 0 20 20" className="h-4 w-4 text-slate-400 group-hover:text-nis-primary" fill="currentColor">
          <path d="M10 6a2 2 0 1 1 0-4 2 2 0 0 1 0 4ZM10 12a2 2 0 1 1 0-4 2 2 0 0 1 0 4ZM10 18a2 2 0 1 1 0-4 2 2 0 0 1 0 4Z" />
        </svg>
        <span>Manage</span>
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
