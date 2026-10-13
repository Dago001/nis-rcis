const steps = ["Submitted", "Under review", "Biometrics appointment", "Card production", "Ready / collected"];

/** Progress tracker (legacy track.php five-step timeline). */
export function Tracker({ step, status }: { step: number; status: string }) {
  const halted = status === "REJECTED";
  return (
    <ol className="grid grid-cols-5 gap-2" aria-label="Application progress">
      {steps.map((label, index) => {
        const n = index + 1;
        const done = n < step || (n === step && status === "ISSUED");
        const current = n === step && !done;
        return (
          <li key={label} className="flex flex-col items-center text-center">
            <span
              className={`flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold ${
                done ? "bg-nis-green text-white" : current ? (halted ? "bg-red-600 text-white" : "bg-nis-gold text-slate-900") : "bg-slate-200 text-slate-500"
              }`}
              aria-current={current ? "step" : undefined}
            >
              {done ? "✓" : n}
            </span>
            <span className="mt-1.5 text-[11px] leading-tight text-slate-600 sm:text-xs">{label}</span>
          </li>
        );
      })}
    </ol>
  );
}
