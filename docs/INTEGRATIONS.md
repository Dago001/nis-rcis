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

The biometrics desk works with several scanner makes. Each maker supplies a
small service that runs on the desk computer; the browser talks to it there,
and only the result is sent to NIS-RCIS, where it is stored **encrypted**.
Fingerprints are never shown again or exported.

| Scanner | Install on each desk computer | What is stored |
|---|---|---|
| **HID DigitalPersona** U.are.U 4500, 5100, 5300 (and other HID readers) | **HID DigitalPersona Lite Client** (also called *Authentication Device Client*), from HID | Lossless PNG finger image (ISO/IEC 19794-4), shown to the officer, because HID's browser software does not produce templates |
| **Mantra** MFS100, MFS110 | Mantra driver and **MFS100 client service**, from Mantra | ISO/IEC 19794-2 template |
| **SecuGen** Hamster Pro 20, Hamster IV, ... | SecuGen driver and **SecuGen WebAPI**, from SecuGen | ISO/IEC 19794-2 template |

On the biometrics desk, the **Scanner** list is set to **Automatic**: the page
asks every service and uses the one with a scanner connected. Officers can pick
a make instead; the choice is remembered on that computer.

Set up each desk computer:

1. Install the scanner maker's software from the table (it replaces the Windows
   Hello driver for that scanner) and plug in the scanner.
2. Use **Chrome or Edge**. In **Firefox**, first open the service's address once
   and accept its certificate: `https://127.0.0.1:52181/get_connection`
   (DigitalPersona), `https://localhost:8003/mfs100/info` (Mantra) or
   `https://localhost:8443` (SecuGen).
3. On the live site, Chrome may ask to *allow access to other apps and services
   on this device*: choose **Allow** (the site needs it to reach the scanner service).
4. Capture a test print on the biometrics desk.

Settings (frontend `.env.local`, then rebuild):

```
NEXT_PUBLIC_FINGERPRINT_SCANNER=auto             # auto (default), digitalpersona, mantra, secugen, simulated or off
NEXT_PUBLIC_FINGERPRINT_SERVICE_URL=https://localhost:8443/SGIFPCapture   # SecuGen only
NEXT_PUBLIC_FINGERPRINT_LICENSE=                  # SecuGen licence string for your domain
```

When every desk has a scanner, make fingerprints compulsory (at least two
fingers) with `FINGERPRINTS_REQUIRED=true` in `backend/.env`.

**Another make** (for example Futronic, Suprema or Integrated Biometrics) can be
added if its maker provides a browser service: write a driver in
`frontend/src/lib/fingerprint/` returning the same fields as the others, add it
to `DRIVERS` in `index.ts`, and allow its address in `connect-src` in
`frontend/next.config.ts`. Scanners that only work through Windows Hello
cannot be used: Windows never gives fingerprints to websites.

## Testing without the real systems

On test computers (`APP_ENV` not `production`) the simulators answer:

- **Interpol:** a passport number starting with `SLTD` (or listed in
  `INTERPOL_SLTD_TEST_HITS`) is a hit; anything else is clear.
- **Quota:** a number like `MOI/EQ/2026/123` is valid; ending in `000` is not
  found; containing `EXP` is expired.
- **Fingerprints:** choose *Simulated scanner* in the **Scanner** list on the biometrics desk.

On the live system the simulators are refused: an unconfigured connection is
recorded as "Not checked".
