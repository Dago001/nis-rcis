# Connections to other government systems

NIS-RCIS is ready to connect to three outside systems. Each needs a formal
agreement with the owning agency before it can be switched on. Until then the
system either uses a **simulator** (on test computers only) or records the
check as **"Not checked — verify manually"**. Nothing is ever approved or
refused automatically: every answer is shown to the approving officer, and a
problem appears as a warning in the application's fraud panel.

| Connection | Owner | What NIS-RCIS asks | When |
|---|---|---|---|
| Stolen and lost passports (SLTD) | INTERPOL, through NCB Abuja (Nigeria Police Force) | Is this passport recorded as stolen or lost? | Every submitted application; officers can re-run it |
| Expatriate quota register | Federal Ministry of Interior (Citizenship and Business) | Is this quota approval valid for this employer and position? | When the applicant gives a quota number; officers can re-run it |
| Fingerprint scanner | NIS (hardware at each biometrics desk) | The applicant's fingerprints | At the biometrics desk |

## 1. Interpol SLTD

**Agreement needed:** access to INTERPOL's I-24/7 network through NCB Abuja
(FIND or a national gateway), including the terms for querying on behalf of NIS.

**What NIS-RCIS sends** (`POST {INTERPOL_SLTD_URL}/sltd/search`, JSON, bearer token):

```json
{ "document_number": "A12345678", "document_type": "PASSPORT", "issuing_country": "GHANA" }
```

**What it expects back:**

```json
{ "hit": false, "reference": "NCB-2026-000123", "records": [] }
```

When the real interface specification arrives, only
`backend/app/Integrations/Drivers/InterpolSltdHttp.php` needs to change to map
its fields (for example to ISO 3166 country codes or a SOAP envelope).

**Switch on** in `backend/.env`:

```
INTERPOL_SLTD_DRIVER=http
INTERPOL_SLTD_URL=https://gateway.example.gov.ng/
INTERPOL_SLTD_API_KEY=...
```

**On a hit:** the application shows a HIGH warning *"Interpol SLTD: the passport
is recorded as stolen or lost"*. Follow the NCB procedure: keep the passport,
do not tell the applicant why, and contact NCB Abuja.

## 2. Ministry of Interior expatriate quota

**Agreement needed:** read access to the expatriate quota register (quota
number, employer, approved positions, positions used, expiry).

Applicants enter the **quota approval number** and **employer** in step 4 of
the online form (staff can enter them for assisted applications).

**What NIS-RCIS sends** (`POST {MOI_QUOTA_URL}/quotas/verify`):

```json
{ "quota_reference": "MOI/EQ/2026/123", "employer_name": "DANGOTE CEMENT PLC", "passport_number": "A12345678", "position": "CIVIL ENGINEER" }
```

**What it expects back:**

```json
{ "found": true, "valid": true, "reference": "…", "employer": "…", "positions_approved": 5, "positions_used": 2, "expires_on": "2027-06-30", "reason": null }
```

Adapter: `backend/app/Integrations/Drivers/MoiQuotaHttp.php`. Switch on with
`MOI_QUOTA_DRIVER=http`, `MOI_QUOTA_URL` and `MOI_QUOTA_API_KEY`.

## 3. Fingerprint scanners

The biometrics desk supports **SecuGen** USB scanners (for example Hamster Pro
20) through the free **SecuGen WebAPI** service, installed on each desk
computer. The browser talks to the scanner service on the same computer
(`https://localhost:8443/SGIFPCapture`); the templates (ISO/IEC 19794-2) are
then sent to NIS-RCIS and stored **encrypted**. Fingerprint images never leave
the desk computer, and templates are never shown or exported.

Set up each desk computer:

1. Install the SecuGen device driver and the SecuGen WebAPI (from SecuGen).
2. Open `https://localhost:8443` once in the browser and accept its certificate.
3. Plug in the scanner and capture a test print on the biometrics desk.

Settings (frontend `.env.local`, then rebuild):

```
NEXT_PUBLIC_FINGERPRINT_SCANNER=secugen          # or off
NEXT_PUBLIC_FINGERPRINT_SERVICE_URL=https://localhost:8443/SGIFPCapture
NEXT_PUBLIC_FINGERPRINT_LICENSE=                  # SecuGen licence string for your domain
```

When every desk has a scanner, make fingerprints compulsory (at least two
fingers) with `FINGERPRINTS_REQUIRED=true` in `backend/.env`.

Other scanner makes can be added as another driver in
`frontend/src/components/FingerprintCapture.tsx` returning the same fields.

## Testing without the real systems

On test computers (`APP_ENV` not `production`) the simulators answer:

- **Interpol:** a passport number starting with `SLTD` (or listed in
  `INTERPOL_SLTD_TEST_HITS`) is a hit; anything else is clear.
- **Quota:** a number like `MOI/EQ/2026/123` is valid; ending in `000` is not
  found; containing `EXP` is expired.
- **Fingerprints:** tick *Use the simulated scanner* on the biometrics desk.

On the live system the simulators are refused: an unconfigured connection is
recorded as "Not checked".
