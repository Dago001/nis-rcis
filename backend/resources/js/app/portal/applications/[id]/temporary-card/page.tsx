"use client";

import Image from "next/image";
import Link from "next/link";
import { useParams, useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState } from "react";
import { Alert, Button, Spinner } from "@/components/ui";
import { api } from "@/lib/api-client";
import { withBase } from "@/lib/base-path";
import { useI18n } from "@/components/I18nProvider";

export type TemporaryCardData = {
  id: number;
  temporary_card_number: string;
  application_number: string;
  reference_number: string;
  applicant_name: string;
  surname: string;
  forenames: string;
  nationality: string;
  date_of_birth: string;
  sex: string;
  passport_number: string;
  category: string;
  domicile: string;
  domicile_state?: string | null;
  domicile_lga?: string | null;
  enrollment_center: string;
  enrollment_state: string;
  biometrics_captured_at: string | null;
  issue_date: string;
  expiry_date: string;
  validity_period: string;
  validity_note: string;
  status: string;
  status_label: string;
  photo_url: string | null;
  signature_url: string | null;
  qr_payload: string;
  qr_svg: string;
  is_development_preview: boolean;
  template_status: string;
  disclaimer: string;
  is_dependent: boolean;
  principal_application_number?: string | null;
};

function TemporaryCardView() {
  const { t } = useI18n();
  const params = useParams();
  const searchParams = useSearchParams();
  const id = params?.id ? String(params.id) : "";
  const fromStaff = searchParams.get("from_staff") === "1";
  const returnTo = searchParams.get("return_to");

  const [card, setCard] = useState<TemporaryCardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!id) return;
    const isStaff = fromStaff || (returnTo && returnTo.startsWith("/staff/"));
    const primaryScope = isStaff ? "staff" : "applicant";
    const fallbackScope = isStaff ? "applicant" : "staff";

    setLoading(true);
    api<{ data: TemporaryCardData }>(primaryScope, `applications/${id}/temporary-card`)
      .then((res) => {
        setCard(res.data);
        setLoading(false);
      })
      .catch((err1) => {
        api<{ data: TemporaryCardData }>(fallbackScope, `applications/${id}/temporary-card`)
          .then((res) => {
            setCard(res.data);
            setLoading(false);
          })
          .catch((err2) => {
            setError(err2.message || err1.message || t("Failed to load temporary residence card"));
            setLoading(false);
          });
      });
  }, [id, fromStaff, returnTo, t]);

  const isAdministrative = fromStaff || Boolean(returnTo && returnTo.startsWith("/staff/"));
  const backHref = isAdministrative
    ? (returnTo && returnTo.startsWith("/staff/") ? returnTo : (id ? `/staff/applications/${id}` : "/staff/applications"))
    : (id ? `/portal/applications/${id}` : "/portal");
  const backLabel = isAdministrative ? `← ${t("Back")}` : `← ${t("Back to Application")}`;

  if (loading) {
    return (
      <div className="flex min-h-[400px] items-center justify-center">
        <Spinner />
      </div>
    );
  }

  if (error || !card) {
    return (
      <div className="mx-auto max-w-2xl py-8 space-y-4">
        <Alert tone="danger">
          <div className="font-semibold">{t("Temporary Residence Card Unavailable")}</div>
          <div className="mt-1 text-sm">{error || t("This application is not yet eligible for a temporary residence card.")}</div>
        </Alert>
        <Link
          href={backHref}
          className="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
        >
          {backLabel}
        </Link>
      </div>
    );
  }

  const pdfUrl = `/portal/applications/${card.id}/temporary-card/pdf`;

  return (
    <div className="space-y-6 pb-12">
      {/* Top action controls (excluded from print) */}
      <div className="no-print mx-auto flex max-w-[210mm] flex-wrap items-center justify-between gap-3 bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
        <Link
          href={backHref}
          className="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 hover:text-slate-900 transition"
        >
          {backLabel}
        </Link>

        <div className="flex flex-wrap items-center gap-2.5">
          <a
            href={pdfUrl}
            target="_blank"
            rel="noopener noreferrer"
            download={`NIS-temporary-card-${card.application_number}.pdf`}
            className="inline-flex items-center gap-2 rounded-lg border border-nis-primary/30 bg-emerald-50 px-4 py-2 text-sm font-semibold text-nis-primary hover:bg-emerald-100 transition"
          >
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            {t("Download Temporary Card")}
          </a>
          <Button onClick={() => window.print()} className="inline-flex items-center gap-2 !px-4 !py-2">
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            {t("Print Temporary Card")}
          </Button>
        </div>
      </div>

      {/* Development Preview Watermark Notice */}
      <div className="no-print mx-auto max-w-[210mm] rounded-lg border border-amber-300 bg-amber-50 px-4 py-2.5 text-center text-xs font-semibold text-amber-800 shadow-2xs">
        ★ {card.disclaimer} ★
      </div>

      {/* Main Printable Document Layout */}
      <article className="mx-auto max-w-[210mm] min-w-[320px] rounded-xl border-2 border-emerald-700 bg-white p-6 sm:p-8 shadow-sm print:m-0 print:max-w-none print:border-none print:p-0 print:shadow-none text-slate-900">
        {/* Document Header */}
        <div className="border-b-2 border-emerald-700 pb-4 text-center">
          <div className="flex justify-center pb-2">
            <Image
              src={withBase("/images/nis-logo.png")}
              alt="Nigeria Immigration Service"
              width={64}
              height={64}
              priority
            />
          </div>
          <div className="text-xs font-bold uppercase tracking-wider text-slate-800">
            {t("Federal Republic of Nigeria")}
          </div>
          <div className="text-base font-extrabold uppercase tracking-wide text-emerald-800 sm:text-lg">
            {t("Nigeria Immigration Service")}
          </div>
          <div className="text-xs text-slate-600">
            {t("Directorate of Visa and Residency · ECOWAS Residence Card System")}
          </div>

          <div className="mt-3 rounded-lg border border-emerald-200 bg-emerald-50/70 py-2 px-4 text-center">
            <div className="text-sm font-bold uppercase tracking-wide text-emerald-900 sm:text-base">
              {t("Temporary Residence Card")} / Carte de Séjour Temporaire
            </div>
            <div className="text-xs italic text-emerald-700">
              {t("Official Interim Proof of Biometrics Completion & Residency")}
            </div>
          </div>
        </div>

        {/* Particulars Grid */}
        <div className="mt-6 grid grid-cols-1 gap-6 md:grid-cols-[140px_1fr_140px] items-start">
          {/* Photo & Signature Column */}
          <div className="flex flex-col items-center">
            <div className="h-[170px] w-[136px] overflow-hidden rounded-md border border-slate-300 bg-slate-100 shadow-2xs flex items-center justify-center">
              {card.photo_url ? (
                /* eslint-disable-next-line @next/next/no-img-element */
                <img
                  src={card.photo_url}
                  alt={card.applicant_name}
                  className="h-full w-full object-cover"
                />
              ) : (
                <div className="text-center text-xs text-slate-400 p-2">
                  {t("Photo Captured")}
                </div>
              )}
            </div>

            {card.signature_url && (
              <div className="mt-3 w-[136px]">
                <div className="h-12 w-full overflow-hidden rounded-md border border-dashed border-slate-300 bg-slate-50 flex items-center justify-center p-1">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img
                    src={card.signature_url}
                    alt="Digital Signature"
                    className="max-h-full max-w-full object-contain"
                  />
                </div>
                <div className="mt-1 text-center text-[10px] font-medium uppercase tracking-wider text-slate-500">
                  {t("Digital Signature")}
                </div>
              </div>
            )}
          </div>

          {/* Details Table */}
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <tbody className="divide-y divide-slate-100">
                <tr>
                  <td className="py-2 pr-4 font-semibold uppercase text-slate-500 w-[35%]">{t("Document Ref")}</td>
                  <td className="py-2 font-mono font-bold text-emerald-800">{card.temporary_card_number}</td>
                </tr>
                <tr>
                  <td className="py-2 pr-4 font-semibold uppercase text-slate-500">{t("Application No")}</td>
                  <td className="py-2 font-mono font-medium text-slate-900">{card.application_number}</td>
                </tr>
                <tr>
                  <td className="py-2 pr-4 font-semibold uppercase text-slate-500">{t("Reference No")}</td>
                  <td className="py-2 font-mono font-medium text-slate-900">{card.reference_number}</td>
                </tr>
                <tr>
                  <td className="py-2 pr-4 font-semibold uppercase text-slate-500">{t("Full Name")}</td>
                  <td className="py-2 text-sm font-bold text-slate-900">{card.applicant_name}</td>
                </tr>
                <tr>
                  <td className="py-2 pr-4 font-semibold uppercase text-slate-500">{t("Nationality")}</td>
                  <td className="py-2 font-medium text-slate-900">{card.nationality}</td>
                </tr>
                <tr>
                  <td className="py-2 pr-4 font-semibold uppercase text-slate-500">{t("Passport No")}</td>
                  <td className="py-2 font-mono font-semibold text-slate-900">{card.passport_number}</td>
                </tr>
                <tr>
                  <td className="py-2 pr-4 font-semibold uppercase text-slate-500">{t("Category")}</td>
                  <td className="py-2 font-medium text-slate-900">{card.category}</td>
                </tr>
                <tr>
                  <td className="py-2 pr-4 font-semibold uppercase text-slate-500">{t("Date of Birth")}</td>
                  <td className="py-2 font-medium text-slate-900">{card.date_of_birth} ({t("Sex")}: {card.sex})</td>
                </tr>
                <tr>
                  <td className="py-2 pr-4 font-semibold uppercase text-slate-500">{t("Domicile")}</td>
                  <td className="py-2 font-medium text-slate-900">
                    {card.domicile}{card.domicile_state ? `, ${card.domicile_state}` : ""}
                  </td>
                </tr>
                <tr>
                  <td className="py-2 pr-4 font-semibold uppercase text-slate-500">{t("Enrollment Office")}</td>
                  <td className="py-2 font-medium text-slate-900">{card.enrollment_center}</td>
                </tr>
                <tr>
                  <td className="py-2 pr-4 font-semibold uppercase text-slate-500">{t("Date of Capture")}</td>
                  <td className="py-2 font-medium text-slate-900">{card.issue_date}</td>
                </tr>
                <tr>
                  <td className="py-2 pr-4 font-semibold uppercase text-slate-500">{t("Valid Until")}</td>
                  <td className="py-2 font-semibold text-emerald-800">
                    {card.expiry_date} ({card.validity_period})
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          {/* QR Code & Status Badge Column */}
          <div className="flex flex-col items-center text-center">
            <div className="rounded-lg border border-slate-300 bg-white p-2 shadow-2xs">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img
                src={card.qr_svg}
                alt="Verification QR Code"
                className="h-28 w-28"
              />
            </div>
            <div className="mt-2.5 inline-flex items-center rounded-full bg-emerald-100 border border-emerald-300 px-2.5 py-0.5 text-[10px] font-bold uppercase text-emerald-800">
              ✓ {t("Biometrics Completed")}
            </div>
            <p className="mt-2 text-[10px] text-slate-500 leading-tight">
              {t("Scan barcode with official NIS terminal to verify authenticity.")}
            </p>
          </div>
        </div>

        {/* Regulatory & Transition Notice */}
        <div className="mt-6 rounded-lg border border-slate-200 bg-slate-50/70 p-4 text-xs text-slate-600 space-y-1.5">
          <div className="font-bold uppercase tracking-wider text-slate-800">
            {t("Important Legal & Regulatory Notice")}:
          </div>
          <p>
            1. <strong>{t("Interim Legal Effect")}:</strong> {t("This document certifies that the holder has completed biometrics enrollment and is authorized to reside in Nigeria pending production and collection of the permanent ECOWAS Residence Card.")}
          </p>
          <p>
            2. <strong>{t("Validity Policy")}:</strong> {card.validity_note}
          </p>
          <p>
            3. <strong>{t("Permanent Card Collection")}:</strong> {t("Upon notification that your permanent residence card is ready, present your international passport and this temporary card at your designated enrollment center to collect your permanent card.")}
          </p>
        </div>

        {/* Footer */}
        <div className="mt-8 border-t border-slate-200 pt-4 text-center text-[10px] text-slate-400">
          {t("Generated electronically by the Nigeria Immigration Service RCIS Portal.")} · {t("Official Template Preview")} · {t("All Rights Reserved.")}
        </div>
      </article>
    </div>
  );
}

export default function TemporaryCardPage() {
  return (
    <Suspense fallback={<Spinner />}>
      <TemporaryCardView />
    </Suspense>
  );
}
