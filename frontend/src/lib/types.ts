export type ApplicationStatus =
  | "PENDING_APPROVAL"
  | "QUERIED"
  | "REJECTED"
  | "APPROVED_FOR_BIOMETRICS"
  | "BIOMETRICS_CAPTURED"
  | "READY_FOR_COLLECTION"
  | "ISSUED";

export type CardStatus = "APPROVED" | "QUERIED" | "ISSUED" | "RENEWED" | "REVOKED";

export type StaffRole = "SuperAdmin" | "ApprovingOfficer" | "IssuingOfficer" | "Inspector" | "Auditor";

export type DocumentType =
  | "photo"
  | "signature"
  | "passport_copy"
  | "residence_visa"
  | "quota_approval"
  | "domicile_proof"
  | "additional";

export type Particulars = {
  surname: string;
  forenames: string;
  nationality: string;
  date_of_birth: string;
  place_of_birth: string;
  sex: string;
  height?: string | null;
  complexion?: string | null;
  eye_color?: string | null;
  hair_color?: string | null;
  distinguished_features?: string | null;
  blood_group?: string | null;
  profession: string;
  domicile: string;
  change_of_address?: string | null;
  passport_number: string;
  passport_issue_date?: string | null;
  passport_expiry?: string | null;
  national_id_number?: string | null;
  tax_id_number?: string | null;
  emergency_contact_name: string;
  emergency_contact_relation: string;
  emergency_contact_phone: string;
  emergency_contact_address: string;
};

export type EnrollmentCenter = {
  id: number;
  code: string;
  name: string;
  state: string;
  address: string;
  time_slots?: string[];
};

export type ApplicationDocument = {
  id: number;
  type: DocumentType;
  label: string;
  mime_type: string;
  size_bytes: number;
  version: number;
  uploaded_at: string;
};

export type Application = Particulars & {
  id: number;
  application_number: string;
  reference_number: string;
  type: "NEW" | "RENEWAL";
  channel: "ONLINE" | "ASSISTED";
  status: ApplicationStatus;
  status_label: string;
  tracker_step: number;
  phone: string;
  email: string;
  enrollment_center?: EnrollmentCenter;
  appointment_date: string;
  appointment_time: string;
  fee_amount_naira: number;
  payment_status: string;
  submitted_at: string | null;
  decided_at: string | null;
  decision_notes: string | null;
  biometrics_captured_at: string | null;
  ready_at: string | null;
  collected_at: string | null;
  card?: { id: number; card_number: string; booklet_number: string; expires_on: string } | null;
  renewal_of_card_number?: string | null;
  documents?: ApplicationDocument[];
  history?: { from: string | null; to: ApplicationStatus; label: string; notes: string | null; at: string }[];
  created_at: string;
};

export type Card = Particulars & {
  id: number;
  card_number: string;
  booklet_number: string;
  status: CardStatus;
  verification_status: "VALID" | "EXPIRED" | "REVOKED" | "WATCHLISTED" | "NOT_ISSUED";
  issuing_country: string;
  statutory_protocol: string;
  decision_reference: string;
  decision_date: string;
  approving_authority: string;
  issuing_officer_name: string;
  issuing_officer_service_no: string;
  issued_on: string;
  issued_at: string;
  expires_on: string;
  postage_stamp_code: string | null;
  authority_signature: string | null;
  query_reason: string | null;
  revocation_reason: string | null;
  revoked_at: string | null;
  is_watchlisted: boolean;
  watchlist_reason: string | null;
  watchlisted_at: string | null;
  approved_at: string | null;
  application_id?: number | null;
  renewals?: {
    renewal_number: number;
    from_date: string;
    to_date: string;
    renewed_at: string;
    endorsing_officer: string;
    officer_service_no: string;
    fee_paid_naira: number;
    receipt_number: string | null;
    remarks: string | null;
  }[];
};

export type StaffUser = {
  id: number;
  username: string;
  fullname: string;
  service_number: string;
  email: string;
  role: StaffRole;
  role_label?: string;
  command: string;
  is_active?: boolean;
  must_change_password?: boolean;
  last_login_at?: string | null;
  unread_notifications?: number;
};

export type Paginated<T> = {
  data: T[];
  meta?: { current_page: number; last_page: number; total: number };
  current_page?: number;
  last_page?: number;
  total?: number;
};

export type AppNotification = {
  id: string;
  data: { title: string; notes?: string | null; application_id?: number; application_number?: string; status?: string };
  read_at: string | null;
  created_at: string;
};
