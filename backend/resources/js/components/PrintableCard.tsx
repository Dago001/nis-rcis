import { Fragment } from "react";
import { createPortal } from "react-dom";
import { QrCode } from "@/components/QrCode";
import { td2Mrz } from "@/lib/mrz";
import type { Card } from "@/lib/types";
import s from "./PrintableCard.module.css";
import { withBase } from "@/lib/base-path";

export type PrintData = { data: Card; photo_url: string | null; signature_url: string | null; verification_url: string };

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

function stateCode(c: Card): string {
  const s = (c.domicile_state || "").trim().toUpperCase();
  if (!s) return "FC";
  const map: Record<string, string> = {
    ABUJA: "FC", "FEDERAL CAPITAL TERRITORY": "FC", FCT: "FC",
    ABIA: "AB", ADAMAWA: "AD", "AKWA IBOM": "AK", ANAMBRA: "AN",
    BAUCHI: "BA", BAYELSA: "BY", BENUE: "BN", BORNO: "BO",
    "CROSS RIVER": "CR", DELTA: "DE", EBONYI: "EB", EDO: "ED",
    EKITI: "EK", ENUGU: "EN", GOMBE: "GO", IMO: "IM",
    JIGAWA: "JI", KADUNA: "KD", KANO: "KN", KATSINA: "KT",
    KEBBI: "KB", KOGI: "KO", KWARA: "KW", LAGOS: "LA",
    NASARAWA: "NA", NIGER: "NI", OGUN: "OG", ONDO: "OD",
    OSUN: "OS", OYO: "OY", PLATEAU: "PL", RIVERS: "RV",
    SOKOTO: "SO", TARABA: "TA", YOBE: "YO", ZAMFARA: "ZA",
  };
  return map[s] || (s.length === 2 ? s : s.slice(0, 2));
}

function cardType(c: Card): string {
  const cat = (c.category || "").toUpperCase();
  if (cat === "BUSINESS") return "TYPE 02 - BUSINESS RESIDENCE";
  if (cat === "STUDENT") return "TYPE 03 - STUDENT RESIDENCE";
  return "TYPE 01 - RESIDENCE CARD";
}

function fullDomicile(c: Card): string {
  const parts = [
    c.domicile?.trim(),
    c.domicile_lga?.trim() ? `LGA: ${c.domicile_lga.trim()}` : null,
    c.domicile_state?.trim() ? `${c.domicile_state.trim()} STATE` : null,
  ].filter(Boolean);
  return parts.join(", ") || "—";
}

/** Shrinks a single-line value so long names still fit their box (sizes are in design px). */
function fit(text: string, base: number, width: number, lines = 1): number {
  const needed = (text || "").length * 0.72;
  return Math.max(9, Math.min(base, (width * lines * 0.95) / Math.max(needed, 1)));
}

/* eslint-disable @next/next/no-img-element -- print layout needs plain <img> at fixed design sizes */

function Front({ p }: { p: PrintData }) {
  const c = p.data;
  return (
    <div className={s.slot}>
      <section className={s.card} aria-label="Card front">
        <img className={s.templateBg} src={withBase("/card/card-front-template.jpg")} alt="" />

        {/* Cardholder Photograph */}
        <div className={s.photo}>
          {p.photo_url && <img src={p.photo_url} alt="Holder photograph" />}
        </div>

        {/* Cardholder Signature */}
        <div className={s.sig}>
          {p.signature_url && <img src={p.signature_url} alt="Holder signature" />}
        </div>

        {/* Residence Card Number (top right) */}
        <div className={s.cardNo} style={{ fontSize: `${fit(c.card_number, 21, 160)}px` }}>
          {c.card_number}
        </div>

        {/* Dynamic Particulars */}
        <div className={s.valSurname} style={{ fontSize: `${fit(c.surname, 22, 440)}px` }}>
          {c.surname}
        </div>
        <div className={s.valForenames} style={{ fontSize: `${fit(c.forenames, 22, 440)}px` }}>
          {c.forenames}
        </div>
        <div className={s.valNationality} style={{ fontSize: `${fit(c.nationality, 17, 195)}px` }}>
          {c.nationality}
        </div>
        <div className={s.valSex}>
          {c.sex}
        </div>
        <div className={s.valDob}>
          {cardDate(c.date_of_birth)}
        </div>
        <div className={s.valPassport} style={{ fontSize: `${fit(c.passport_number, 17, 185)}px` }}>
          {c.passport_number}
        </div>
        <div className={s.valIssuedOn}>
          {cardDate(c.issued_on)}
        </div>
        <div className={s.valExpiresOn}>
          {cardDate(c.expires_on)}
        </div>
        <div className={s.valStateCode}>
          {stateCode(c)}
        </div>

        {/* Fainted NIS Logo watermark behind/replacing ICAO chip */}
        <div className={s.nisLogoWatermark}>
          <img src={withBase("/images/nis-logo.png")} alt="" />
        </div>

        {/* Ghost photo */}
        <div className={s.ghost}>
          {p.photo_url && <img src={p.photo_url} alt="" />}
        </div>
      </section>
    </div>
  );
}

function Back({ p }: { p: PrintData }) {
  const c = p.data;
  return (
    <div className={s.slot}>
      <section className={s.card} aria-label="Card back">
        <img className={s.templateBg} src={withBase("/card/card-back-template.png")} alt="" />

        {/* Dynamic Verification QR Code */}
        <div className={s.qr}>
          <QrCode value={p.verification_url} size={152} />
        </div>

        {/* Particulars on Card Back */}
        <div className={s.valIssuedAt} style={{ fontSize: `${fit(c.issued_at || "NIS HEADQUARTERS, ABUJA", 16, 205)}px` }}>
          {c.issued_at || "NIS HEADQUARTERS, ABUJA"}
        </div>
        <div className={s.valCardType}>
          {cardType(c)}
        </div>
        <div className={s.valDomicile} style={{ fontSize: `${fit(fullDomicile(c), 13, 580, 2)}px` }}>
          {fullDomicile(c)}
        </div>
        <div className={s.valProfession} style={{ fontSize: `${fit(c.profession || "—", 16, 205)}px` }}>
          {c.profession || "—"}
        </div>
        <div className={s.valHeight}>
          {height(c.height)}
        </div>
        <div className={s.valBackIssuedOn}>
          {cardDate(c.issued_on)}
        </div>
        <div className={s.valBackExpiresOn}>
          {cardDate(c.expires_on)}
        </div>

        {/* ICAO 9303 TD2 2-line Machine Readable Zone */}
        <div className={s.mrz} aria-hidden="true">
          {td2Mrz({
            documentNumber: c.card_number,
            dateOfBirth: c.date_of_birth,
            sex: c.sex,
            dateOfExpiry: c.expires_on,
            nationality: c.nationality,
            surname: c.surname,
            givenNames: c.forenames,
          }).map((line, idx) => <div key={idx}>{line}</div>)}
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
