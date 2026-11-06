"use client";

import { useCallback, useEffect, useState, type FormEvent, type ReactNode } from "react";
import { PageTitle } from "@/components/PageTitle";
import { useStaff } from "@/components/StaffShell";
import { Alert, Button, Field, Input, Panel, Select, Spinner } from "@/components/ui";
import { api, ApiError } from "@/lib/api-client";
import { dateTime } from "@/lib/format";
import type { StaffRole, StaffUser } from "@/lib/types";

const ALL_ROLES: [StaffRole, string][] = [
  ["SuperAdmin", "Super Administrator"],
  ["HeadquartersAdmin", "Headquarters Administrator"],
  ["ZonalAdmin", "Zonal Administrator"],
  ["CommandAdmin", "Command Administrator"],
  ["VettingOfficer", "Vetting Officer"],
  ["ApprovingOfficer", "Approving Officer"],
  ["BiometricsOfficer", "Biometrics Officer"],
  ["CardProductionOfficer", "Card Production Officer (HQ Sauka)"],
  ["CardDistributionOfficer", "Card Distribution Officer (HQ Sauka)"],
  ["CardReceivingOfficer", "Card Receiving Officer (Commands)"],
  ["CardIssuanceOfficer", "Issuance Officer (Commands)"],
  ["ReportingAuditOfficer", "Reporting / Audit Officer"],
  // Legacy roles maintained for backward compatibility
  ["Admin", "Administrator (Legacy)"],
  ["Supervisor", "Supervisor (Legacy)"],
  ["EnrollmentOfficer", "Enrollment Officer (Legacy)"],
  ["ProductionOfficer", "Production Officer (Legacy)"],
  ["IssuingOfficer", "Issuing Officer (Legacy)"],
];

export const NIS_RANKS: [string, string][] = [
  ["CGI", "Comptroller General of Immigration"],
  ["DCGI", "Deputy Comptroller General of Immigration"],
  ["ACGI", "Assistant Comptroller General of Immigration"],
  ["CIS", "Comptroller of Immigration"],
  ["DCI", "Deputy Comptroller of Immigration"],
  ["ACI", "Assistant Comptroller of Immigration"],
  ["CSI", "Chief Superintendent of Immigration"],
  ["SI", "Superintendent of Immigration"],
  ["DSI", "Deputy Superintendent of Immigration"],
  ["ASI 1", "Assistant Superintendent of Immigration 1"],
  ["ASI 2", "Assistant Superintendent of Immigration 2"],
  ["CII", "Chief Inspector of Immigration"],
  ["DII", "Deputy Chief Inspector of Immigration"],
  ["SII", "Senior Inspector of Immigration"],
  ["II", "Inspector of Immigration"],
  ["AIC", "Assistant Inspector of Immigration"],
  ["IA 1", "Immigration Assistant 1"],
  ["IA 2", "Immigration Assistant 2"],
  ["IA 3", "Immigration Assistant 3"],
];

interface NisZoneItem {
  id: number;
  code: string;
  name: string;
  commands: { id: number; code: string; name: string; state?: string }[];
}

export default function UsersPage() {
  const me = useStaff();
  const [users, setUsers] = useState<StaffUser[] | null>(null);
  const [zones, setZones] = useState<NisZoneItem[]>([]);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [notice, setNotice] = useState<string | null>(null);
  const [query, setQuery] = useState("");
  const [roleFilter, setRoleFilter] = useState("");
  const [showForm, setShowForm] = useState(false);

  // Form State for dynamic Geographic Allocation
  const [selectedRole, setSelectedRole] = useState<StaffRole>("VettingOfficer");
  const [allocationLevel, setAllocationLevel] = useState<"HEADQUARTERS" | "ZONE" | "COMMAND">("HEADQUARTERS");
  const [hqUnit, setHqUnit] = useState<string>("NIS Headquarters Sauka Abuja");
  const [selectedZoneId, setSelectedZoneId] = useState<number | "">("");
  const [selectedCommandId, setSelectedCommandId] = useState<number | "">("");
  const [geofenceExempt, setGeofenceExempt] = useState(false);

  // Filter available roles based on logged-in user jurisdiction
  const availableRoles = ALL_ROLES.filter(([role]) => {
    if (me.role === "SuperAdmin") return true;
    if (me.role === "HeadquartersAdmin" || me.role === "Admin") {
      return role !== "SuperAdmin";
    }
    if (me.role === "ZonalAdmin") {
      return !["SuperAdmin", "HeadquartersAdmin", "CardProductionOfficer", "CardDistributionOfficer"].includes(role);
    }
    if (me.role === "CommandAdmin") {
      return ["VettingOfficer", "ApprovingOfficer", "BiometricsOfficer", "CardReceivingOfficer", "CardIssuanceOfficer", "ReportingAuditOfficer", "EnrollmentOfficer", "IssuingOfficer"].includes(role);
    }
    return false;
  });

  const load = useCallback(() => api<{ data: StaffUser[] }>("staff", "users").then((r) => setUsers(r.data)), []);

  useEffect(() => {
    void load();
    // Load NIS Zones & Commands directory
    api<{ zones?: NisZoneItem[] }>("public", "enrollment-centers")
      .then((r) => {
        if (r.zones) setZones(r.zones);
      })
      .catch(() => {});
  }, [load]);

  // Adjust allocation level automatically when role changes
  useEffect(() => {
    if (["CardProductionOfficer", "CardDistributionOfficer", "HeadquartersAdmin"].includes(selectedRole)) {
      setAllocationLevel("HEADQUARTERS");
    } else if (selectedRole === "ZonalAdmin") {
      setAllocationLevel("ZONE");
    } else if (["CardReceivingOfficer", "CardIssuanceOfficer", "CommandAdmin"].includes(selectedRole)) {
      setAllocationLevel("COMMAND");
    }
  }, [selectedRole]);

  const activeZone = zones.find((z) => z.id === selectedZoneId);
  const activeCommands = activeZone ? activeZone.commands : zones.flatMap((z) => z.commands);

  async function create(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    setErrors({});
    const formData = new FormData(form);
    const payload: Record<string, any> = Object.fromEntries(formData);

    payload.role = selectedRole;
    payload.geofence_exempt = geofenceExempt;
    if (!payload.rank) payload.rank = null;

    if (allocationLevel === "HEADQUARTERS") {
      payload.headquarters_unit = hqUnit;
      payload.command = `NIS Headquarters (${hqUnit})`;
      payload.zone_id = null;
      payload.command_id = null;
    } else if (allocationLevel === "ZONE") {
      payload.zone_id = selectedZoneId || null;
      payload.command_id = null;
      payload.headquarters_unit = null;
      const z = zones.find((item) => item.id === selectedZoneId);
      payload.command = z ? z.name : "NIS Zonal Headquarters";
    } else {
      payload.command_id = selectedCommandId || null;
      payload.headquarters_unit = null;
      const cmd = activeCommands.find((c) => c.id === selectedCommandId);
      if (cmd) {
        payload.command = cmd.name;
        payload.zone_id = selectedZoneId || null;
        payload.state = cmd.state || "";
      }
    }

    try {
      const r = await api<{ data: StaffUser; message?: string }>("staff", "users", { method: "POST", json: payload });
      setNotice(r.message ?? `Account created. Temporary password and login credentials have been emailed to ${r.data.email}.`);
      form.reset();
      setShowForm(false);
      setSelectedZoneId("");
      setSelectedCommandId("");
      setGeofenceExempt(false);
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

  async function changeRank(user: StaffUser, rank: string) {
    if (rank === (user.rank || "")) return;
    await api("staff", `users/${user.id}`, { method: "PATCH", json: { rank: rank || null } });
    setNotice(`Updated ${user.fullname}'s rank to ${rank || "Unassigned"}.`);
    await load();
  }

  const q = query.trim().toLowerCase();
  const shown = (users ?? []).filter(
    (u) =>
      (!roleFilter || u.role === roleFilter) &&
      (!q || [u.fullname, u.username, u.service_number, u.email, u.command, u.zone_name, u.command_name, u.headquarters_unit].some((v) => v?.toLowerCase().includes(q))),
  );
  const activeCount = (users ?? []).filter((u) => u.is_active).length;

  const isCommandAdmin = me.role === "CommandAdmin";

  return (
    <div className="space-y-6">
      <PageTitle
        title={isCommandAdmin ? "Command Staff Management" : "NIS Staff Directory & National Access Control"}
        subtitle={isCommandAdmin ? `${me.command || "Command"} — Authorized Operational Officers` : "Manage official NIS staff accounts, 5-digit NIS numbers, jurisdictional roles, and physical geofence boundaries across Headquarters, Zones, and Commands."}
      />
      {notice && <Alert tone="success">{notice}</Alert>}

      {showForm && (
        <Panel title="Add New NIS Officer Account" actions={<Button variant="ghost" className="!px-3 !py-1.5" onClick={() => setShowForm(false)}>Close</Button>}>
          <form onSubmit={create} className="space-y-4">
            <div className="grid gap-3 sm:grid-cols-4">
              <Field label="Full name" error={errors.fullname}>
                <Input name="fullname" placeholder="e.g. Ibrahim Musa" required />
              </Field>
              <Field label="NIS Rank" error={errors.rank}>
                <Select name="rank" defaultValue="">
                  <option value="">No Rank / Civilian</option>
                  {NIS_RANKS.map(([code, title]) => (
                    <option key={code} value={code}>
                      {code} — {title}
                    </option>
                  ))}
                </Select>
              </Field>
              <Field label="Username" error={errors.username}>
                <Input name="username" placeholder="e.g. ibrahim.musa" required />
              </Field>
              <Field label="5-Digit NIS Number" error={errors.service_number}>
                <div className="relative">
                  <Input name="service_number" placeholder="NIS-10492 or 10492" required />
                  <span className="absolute right-2.5 top-2.5 text-[11px] font-mono font-medium text-slate-400">5 digits</span>
                </div>
              </Field>
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
              <Field label="Official E-mail Address" error={errors.email}>
                <Input name="email" type="email" placeholder="officer@immigration.gov.ng" required />
              </Field>
              <Field label="Official Staff Role" error={errors.role}>
                <Select
                  name="role"
                  value={selectedRole}
                  onChange={(e) => setSelectedRole(e.target.value as StaffRole)}
                  required
                >
                  {availableRoles.map(([v, l]) => (
                    <option key={v} value={v}>{l}</option>
                  ))}
                </Select>
              </Field>
            </div>

            {/* Geographical Jurisdiction Selection */}
            <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
              <div className="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-600">
                NIS Organisational Jurisdiction & Deployment
              </div>
              <div className="mb-3 flex flex-wrap gap-4 text-sm">
                {!isCommandAdmin && (
                  <>
                    <label className="flex items-center gap-2 cursor-pointer">
                      <input
                        type="radio"
                        name="allocation_type"
                        checked={allocationLevel === "HEADQUARTERS"}
                        onChange={() => setAllocationLevel("HEADQUARTERS")}
                        className="text-emerald-700 focus:ring-emerald-600"
                      />
                      <span className="font-medium text-slate-800">NIS Service Headquarters (Sauka Abuja)</span>
                    </label>
                    <label className="flex items-center gap-2 cursor-pointer">
                      <input
                        type="radio"
                        name="allocation_type"
                        checked={allocationLevel === "ZONE"}
                        onChange={() => setAllocationLevel("ZONE")}
                        className="text-emerald-700 focus:ring-emerald-600"
                      />
                      <span className="font-medium text-slate-800">NIS Zonal Office (Zone A - H)</span>
                    </label>
                  </>
                )}
                <label className="flex items-center gap-2 cursor-pointer">
                  <input
                    type="radio"
                    name="allocation_type"
                    checked={allocationLevel === "COMMAND"}
                    onChange={() => setAllocationLevel("COMMAND")}
                    className="text-emerald-700 focus:ring-emerald-600"
                  />
                  <span className="font-medium text-slate-800">NIS State / Border Command</span>
                </label>
              </div>

              {allocationLevel === "HEADQUARTERS" && (
                <div className="grid gap-3 sm:grid-cols-2">
                  <Field label="Headquarters Directorate / Unit" error={errors.headquarters_unit}>
                    <Select value={hqUnit} onChange={(e) => setHqUnit(e.target.value)}>
                      <option value="Central Card Production Centre">Central Card Production Centre</option>
                      <option value="Logistics & Card Distribution Directorate">Logistics & Card Distribution Directorate</option>
                      <option value="National Vetting & Investigation Directorate">National Vetting & Investigation Directorate</option>
                      <option value="ICT & Border Management Systems">ICT & Border Management Systems</option>
                      <option value="Comptroller General Office (CGO)">Comptroller General Office (CGO)</option>
                      <option value="General Service Headquarters">General Service Headquarters</option>
                    </Select>
                  </Field>
                  <div className="flex items-center rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
                    ℹ️ Headquarters officers hold national purview for Vetting and Centralized Card Production.
                  </div>
                </div>
              )}

              {allocationLevel === "ZONE" && (
                <div className="grid gap-3 sm:grid-cols-2">
                  <Field label="Select NIS Zone" error={errors.zone_id}>
                    <Select
                      value={selectedZoneId}
                      onChange={(e) => setSelectedZoneId(e.target.value ? Number(e.target.value) : "")}
                      required
                    >
                      <option value="">-- Choose NIS Zone --</option>
                      {zones.map((z) => (
                        <option key={z.id} value={z.id}>{z.code} — {z.name}</option>
                      ))}
                    </Select>
                  </Field>
                  <div className="flex items-center rounded border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-800">
                    ℹ️ Zonal Administrators have oversight strictly over State Commands within their assigned Zone.
                  </div>
                </div>
              )}

              {allocationLevel === "COMMAND" && (
                <div className="grid gap-3 sm:grid-cols-2">
                  <Field label="Filter by Zone (Optional)">
                    <Select
                      value={selectedZoneId}
                      onChange={(e) => {
                        setSelectedZoneId(e.target.value ? Number(e.target.value) : "");
                        setSelectedCommandId("");
                      }}
                    >
                      <option value="">All Zones</option>
                      {zones.map((z) => (
                        <option key={z.id} value={z.id}>{z.code} — {z.name}</option>
                      ))}
                    </Select>
                  </Field>
                  <Field label="Select Official NIS Command" error={errors.command_id}>
                    <Select
                      value={selectedCommandId}
                      onChange={(e) => setSelectedCommandId(e.target.value ? Number(e.target.value) : "")}
                      required
                    >
                      <option value="">-- Choose NIS Command --</option>
                      {activeCommands.map((c) => (
                        <option key={c.id} value={c.id}>{c.name} {c.state ? `(${c.state})` : ""}</option>
                      ))}
                    </Select>
                  </Field>
                </div>
              )}
            </div>

            {/* Geofence Configuration */}
            {(me.role === "SuperAdmin" || me.role === "HeadquartersAdmin" || me.role === "Admin") && (
              <div className="flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-3 text-sm">
                <input
                  type="checkbox"
                  id="geofence_exempt"
                  checked={geofenceExempt}
                  onChange={(e) => setGeofenceExempt(e.target.checked)}
                  className="h-4 w-4 rounded border-slate-300 text-emerald-700 focus:ring-emerald-600"
                />
                <label htmlFor="geofence_exempt" className="cursor-pointer text-slate-700">
                  <span className="font-semibold text-slate-900">Exempt from Physical Office Geofence:</span> Check only for roving supervisors or emergency audit personnel who may operate outside authorized perimeter boundaries.
                </label>
              </div>
            )}

            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="ghost" onClick={() => setShowForm(false)}>Cancel</Button>
              <Button type="submit">Create Official Account & Send Credentials</Button>
            </div>
          </form>
        </Panel>
      )}

      <Panel>
        <header className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 className="text-[15px] font-semibold text-slate-900">NIS Staff Directory & Jurisdictions</h2>
            {users && <p className="text-xs text-slate-500">{users.length} accounts • {activeCount} active</p>}
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <Input
              type="search"
              placeholder="Search name, NIS No., role, command..."
              aria-label="Search officers"
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              className="!w-full !py-2 sm:!w-64"
            />
            <Select
              aria-label="Filter by role"
              value={roleFilter}
              onChange={(e) => setRoleFilter(e.target.value)}
              className="!w-auto !py-2"
            >
              <option value="">All roles</option>
              {availableRoles.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
            </Select>
            {!showForm && (
              <Button className="!py-2" onClick={() => setShowForm(true)}>+ Add Officer</Button>
            )}
          </div>
        </header>

        {!users ? (
          <div className="px-5 py-8 text-center"><Spinner /></div>
        ) : shown.length === 0 ? (
          <div className="py-8 text-center text-sm text-slate-500">No NIS staff accounts match your filter criteria.</div>
        ) : (
          <div className="-mx-5 mt-4 overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="border-y border-slate-200 bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                <tr>
                  <th className="w-12 pl-5 py-3">S/N</th>
                  <th className="py-3">Officer Details</th>
                  <th className="py-3">Rank</th>
                  <th className="py-3">Official Role</th>
                  <th className="py-3">Jurisdiction / Command</th>
                  <th className="py-3">Physical Geofence</th>
                  <th className="py-3">Status</th>
                  <th className="pr-5 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {shown.map((u, idx) => (
                  <tr key={u.id} className="align-middle hover:bg-slate-50/60">
                    <td className="pl-5 py-3.5 text-xs text-slate-400 font-mono">{idx + 1}</td>
                    <td className="py-3.5">
                      <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-800">
                          {initials(u.fullname)}
                        </span>
                        <div>
                          <div className="font-semibold text-slate-900">{u.fullname}</div>
                          <div className="text-xs text-slate-500 font-mono">
                            <span className="font-semibold text-emerald-700">{u.service_number}</span> • {u.email}
                          </div>
                        </div>
                      </div>
                    </td>
                    <td className="py-3.5">
                      <Select
                        aria-label={`Rank for ${u.fullname}`}
                        value={u.rank || ""}
                        onChange={(e) => changeRank(u, e.target.value)}
                        className="!py-1 !text-xs !w-auto font-semibold text-slate-800"
                      >
                        <option value="">— (None)</option>
                        {NIS_RANKS.map(([code]) => (
                          <option key={code} value={code}>
                            {code}
                          </option>
                        ))}
                      </Select>
                    </td>
                    <td className="py-3.5">
                      {u.id === me.id ? (
                        <span className="inline-flex items-center rounded-md bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                          {roleLabel(u.role)} (You)
                        </span>
                      ) : (
                        <Select
                          aria-label={`Role for ${u.fullname}`}
                          value={u.role}
                          onChange={(e) => changeRole(u, e.target.value)}
                          className="!py-1 !text-xs !w-auto"
                        >
                          {availableRoles.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                        </Select>
                      )}
                    </td>
                    <td className="py-3.5">
                      <div className="font-medium text-slate-800">
                        {u.headquarters_unit ? `HQ: ${u.headquarters_unit}` : u.command_name || u.command}
                      </div>
                      {u.zone_name && (
                        <div className="text-xs text-slate-500">
                          Zone: {u.zone_name} {u.state ? `• State: ${u.state}` : ""}
                        </div>
                      )}
                    </td>
                    <td className="py-3.5">
                      {u.geofence_exempt ? (
                        <span className="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-600/20">
                          Exempt
                        </span>
                      ) : (
                        <span className="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                          Enforced
                        </span>
                      )}
                    </td>
                    <td className="py-3.5">
                      <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                        u.is_active ? "bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20" : "bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20"
                      }`}>
                        {u.is_active ? "Active" : "Suspended"}
                      </span>
                    </td>
                    <td className="pr-5 py-3.5 text-right space-x-1 whitespace-nowrap">
                      {u.id !== me.id && (
                        <Button
                          variant="ghost"
                          className="!px-2 !py-1 !text-xs"
                          onClick={() => toggle(u)}
                        >
                          {u.is_active ? "Suspend" : "Activate"}
                        </Button>
                      )}
                      <Button
                        variant="ghost"
                        className="!px-2 !py-1 !text-xs"
                        onClick={() => reset(u)}
                      >
                        Reset PW
                      </Button>
                      {u.two_factor_enabled && (
                        <Button
                          variant="ghost"
                          className="!px-2 !py-1 !text-xs"
                          onClick={() => resetTwoFactor(u)}
                        >
                          Reset 2FA
                        </Button>
                      )}
                      <Button
                        variant="ghost"
                        className="!px-2 !py-1 !text-xs text-rose-600 hover:text-rose-700"
                        onClick={() => signOut(u)}
                      >
                        Sign out
                      </Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>
    </div>
  );
}

function roleLabel(role: string): string {
  return ALL_ROLES.find(([v]) => v === role)?.[1] ?? role;
}

function initials(name: string): string {
  return name.split(/\s+/).filter(Boolean).slice(0, 2).map((p) => p[0]!.toUpperCase()).join("");
}
