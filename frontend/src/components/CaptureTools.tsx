"use client";

import { useEffect, useRef, useState } from "react";
import { Button } from "./ui";

/** Live facial photo from the desk webcam (legacy webcam-capture.js). */
export function WebcamCapture({ onCapture }: { onCapture: (dataUrl: string | null) => void }) {
  const video = useRef<HTMLVideoElement>(null);
  const [photo, setPhoto] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let stream: MediaStream | null = null;
    navigator.mediaDevices
      ?.getUserMedia({ video: { width: 640, height: 800, facingMode: "user" } })
      .then((s) => {
        stream = s;
        if (video.current) video.current.srcObject = s;
      })
      .catch(() => setError("Camera unavailable. Check browser permissions."));
    return () => stream?.getTracks().forEach((t) => t.stop());
  }, []);

  function capture() {
    const v = video.current;
    if (!v) return;
    const canvas = document.createElement("canvas");
    canvas.width = 480;
    canvas.height = 600;
    const ctx = canvas.getContext("2d")!;
    const scale = Math.max(canvas.width / v.videoWidth, canvas.height / v.videoHeight);
    const w = v.videoWidth * scale;
    const h = v.videoHeight * scale;
    ctx.drawImage(v, (canvas.width - w) / 2, (canvas.height - h) / 2, w, h);
    const url = canvas.toDataURL("image/jpeg", 0.9);
    setPhoto(url);
    onCapture(url);
  }

  return (
    <div className="space-y-3">
      {error && <p className="text-sm text-red-700">{error}</p>}
      <div className="flex gap-4">
        <video ref={video} autoPlay playsInline muted className="h-60 w-48 rounded-lg bg-slate-900 object-cover" />
        {photo && (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={photo} alt="Captured" className="h-60 w-48 rounded-lg border-2 border-nis-green object-cover" />
        )}
      </div>
      <div className="flex gap-2">
        <Button type="button" onClick={capture} disabled={!!error}>{photo ? "Retake photo" : "Capture photo"}</Button>
        {photo && <Button type="button" variant="secondary" onClick={() => { setPhoto(null); onCapture(null); }}>Clear</Button>}
      </div>
    </div>
  );
}

/** Signature pad (legacy signature-pad.js). */
export function SignaturePad({ onChange }: { onChange: (dataUrl: string | null) => void }) {
  const canvas = useRef<HTMLCanvasElement>(null);
  const drawing = useRef(false);
  const [empty, setEmpty] = useState(true);

  useEffect(() => {
    const ctx = canvas.current!.getContext("2d")!;
    ctx.fillStyle = "#fff";
    ctx.fillRect(0, 0, canvas.current!.width, canvas.current!.height);
    ctx.lineWidth = 2.5;
    ctx.lineCap = "round";
    ctx.strokeStyle = "#0f172a";
  }, []);

  function point(e: React.PointerEvent<HTMLCanvasElement>) {
    const rect = canvas.current!.getBoundingClientRect();
    return { x: ((e.clientX - rect.left) / rect.width) * canvas.current!.width, y: ((e.clientY - rect.top) / rect.height) * canvas.current!.height };
  }

  function start(e: React.PointerEvent<HTMLCanvasElement>) {
    drawing.current = true;
    canvas.current!.setPointerCapture(e.pointerId);
    const ctx = canvas.current!.getContext("2d")!;
    const p = point(e);
    ctx.beginPath();
    ctx.moveTo(p.x, p.y);
  }

  function move(e: React.PointerEvent<HTMLCanvasElement>) {
    if (!drawing.current) return;
    const ctx = canvas.current!.getContext("2d")!;
    const p = point(e);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
  }

  function end() {
    if (!drawing.current) return;
    drawing.current = false;
    setEmpty(false);
    onChange(canvas.current!.toDataURL("image/png"));
  }

  function clear() {
    const ctx = canvas.current!.getContext("2d")!;
    ctx.fillStyle = "#fff";
    ctx.fillRect(0, 0, canvas.current!.width, canvas.current!.height);
    setEmpty(true);
    onChange(null);
  }

  return (
    <div className="space-y-2">
      <canvas
        ref={canvas}
        width={600}
        height={200}
        className="w-full max-w-lg touch-none rounded-lg border-2 border-dashed border-slate-300 bg-white"
        onPointerDown={start}
        onPointerMove={move}
        onPointerUp={end}
        onPointerLeave={end}
        aria-label="Signature pad"
      />
      <Button type="button" variant="secondary" onClick={clear} disabled={empty}>Clear signature</Button>
    </div>
  );
}
