"use client";

import { useCallback, useEffect, useState, type FormEvent } from "react";
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
      await load();
    } catch (e) {
      if (e instanceof ApiError) setErrors(e.fieldErrors());
    }
  }

  async function toggle(user: StaffUser) {
    await api("staff", `users/${user.id}`, { method: "PATCH", json: { is_active: !user.is_active } });
    await load();
  }

  async function reset(user: StaffUser) {
    if (!confirm(`Issue a new temporary password for ${user.fullname}? Their current sessions will be signed out.`)) return;
    const r = await api<{ temporary_password: string }>("staff", `users/${user.id}/reset-password`, { method: "POST" });
    setSecret({ who: user.fullname, password: r.temporary_password });
  }

  return (
    <div className="space-y-6">
      <PageTitle title="Staff accounts" />
      {secret && (
        <Alert tone="warning">
          Temporary password for <strong>{secret.who}</strong>: <code className="rounded bg-white px-2 py-0.5 font-mono">{secret.password}</code>
          <br />It is shown only once. Give it to the officer in person; they must change it at first sign-in.
        </Alert>
      )}
      <Panel title="Add officer">
        <form onSubmit={create} className="grid gap-3 sm:grid-cols-3">
          <Field label="Full name" error={errors.fullname}><Input name="fullname" required /></Field>
          <Field label="Username" error={errors.username}><Input name="username" required /></Field>
          <Field label="Service number" error={errors.service_number}><Input name="service_number" required /></Field>
          <Field label="E-mail" error={errors.email}><Input name="email" type="email" required /></Field>
          <Field label="Role" error={errors.role}><Select name="role">{ROLES.map(([v, l]) => <option key={v} value={v}>{l}</option>)}</Select></Field>
          <Field label="Command" error={errors.command}><Input name="command" defaultValue="National Processing Center" required /></Field>
          <div className="sm:col-span-3"><Button type="submit">Create account</Button></div>
        </form>
      </Panel>
      {!users ? <Spinner /> : (
        <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th className="px-4 py-3">Officer</th><th className="px-4 py-3">Role</th><th className="px-4 py-3">Command</th><th className="px-4 py-3">Last sign-in</th><th className="px-4 py-3" /></tr></thead>
            <tbody className="divide-y divide-slate-100">
              {users.map((u) => (
                <tr key={u.id} className={u.is_active ? "" : "opacity-50"}>
                  <td className="px-4 py-2.5"><div className="font-medium">{u.fullname}</div><div className="text-xs text-slate-500">{u.service_number} · {u.username}</div></td>
                  <td className="px-4 py-2.5">{ROLES.find(([v]) => v === u.role)?.[1]}</td>
                  <td className="px-4 py-2.5">{u.command}</td>
                  <td className="px-4 py-2.5">{dateTime(u.last_login_at)}</td>
                  <td className="space-x-3 whitespace-nowrap px-4 py-2.5 text-right">
                    {u.id !== me.id && (
                      <>
                        <button className="text-nis-green underline" onClick={() => reset(u)}>Reset password</button>
                        <button className="text-red-700 underline" onClick={() => toggle(u)}>{u.is_active ? "Deactivate" : "Activate"}</button>
                      </>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
