"use client";

import { useEffect, useState } from "react";
import { useI18n } from "@/components/I18nProvider";
import { api } from "@/lib/api-client";

type State = "unsupported" | "unavailable" | "denied" | "off" | "on" | "busy";

function urlBase64ToUint8Array(base64: string): Uint8Array<ArrayBuffer> {
  const padded = (base64 + "=".repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, "+").replace(/_/g, "/");
  const raw = atob(padded);
  const out = new Uint8Array(new ArrayBuffer(raw.length));
  for (let i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
  return out;
}

type InstallEvent = Event & { prompt: () => Promise<void> };

/**
 * "Notifications on this device" and "Install the app" for the applicant
 * portal. Push messages are sent in addition to the e-mails, never instead.
 */
export function PushToggle() {
  const { t } = useI18n();
  const [state, setState] = useState<State>("busy");
  const [key, setKey] = useState<string | null>(null);
  const [install, setInstall] = useState<InstallEvent | null>(null);

  useEffect(() => {
    const onPrompt = (e: Event) => {
      e.preventDefault();
      setInstall(e as InstallEvent);
    };
    window.addEventListener("beforeinstallprompt", onPrompt);

    (async () => {
      if (!("serviceWorker" in navigator) || !("PushManager" in window) || !("Notification" in window)) return setState("unsupported");
      const { public_key } = await api<{ public_key: string | null }>("public", "push-key").catch(() => ({ public_key: null }));
      if (!public_key) return setState("unavailable");
      setKey(public_key);
      if (Notification.permission === "denied") return setState("denied");
      const reg = await navigator.serviceWorker.ready;
      setState((await reg.pushManager.getSubscription()) ? "on" : "off");
    })();

    return () => window.removeEventListener("beforeinstallprompt", onPrompt);
  }, []);

  async function turnOn() {
    if (!key) return;
    setState("busy");
    try {
      if ((await Notification.requestPermission()) !== "granted") return setState("denied");
      const reg = await navigator.serviceWorker.ready;
      const sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(key) });
      await api("applicant", "push-subscriptions", { method: "POST", json: sub.toJSON() });
      setState("on");
    } catch {
      setState("off");
    }
  }

  async function turnOff() {
    setState("busy");
    const reg = await navigator.serviceWorker.ready;
    const sub = await reg.pushManager.getSubscription();
    if (sub) {
      await api("applicant", "push-subscriptions", { method: "DELETE", json: { endpoint: sub.endpoint } }).catch(() => undefined);
      await sub.unsubscribe();
    }
    setState("off");
  }

  if (state === "unsupported" || state === "unavailable") {
    return install ? <InstallButton onClick={() => install.prompt()} label={t("Install the app")} /> : null;
  }

  return (
    <section className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-5 text-sm shadow-sm" aria-label={t("Notifications on this device")}>
      <div>
        <h2 className="text-[15px] font-medium text-nis-primary-dark">{t("Notifications on this device")}</h2>
        <p className="mt-1 text-slate-600">
          {state === "on" ? t("On: you will get a notification here as well as the e-mail.")
            : state === "denied" ? t("Blocked in your browser settings. Allow notifications for this site to turn them on.")
              : t("Get a notification on this phone or computer when your application changes, as well as the e-mail.")}
        </p>
      </div>
      <div className="flex gap-2">
        {install && <InstallButton onClick={() => install.prompt()} label={t("Install the app")} />}
        {state !== "denied" && (
          <button onClick={state === "on" ? turnOff : turnOn} disabled={state === "busy"}
            className={`rounded-md px-4 py-2 font-medium disabled:opacity-50 ${state === "on" ? "border border-slate-300 hover:border-nis-primary" : "bg-nis-primary text-white hover:bg-nis-primary-dark"}`}>
            {state === "on" ? t("Turn off") : t("Turn on notifications")}
          </button>
        )}
      </div>
    </section>
  );
}

function InstallButton({ onClick, label }: { onClick: () => void; label: string }) {
  return <button onClick={onClick} className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:border-nis-primary hover:text-nis-primary">{label}</button>;
}
