"use client";

import { useEffect, useRef, useState } from "react";
import { Button, Select } from "./ui";

const CAMERA_KEY = "nis-rcis:camera";
// Software cameras (screen-sharing apps, OBS, ...) show nothing unless their app is running.
const VIRTUAL_CAMERA = /ideashare|ideacamera|virtual|obs|manycam|snap camera|droidcam|nvidia broadcast/i;

function savedCamera(): string {
  try {
    return localStorage.getItem(CAMERA_KEY) ?? "";
  } catch {
    return "";
  }
}

function rememberCamera(id: string) {
  try {
    if (id) localStorage.setItem(CAMERA_KEY, id);
    else localStorage.removeItem(CAMERA_KEY);
  } catch {
    // Private window or blocked storage: the choice just is not remembered.
  }
}

function cameraError(e: unknown): string {
  const name = e instanceof DOMException ? e.name : "";
  if (name === "NotAllowedError" || name === "SecurityError")
    return "Camera access is blocked for this site. Allow the camera (camera or padlock icon in the address bar), then click Try again.";
  if (name === "NotReadableError" || name === "AbortError")
    return "The camera is in use by another program or browser tab (Zoom, Teams, another copy of this page...). Close it, then click Try again.";
  if (name === "NotFoundError") return "No camera found. Connect the webcam, then click Try again.";
  return "The camera could not be started. Click Try again.";
}

/** Live facial photo from the desk webcam (legacy webcam-capture.js). */
export function WebcamCapture({ onCapture }: { onCapture: (dataUrl: string | null) => void }) {
  const video = useRef<HTMLVideoElement>(null);
  const [photo, setPhoto] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [ready, setReady] = useState(false);
  const [cameras, setCameras] = useState<MediaDeviceInfo[]>([]);
  const [cameraId, setCameraId] = useState(savedCamera);
  const [activeId, setActiveId] = useState("");
  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    const el = video.current;
    const media = navigator.mediaDevices;
    let cancelled = false;
    let stream: MediaStream | null = null;
    const size = { width: { ideal: 640 }, height: { ideal: 800 } };

    (media?.getUserMedia
      ? media.getUserMedia({ video: cameraId ? { ...size, deviceId: { exact: cameraId } } : { ...size, facingMode: "user" } })
      : Promise.reject(new DOMException("No camera API", "NotFoundError"))
    )
      .then(async (s) => {
        // Stopped (or switched camera) before the camera opened: release it, or it stays on.
        if (cancelled) return s.getTracks().forEach((t) => t.stop());
        stream = s;
        if (el) el.srcObject = s;
        const active = s.getVideoTracks()[0]?.getSettings().deviceId ?? "";
        const all = (await media.enumerateDevices()).filter((d) => d.kind === "videoinput");
        if (cancelled) return;
        setCameras(all);
        setActiveId(active);
        setError(null);
        setReady(true);
        // No camera chosen yet and the browser picked a software camera: use a real one.
        const current = all.find((d) => d.deviceId === active);
        const real = all.find((d) => !VIRTUAL_CAMERA.test(d.label));
        if (!cameraId && current && VIRTUAL_CAMERA.test(current.label) && real) setCameraId(real.deviceId);
      })
      .catch((e) => {
        if (cancelled) return;
        setReady(false);
        // The remembered camera was unplugged: fall back to the default one.
        if (cameraId && e instanceof DOMException && (e.name === "OverconstrainedError" || e.name === "NotFoundError")) {
          rememberCamera("");
          setCameraId("");
          return;
        }
        setError(cameraError(e));
      });

    return () => {
      cancelled = true;
      stream?.getTracks().forEach((t) => t.stop());
      if (el) el.srcObject = null;
    };
  }, [cameraId, attempt]);

  function chooseCamera(id: string) {
    rememberCamera(id);
    setReady(false);
    setCameraId(id);
  }

  function capture() {
    const v = video.current;
    if (!v || !v.videoWidth) return; // no frame yet
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
      {error && (
        <div className="flex flex-wrap items-center gap-3">
          <p className="text-sm text-red-700">{error}</p>
          <Button type="button" variant="secondary" onClick={() => setAttempt((n) => n + 1)}>Try again</Button>
        </div>
      )}
      {cameras.length > 1 && (
        <label className="flex max-w-md items-center gap-2 text-sm text-slate-700">
          <span className="shrink-0">Camera</span>
          <Select value={activeId} onChange={(e) => chooseCamera(e.target.value)}>
            {cameras.map((c, i) => <option key={c.deviceId} value={c.deviceId}>{c.label || `Camera ${i + 1}`}</option>)}
          </Select>
        </label>
      )}
      <div className="flex gap-4">
        <video ref={video} autoPlay playsInline muted className="h-60 w-48 rounded-lg bg-slate-900 object-cover" />
        {photo && (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={photo} alt="Captured" className="h-60 w-48 rounded-lg border-2 border-nis-green object-cover" />
        )}
      </div>
      <div className="flex gap-2">
        <Button type="button" onClick={capture} disabled={!ready}>{photo ? "Retake photo" : "Capture photo"}</Button>
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
    // No background fill: the saved PNG stays transparent so only the ink shows on the card.
    const ctx = canvas.current!.getContext("2d")!;
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
    canvas.current!.getContext("2d")!.clearRect(0, 0, canvas.current!.width, canvas.current!.height);
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
