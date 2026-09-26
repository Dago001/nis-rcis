"use client";

import { useParams } from "next/navigation";
import { useEffect, useState } from "react";
import { QrCode } from "@/components/QrCode";
import { Alert, Button, Spinner } from "@/components/ui";
import { api } from "@/lib/api-client";
import { nisDate } from "@/lib/format";
import type { Card } from "@/lib/types";

type PrintData = { data: Card; photo_url: string | null; signature_url: string | null; verification_url: string };

/** ID-1 card layout (85.6 × 54 mm) with QR verification (legacy print-idcard.php). */
export default function PrintCardPage() {
  const { id } = useParams<{ id: string }>();
  const [p, setP] = useState<PrintData | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api<PrintData>("staff", `cards/${id}/print`).then(setP).catch((e) => setError(e.message));
  }, [id]);

  if (error) return <Alert tone="danger">{error}</Alert>;
  if (!p) return <Spinner />;
  const c = p.data;

  const face = "relative overflow-hidden rounded-[3mm] border border-slate-300 bg-gradient-to-br from-nis-green-light via-white to-nis-gold-light text-[2.3mm] leading-tight";

  return (
    <div className="space-y-6">
      <div className="no-print flex justify-between">
        <p className="text-sm text-slate-600">Print at 100% scale on CR80 card stock.</p>
        <Button onClick={() => window.print()}>Print</Button>
      </div>
      <div className="flex flex-wrap gap-6">
        <div className={face} style={{ width: "85.6mm", height: "54mm" }}>
          <div className="flex items-center gap-[1.5mm] bg-nis-green-dark px-[2mm] py-[0.8mm] text-white">
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src="/images/nigeria-coat-of-arms.png" alt="Coat of arms of Nigeria" style={{ height: "8mm", width: "auto" }} />
            <div className="min-w-0 flex-1">
              <div className="text-[2.4mm] font-bold">FEDERAL REPUBLIC OF NIGERIA</div>
              <div className="text-[1.9mm]">NIGERIA IMMIGRATION SERVICE · RESIDENCE CARD</div>
            </div>
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src="/images/ecowas-logo.png" alt="ECOWAS" style={{ height: "8mm", width: "8mm" }} />
          </div>
          <div className="flex gap-[2mm] p-[2mm]">
            {p.photo_url && (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={p.photo_url} alt="" style={{ width: "20mm", height: "25mm" }} className="border object-cover" />
            )}
            <div className="space-y-[0.6mm]">
              <div><span className="text-slate-500">SURNAME </span><b>{c.surname}</b></div>
              <div><span className="text-slate-500">NAMES </span><b>{c.forenames}</b></div>
              <div><span className="text-slate-500">NATIONALITY </span><b>{c.nationality}</b></div>
              <div><span className="text-slate-500">SEX </span><b>{c.sex}</b> <span className="text-slate-500">DOB </span><b>{nisDate(c.date_of_birth)}</b></div>
              <div><span className="text-slate-500">PASSPORT </span><b>{c.passport_number}</b></div>
              <div><span className="text-slate-500">ISSUED </span><b>{nisDate(c.issued_on)}</b></div>
              <div><span className="text-slate-500">EXPIRES </span><b className="text-red-700">{nisDate(c.expires_on)}</b></div>
            </div>
          </div>
          <div className="absolute bottom-[1.5mm] left-[2mm] text-[2.8mm] font-bold tracking-wider">No. {c.card_number}</div>
          {p.signature_url && (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={p.signature_url} alt="" className="absolute bottom-[1mm] right-[2mm] object-contain" style={{ width: "22mm", height: "6mm" }} />
          )}
        </div>

        <div className={face} style={{ width: "85.6mm", height: "54mm" }}>
          <div className="flex h-full gap-[2mm] p-[3mm]">
            <div className="flex-1 space-y-[0.8mm]">
              <div className="font-bold">BOOKLET {c.booklet_number}</div>
              <div><span className="text-slate-500">PROFESSION </span>{c.profession}</div>
              <div><span className="text-slate-500">ADDRESS </span>{c.domicile.slice(0, 80)}</div>
              <div><span className="text-slate-500">EMERGENCY </span>{c.emergency_contact_name} {c.emergency_contact_phone}</div>
              <div><span className="text-slate-500">ISSUED AT </span>{c.issued_at}</div>
              <div className="pt-[1mm] text-[1.8mm] text-slate-600">This card remains the property of the Federal Government of Nigeria. If found, return to the nearest NIS office. Verify by scanning the QR code.</div>
            </div>
            <div className="flex flex-col items-center justify-center">
              <QrCode value={p.verification_url} size={80} />
              <div className="mt-[0.5mm] text-[1.6mm]">SCAN TO VERIFY</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
