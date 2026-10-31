"use client";

import Image from "next/image";
import Link from "next/link";
import { useParams, useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState } from "react";
import { Alert, Button, Spinner } from "@/components/ui";
import { api } from "@/lib/api-client";
import { withBase } from "@/lib/base-path";
import { useI18n } from "@/components/I18nProvider";
import {
  TemporaryCardDisplay,
  type TemporaryCardInfo,
} from "@/components/TemporaryCardDisplay";

function TemporaryCardView() {
  const { t } = useI18n();
  const params = useParams();
  const searchParams = useSearchParams();
  const id = params?.id ? String(params.id) : "";
  const fromStaff = searchParams.get("from_staff") === "1";
  const returnTo = searchParams.get("return_to");

  const [card, setCard] = useState<TemporaryCardInfo | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!id) return;
    const isStaff = fromStaff || (returnTo && returnTo.startsWith("/staff/"));
    const primaryScope = isStaff ? "staff" : "applicant";
    const fallbackScope = isStaff ? "applicant" : "staff";

    setLoading(true);
    api<{ data: TemporaryCardInfo }>(primaryScope, `applications/${id}/temporary-card`)
      .then((res) => {
        setCard(res.data);
        setLoading(false);
      })
      .catch((err1) => {
        api<{ data: TemporaryCardInfo }>(fallbackScope, `applications/${id}/temporary-card`)
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
            className="inline-flex items-center gap-2 rounded-lg border border-emerald-700/30 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-100 transition"
          >
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            {t("Download Temporary Card (PDF)")}
          </a>
          <Button onClick={() => window.print()} className="inline-flex items-center gap-2 !px-4 !py-2">
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            {t("Print Temporary Card")}
          </Button>
        </div>
      </div>

      {/* Main Printable A4 Document Layout */}
      <article className="mx-auto max-w-[210mm] min-w-[320px] rounded-xl border border-slate-300 bg-white p-6 sm:p-8 shadow-sm print:m-0 print:max-w-none print:border-none print:p-0 print:shadow-none text-slate-900">
        {/* Document Header */}
        <div className="border-b-2 border-emerald-800 pb-4 text-center">
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
          <div className="text-base font-extrabold uppercase tracking-wide text-emerald-900 sm:text-lg">
            {t("Nigeria Immigration Service")}
          </div>
          <div className="text-xs text-slate-600">
            {t("Directorate of Visa and Residency · ECOWAS Residence Card System")}
          </div>
        </div>

        {/* Prominent 30-Day Validity Banner */}
        <div className="mt-4 rounded-xl border-2 border-emerald-800 bg-emerald-50/80 p-3 sm:p-4 text-center shadow-xs">
          <div className="text-xs sm:text-sm font-extrabold uppercase tracking-wider text-emerald-950">
            {t("Temporary Residence Card")} &middot; {t("Interim Residence Credential")}
          </div>
          <div className="mt-1.5 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-xs sm:text-sm font-bold text-slate-800">
            <span className="text-emerald-900">
              VALIDITY: {card.validity_period}
            </span>
            <span className="text-slate-400 hidden sm:inline">&vert;</span>
            <span>
              VALID FROM: <strong className="text-slate-900">{card.valid_from}</strong>
            </span>
            <span className="text-slate-400 hidden sm:inline">&vert;</span>
            <span>
              VALID UNTIL: <strong className="text-red-700">{card.valid_until}</strong>
            </span>
          </div>
        </div>

        {/* Card Front and Back matching Main Residence Card design */}
        <div className="mt-6 flex flex-col items-center justify-center">
          <TemporaryCardDisplay card={card} />
        </div>

        {/* Particulars Summary Table */}
        <div className="mt-8 overflow-x-auto rounded-lg border border-slate-200">
          <table className="w-full text-left text-xs">
            <tbody className="divide-y divide-slate-100">
              <tr className="bg-slate-50/60">
                <td className="py-2.5 px-3 font-semibold uppercase text-slate-500 w-[25%]">{t("Document Ref")}</td>
                <td className="py-2.5 px-3 font-mono font-bold text-emerald-900 w-[25%]">{card.temporary_card_number}</td>
                <td className="py-2.5 px-3 font-semibold uppercase text-slate-500 w-[25%]">{t("Application No")}</td>
                <td className="py-2.5 px-3 font-mono font-medium text-slate-900 w-[25%]">{card.application_number}</td>
              </tr>
              <tr>
                <td className="py-2.5 px-3 font-semibold uppercase text-slate-500">{t("Full Name")}</td>
                <td className="py-2.5 px-3 text-sm font-bold text-slate-900">{card.applicant_name}</td>
                <td className="py-2.5 px-3 font-semibold uppercase text-slate-500">{t("Nationality")}</td>
                <td className="py-2.5 px-3 font-medium text-slate-900">{card.nationality}</td>
              </tr>
              <tr className="bg-slate-50/60">
                <td className="py-2.5 px-3 font-semibold uppercase text-slate-500">{t("Passport No")}</td>
                <td className="py-2.5 px-3 font-mono font-semibold text-slate-900">{card.passport_number}</td>
                <td className="py-2.5 px-3 font-semibold uppercase text-slate-500">{t("Date of Birth")}</td>
                <td className="py-2.5 px-3 font-medium text-slate-900">{card.date_of_birth} ({t("Sex")}: {card.sex})</td>
              </tr>
              <tr>
                <td className="py-2.5 px-3 font-semibold uppercase text-slate-500">{t("Enrollment Center")}</td>
                <td className="py-2.5 px-3 font-medium text-slate-900">{card.enrollment_center}</td>
                <td className="py-2.5 px-3 font-semibold uppercase text-slate-500">{t("Biometrics Captured")}</td>
                <td className="py-2.5 px-3 font-medium text-slate-900">{card.valid_from}</td>
              </tr>
              <tr className="bg-slate-50/60">
                <td className="py-2.5 px-3 font-semibold uppercase text-slate-500">{t("Validity Period")}</td>
                <td className="py-2.5 px-3 font-bold text-emerald-900">VALIDITY: {card.validity_period}</td>
                <td className="py-2.5 px-3 font-semibold uppercase text-slate-500">{t("Valid Until")}</td>
                <td className="py-2.5 px-3 font-bold text-red-700">{card.valid_until}</td>
              </tr>
            </tbody>
          </table>
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
            2. <strong>{t("Validity Policy")}:</strong> VALIDITY: {card.validity_period} &mdash; Valid from {card.valid_from} until {card.valid_until} (30 calendar days from confirmed biometrics completion date). Reprinting does not extend or reset validity.
          </p>
          <p>
            3. <strong>{t("Permanent Card Collection")}:</strong> {t("Upon notification that your permanent residence card is ready, present your international passport and this temporary card at your designated enrollment center to collect your permanent card.")}
          </p>
        </div>

        {/* Footer */}
        <div className="mt-8 border-t border-slate-200 pt-4 text-center text-[10px] text-slate-400">
          {t("Generated electronically by the Nigeria Immigration Service RCIS Portal.")} &middot; {t("Official Temporary Residence Credential")} &middot; {t("All Rights Reserved.")}
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
