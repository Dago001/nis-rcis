const steps = ["Submitted", "Under review", "Biometrics", "Card production", "Ready / collected"];

/** Progress tracker (legacy track.php five-step timeline). */
export function Tracker({
  step,
  status,
  biometricsCompleted,
}: {
  step: number;
  status: string;
  biometricsCompleted?: boolean;
}) {
  const halted = status === "REJECTED";
  const isVettedOrBeyond = ["VETTED", "APPROVED_FOR_BIOMETRICS", "BIOMETRICS_CAPTURED", "APPROVED", "READY_FOR_COLLECTION", "ISSUED"].includes(status);
  const isBiometricsDone = Boolean(biometricsCompleted) || ["BIOMETRICS_CAPTURED", "APPROVED", "READY_FOR_COLLECTION", "ISSUED"].includes(status);

  return (
    <ol className="grid grid-cols-5 gap-2" aria-label="Application progress">
      {steps.map((label, index) => {
        const n = index + 1;
        let done = n < step || (n === step && status === "ISSUED");

        if (n === 1) {
          done = true;
        } else if (n === 2 && isVettedOrBeyond) {
          done = true;
        } else if (n === 3 && isBiometricsDone) {
          done = true;
        }

        const current =
          !done &&
          (n === step ||
            (n === 3 && isVettedOrBeyond && !isBiometricsDone) ||
            (n === 4 && isBiometricsDone && status !== "READY_FOR_COLLECTION" && status !== "ISSUED") ||
            (n === 5 && status === "READY_FOR_COLLECTION"));

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
