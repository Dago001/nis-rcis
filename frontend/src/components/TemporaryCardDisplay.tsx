import { withBase } from "@/lib/base-path";
import s from "./TemporaryCardDisplay.module.css";

export type TemporaryCardInfo = {
  id: number;
  temporary_card_number: string;
  application_number: string;
  reference_number: string;
  applicant_name: string;
  surname: string;
  forenames: string;
  nationality: string;
  date_of_birth: string;
  dob_card?: string;
  sex: string;
  passport_number: string;
  category: string;
  card_type?: string;
  domicile: string;
  domicile_state?: string | null;
  domicile_lga?: string | null;
  domicile_full?: string;
  state_code?: string;
  profession?: string;
  height?: string;
  enrollment_center: string;
  enrollment_state: string;
  biometrics_captured_at: string | null;
  issue_date: string;
  expiry_date: string;
  issue_date_card?: string;
  expiry_date_card?: string;
  validity_days: number;
  validity_period: string;
  validity_heading: string;
  valid_from: string;
  valid_until: string;
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

/** Shrinks a single-line value so long names still fit their box (sizes in design px). */
function fit(text: string, base: number, width: number, lines = 1): number {
  const needed = (text || "").length * 0.72;
  return Math.max(9, Math.min(base, (width * lines * 0.95) / Math.max(needed, 1)));
}

/** Formats date into "01 NOV 2026" */
function formatCardDate(value?: string | null): string {
  if (!value) return "—";
  if (value.includes("/") && value.length === 10) {
    const [d, m, y] = value.split("/");
    const months = ["JAN", "FEB", "MAR", "APR", "MAY", "JUN", "JUL", "AUG", "SEP", "OCT", "NOV", "DEC"];
    const monthName = months[parseInt(m, 10) - 1] || m;
    return `${d.padStart(2, "0")} ${monthName} ${y}`;
  }
  const d = new Date(value.length === 10 ? `${value}T00:00:00` : value);
  if (Number.isNaN(d.getTime())) return value.toUpperCase();
  return `${String(d.getDate()).padStart(2, "0")} ${d.toLocaleString("en-GB", { month: "short" }).toUpperCase().slice(0, 3)} ${d.getFullYear()}`;
}

export function TemporaryCardFront({ card }: { card: TemporaryCardInfo }) {
  const surname = card.surname || "—";
  const forenames = card.forenames || "—";
  const nationality = card.nationality || "—";
  const sex = card.sex || "—";
  const dob = card.dob_card || formatCardDate(card.date_of_birth);
  const passport = card.passport_number || "—";
  const issuedOn = card.issue_date_card || formatCardDate(card.issue_date);
  const expiresOn = card.expiry_date_card || formatCardDate(card.expiry_date);
  const stateCode = card.state_code || "FC";

  return (
    <div className={s.slot}>
      <section className={s.card} aria-label="Temporary Residence Card Front">
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img className={s.templateBg} src={withBase("/card/card-front-template.jpg")} alt="" />

        {/* Prominent Identification Banner */}
        <div className={s.temporaryBannerFront}>
          Temporary Residence Card
        </div>

        {/* Cardholder Photograph */}
        <div className={s.photo}>
          {card.photo_url ? (
            /* eslint-disable-next-line @next/next/no-img-element */
            <img src={card.photo_url} alt="Holder photograph" />
          ) : (
            <div className="flex h-full w-full items-center justify-center text-[10px] text-slate-400">
              PHOTO
            </div>
          )}
        </div>

        {/* Cardholder Signature */}
        <div className={s.sig}>
          {card.signature_url && (
            /* eslint-disable-next-line @next/next/no-img-element */
            <img src={card.signature_url} alt="Holder signature" />
          )}
        </div>

        {/* Residence Card Number (top right) */}
        <div className={s.cardNo} style={{ fontSize: `${fit(card.temporary_card_number, 21, 170)}px` }}>
          {card.temporary_card_number}
        </div>

        {/* Dynamic Particulars */}
        <div className={s.valSurname} style={{ fontSize: `${fit(surname, 22, 440)}px` }}>
          {surname}
        </div>
        <div className={s.valForenames} style={{ fontSize: `${fit(forenames, 22, 440)}px` }}>
          {forenames}
        </div>
        <div className={s.valNationality} style={{ fontSize: `${fit(nationality, 17, 195)}px` }}>
          {nationality}
        </div>
        <div className={s.valSex}>
          {sex}
        </div>
        <div className={s.valDob}>
          {dob}
        </div>
        <div className={s.valPassport} style={{ fontSize: `${fit(passport, 17, 185)}px` }}>
          {passport}
        </div>
        <div className={s.valIssuedOn}>
          {issuedOn}
        </div>
        <div className={s.valExpiresOn}>
          {expiresOn}
        </div>
        <div className={s.valStateCode}>
          {stateCode}
        </div>

        {/* NIS Logo Watermark */}
        <div className={s.nisLogoWatermark}>
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={withBase("/images/nis-logo.png")} alt="" />
        </div>

        {/* Ghost Photo */}
        <div className={s.ghost}>
          {card.photo_url && (
            /* eslint-disable-next-line @next/next/no-img-element */
            <img src={card.photo_url} alt="" />
          )}
        </div>
      </section>
    </div>
  );
}

export function TemporaryCardBack({ card }: { card: TemporaryCardInfo }) {
  const issuedAt = card.enrollment_center || "NIS Headquarters, Abuja";
  const cardType = card.card_type || "TYPE 01 - RESIDENCE CARD";
  const domicile = card.domicile_full || card.domicile || "—";
  const profession = card.profession || "—";
  const height = card.height || "—";
  const issuedOn = card.issue_date_card || formatCardDate(card.issue_date);
  const expiresOn = card.expiry_date_card || formatCardDate(card.expiry_date);

  return (
    <div className={s.slot}>
      <section className={s.card} aria-label="Temporary Residence Card Back">
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img className={s.templateBg} src={withBase("/card/card-back-template.png")} alt="" />

        {/* Top Identification Banner */}
        <div className={s.temporaryBannerBack}>
          Temporary Residence Card
        </div>

        {/* Dynamic Verification QR Code */}
        <div className={s.qr}>
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={card.qr_svg} alt="Verification QR Code" />
        </div>

        {/* Particulars on Card Back */}
        <div className={s.valIssuedAt} style={{ fontSize: `${fit(issuedAt, 16, 205)}px` }}>
          {issuedAt}
        </div>
        <div className={s.valCardType}>
          TEMPORARY RESIDENCE
        </div>
        <div className={s.valDomicile} style={{ fontSize: `${fit(domicile, 13, 580, 2)}px` }}>
          {domicile}
        </div>
        <div className={s.valProfession} style={{ fontSize: `${fit(profession, 16, 205)}px` }}>
          {profession}
        </div>
        <div className={s.valHeight}>
          {height}
        </div>
        <div className={s.valBackIssuedOn}>
          {issuedOn}
        </div>
        <div className={s.valBackExpiresOn}>
          {expiresOn}
        </div>

        {/* Dedicated 30-Day Validity Block on Card Back replacing MRZ */}
        <div className={s.validityBlockBack}>
          <div className={s.validityLine1}>
            TEMPORARY RESIDENCE CARD &middot; VALIDITY: {card.validity_period}
          </div>
          <div className={s.validityLine2}>
            VALID FROM: {issuedOn} &nbsp;&vert;&nbsp; VALID UNTIL: {expiresOn}
          </div>
        </div>
      </section>
    </div>
  );
}

export function TemporaryCardDisplay({ card }: { card: TemporaryCardInfo }) {
  return (
    <div className="flex flex-col items-center justify-center gap-6 lg:flex-row lg:items-start lg:gap-8">
      <div className="flex flex-col items-center gap-2">
        <span className="text-xs font-bold uppercase tracking-wider text-slate-500">
          Card Front
        </span>
        <TemporaryCardFront card={card} />
      </div>
      <div className="flex flex-col items-center gap-2">
        <span className="text-xs font-bold uppercase tracking-wider text-slate-500">
          Card Back
        </span>
        <TemporaryCardBack card={card} />
      </div>
    </div>
  );
}
