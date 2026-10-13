"use client";

import { useEffect } from "react";

export default function StaffError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {
    // Purge cached session data that might have caused the error
    try {
      sessionStorage.removeItem("nis_staff_dashboard");
      sessionStorage.removeItem("nis_staff_me");
    } catch {}
    console.error("Staff page error caught:", error);
  }, [error]);

  return (
    <div className="flex min-h-[400px] flex-col items-center justify-center p-6 text-center">
      <div className="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm max-w-md w-full">
        <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 text-amber-600 text-2xl mb-4">
          ⚠
        </div>
        <h2 className="text-lg font-semibold text-slate-900 mb-2">Unable to display dashboard</h2>
        <p className="text-sm text-slate-600 mb-6">
          A temporary issue occurred while loading this view. Cached data has been cleared.
        </p>
        <div className="flex gap-3 justify-center">
          <button
            onClick={() => {
              try {
                sessionStorage.clear();
              } catch {}
              reset();
            }}
            className="rounded-xl bg-nis-primary px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-nis-primary-dark transition"
          >
            Try again
          </button>
          <button
            onClick={() => {
              window.location.reload();
            }}
            className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition"
          >
            Refresh page
          </button>
        </div>
      </div>
    </div>
  );
}
