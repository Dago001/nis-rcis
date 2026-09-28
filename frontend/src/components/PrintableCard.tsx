import { Fragment, type ReactNode } from "react";
import { createPortal } from "react-dom";
import { backHeaderPattern, bodyPattern, frontHeaderPattern } from "@/components/card-guilloche";
import { QrCode } from "@/components/QrCode";
import { td1Mrz } from "@/lib/mrz";
import type { Card } from "@/lib/types";
import s from "./PrintableCard.module.css";
import { withBase } from "@/lib/base-path";

export type PrintData = { data: Card; photo_url: string | null; signature_url: string | null; verification_url: string };

const MICROTEXT = "FEDERALREPUBLICOFNIGERIA·ECOWAS·CEDEAO·NIGERIAIMMIGRATIONSERVICE·".repeat(8);

/** "05 MAR 1990" */
function cardDate(value?: string | null): string {
  if (!value) return "—";
  const d = new Date(value.length === 10 ? `${value}T00:00:00` : value);
  if (Number.isNaN(d.getTime())) return value.toUpperCase();
  return `${String(d.getDate()).padStart(2, "0")} ${d.toLocaleString("en-GB", { month: "short" }).toUpperCase().slice(0, 3)} ${d.getFullYear()}`;
}

function height(value?: string | null): string {
  if (!value) return "—";
  const v = value.trim().toLowerCase();
  if (/[a-z]$/.test(v)) return v.replace(/\s*(cm|m)$/, " $1");
  return Number(v) < 3 ? `${v} m` : `${v} cm`;
}

/** Shrinks a single-line value so long names still fit their box (sizes are in design px). */
function fit(text: string, base: number, width: number, lines = 1): number {
  const needed = text.length * 0.72;
  return Math.max(9, Math.min(base, (width * lines * 0.95) / Math.max(needed, 1)));
}

function Field({ en, fr, children, className = "", valueClass = "", size }: { en: string; fr: string; children: ReactNode; className?: string; valueClass?: string; size?: number }) {
  return (
    <div className={`${s.f} ${className}`}>
      <div className={s.l}>{en} <i>/ {fr}</i></div>
      <div className={`${s.v} ${valueClass}`} style={size ? { fontSize: `${size}px` } : undefined}>{children}</div>
    </div>
  );
}

/* eslint-disable @next/next/no-img-element -- print layout needs plain <img> at fixed design sizes */

function Front({ p }: { p: PrintData }) {
  const c = p.data;
  return (
    <div className={s.slot}>
      <section className={s.card} aria-label="Card front">
        <div className={s.bg}>
          <img className={s.fill} src={bodyPattern} alt="" />
          <img className={s.watermark} src={withBase("/card/coat-of-arms.png")} alt="" />
        </div>
        <header className={s.hdr}>
          <img className={s.hw} src={frontHeaderPattern} alt="" />
          <img className={s.coa} src={withBase("/card/coat-of-arms.png")} alt="Coat of arms of Nigeria" />
          <div className={s.ttl}>
            <h1>FEDERAL REPUBLIC OF NIGERIA</h1>
            <div className={s.sub}>ECOWAS RESIDENCE CARD</div>
            <div className={s.fr}>CARTE DE RÉSIDENT CEDEAO</div>
          </div>
          <img className={s.eco} src={withBase("/card/ecowas-emblem.png")} alt="ECOWAS emblem" />
        </header>

        <div className={s.photo}>{p.photo_url && <img src={p.photo_url} alt="Holder photograph" />}</div>

        <div className={s.fields}>
          <Field en="Surname" fr="Nom" className={s.w} valueClass={s.big} size={fit(c.surname, 23, 400)}>{c.surname}</Field>
          <Field en="Given names" fr="Prénoms" className={s.w} valueClass={s.big} size={fit(c.forenames, 23, 400)}>{c.forenames}</Field>
          <div className={s.rule} />
          <Field en="Nationality" fr="Nationalité" size={fit(c.nationality, 18, 189)}>{c.nationality}</Field>
          <Field en="Sex" fr="Sexe">{c.sex}</Field>
          <Field en="Date of birth" fr="Né(e) le">{cardDate(c.date_of_birth)}</Field>
          <Field en="Passport no." fr="N° passeport" size={fit(c.passport_number, 18, 189)}>{c.passport_number}</Field>
          <Field en="Date of issue" fr="Délivré le">{cardDate(c.issued_on)}</Field>
          <Field en="Date of expiry" fr="Expire le" valueClass={s.red}>{cardDate(c.expires_on)}</Field>
          <Field en="Issuing state" fr="État">NIGERIA<small>NGA</small></Field>
          <Field en="Category" fr="Catégorie">ECOWAS CITIZEN</Field>
        </div>

        <div className={s.cno}>
          <div className={s.l}>Residence card no.</div>
          <div className={s.v}>{c.card_number}</div>
        </div>
        <div className={s.ghost}>{p.photo_url && <img src={p.photo_url} alt="" />}</div>
        <div className={s.holo}><img src={withBase("/card/coat-of-arms.png")} alt="" /></div>

        <div className={s.sig}>
          <div className={s.ink}>{p.signature_url && <img src={p.signature_url} alt="Holder's signature" />}</div>
          <div className={`${s.line} ${s.l}`}>Holder&apos;s signature <i>/ Signature</i></div>
        </div>

        <div className={s.micro}>{MICROTEXT}</div>
        <div className={s.flag} />
      </section>
    </div>
  );
}

function Back({ p }: { p: PrintData }) {
  const c = p.data;
  return (
    <div className={s.slot}>
      <section className={s.card} aria-label="Card back">
        <div className={s.bg}>
          <img className={s.fill} src={bodyPattern} alt="" />
          <img className={s.watermark} src={withBase("/card/coat-of-arms.png")} alt="" />
        </div>
        <header className={s.bhdr}>
          <img className={s.hw} src={backHeaderPattern} alt="" />
          <img className={s.coaSmall} src={withBase("/card/coat-of-arms.png")} alt="" />
          <div className={s.t}>NIGERIA IMMIGRATION SERVICE<i>SERVICE DE L&apos;IMMIGRATION DU NIGERIA</i></div>
        </header>

        <div className={s.qr}>
          <QrCode value={p.verification_url} size={152} />
          <p>SCAN TO VERIFY</p>
        </div>

        <div className={s.bf}>
          <Field en="Issuing authority" fr="Autorité" className={s.w}>NIGERIA IMMIGRATION SERVICE</Field>
          <Field en="Place of issue" fr="Lieu" size={fit(c.issued_at ?? "", 15.5, 199)}>{c.issued_at || "—"}</Field>
          <Field en="Card type" fr="Type">TD1 · IR</Field>
          <Field en="Address in Nigeria" fr="Adresse" className={s.w} valueClass={s.addr} size={fit(c.domicile, 15.5, 420, 2)}>{c.domicile}</Field>
          <Field en="Occupation" fr="Profession" size={fit(c.profession, 15.5, 199)}>{c.profession}</Field>
          <Field en="Height" fr="Taille">{height(c.height)}</Field>
        </div>

        <div className={s.chip}>
          <svg viewBox="0 0 92 70" aria-hidden="true">
            <g fill="none" stroke="rgba(90,60,10,.55)" strokeWidth="1.4">
              <rect x="30" y="18" width="32" height="34" rx="6" />
              <path d="M0 24h30M0 46h30M62 24h30M62 46h30M46 0v18M46 52v18" />
            </g>
          </svg>
        </div>
        <div className={s.nfc}>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden="true">
            <path d="M6 8.5a6 6 0 0 1 0 7M9.5 6a10 10 0 0 1 0 12M13 3.5a14 14 0 0 1 0 17" />
          </svg>
          ICAO eMRTD
        </div>

        <p className={s.note}>
          This card is the property of the <b>Federal Government of Nigeria</b> and must be surrendered on demand. It is valid only with the holder&apos;s
          national passport. If found, return to the nearest NIS office. <b>Verify at any NIS port of entry or with the NIS Verify app.</b>
        </p>

        <div className={s.mrz} aria-hidden="true">
          {td1Mrz({
            documentNumber: c.card_number,
            dateOfBirth: c.date_of_birth,
            sex: c.sex,
            dateOfExpiry: c.expires_on,
            nationality: c.nationality,
            surname: c.surname,
            givenNames: c.forenames,
          }).map((line) => <div key={line}>{line}</div>)}
        </div>
      </section>
    </div>
  );
}

/** On-screen preview of an ID-1 card (85.6 × 54 mm): front with the holder's particulars, back with the QR code and MRZ. */
export function PrintableCard({ p }: { p: PrintData }) {
  return (
    <div className="no-print flex flex-wrap gap-6">
      <Front p={p} />
      <Back p={p} />
    </div>
  );
}

/**
 * What actually prints: one CR80 page per side (front, back, front, back, …) for the Fargo HDP5000's
 * duplex driver. Rendered straight under <body> so the console layout's padding cannot shift the card.
 */
export function CardPrintPages({ cards }: { cards: PrintData[] }) {
  if (typeof document === "undefined") return null;
  return createPortal(
    <div className={`card-print ${s.pages}`}>
      {cards.map((p) => (
        <Fragment key={p.data.id}>
          <Front p={p} />
          <Back p={p} />
        </Fragment>
      ))}
    </div>,
    document.body,
  );
}

export type Stock = { received: number; printed: number; spoiled: number; remaining: number; low: boolean; threshold: number };
