<?php

namespace App\Assistant;

/**
 * Curated, public facts about the residence card service.
 *
 * This is the ONLY information the assistant may use for general questions.
 * It contains no database content, no configuration secrets and no personal
 * data, so it is safe to place in the language-model prompt and to show to
 * anyone. Keep it in step with the public FAQ page.
 */
class KnowledgeBase
{
    /**
     * @return list<array{id: string, question: string, answer: string, keywords: list<string>, links: list<array{label: string, href: string}>}>
     */
    public function entries(): array
    {
        $fee = number_format((int) config('nis.fee_naira', 25000));
        $validity = (int) config('nis.card_validity_years', 2);
        $codFee = number_format(15000);

        return [
            [
                'id' => 'apply',
                'question' => 'How do I apply for an ECOWAS Residence Card?',
                'answer' => "To apply for an ECOWAS Residence Card, register for an account on the portal and complete the 7-step online application:\n1. General Information: Select nationality, document type (Passport or ECOWAS Travel Certificate), and category (Work, Business, Student, or Religious Activities).\n2. Personal details: Enter full personal particulars and upload a colour passport photograph.\n3. Residence & contacts: Provide home country address, residential address in Nigeria, and emergency contact.\n4. Documents: Upload proof of domicile in Nigeria and all mandatory category-specific supporting documents.\n5. Review & declaration: Review all submitted information and confirm the statutory declaration.\n6. Fee payment: Pay the ₦{$fee} application fee securely online.\n7. Biometrics appointment: Select an enrollment center, date, and convenient time slot for 10-finger biometric capture.\nOnce submitted, an immigration Vetting Officer reviews and verifies your application.",
                'keywords' => ['apply', 'application', 'how to apply', 'how do i apply', 'start', 'process', 'steps', 'register', 'new application', 'procedure', 'how can i apply', 'comment postuler', 'demande', 'form'],
                'links' => [['label' => 'Create an account', 'href' => '/register'], ['label' => 'Apply for Residence Card', 'href' => '/register']],
            ],
            [
                'id' => 'eligibility',
                'question' => 'Who is eligible to apply?',
                'answer' => 'Citizens of ECOWAS member states (Benin, Burkina Faso, Cabo Verde, Côte d\'Ivoire, The Gambia, Ghana, Guinea, Guinea-Bissau, Liberia, Mali, Niger, Senegal, Sierra Leone, and Togo) residing in Nigeria for Employment (Work), Business/Investment, Study, or Religious Activities holding valid travel and residence credentials are required and eligible to obtain an ECOWAS Residence Card. Current holders must also renew before card expiration.',
                'keywords' => ['who', 'need', 'eligible', 'eligibility', 'qualify', 'foreigner', 'expatriate', 'who is eligible', 'who can apply', 'citizens', 'member states', 'ecowas', 'qui est eligible'],
                'links' => [['label' => 'About the card', 'href' => '/about']],
            ],
            [
                'id' => 'documents',
                'question' => 'What documents are required?',
                'answer' => "All applicants must upload the following compulsory documents:\n1. International Passport data page OR ECOWAS Travel Certificate (valid for at least 6 months).\n2. Proof of Residence (Utility Bill / Tenancy Agreement).\n3. Recent colour passport photograph on a white background (JPG or PNG).\nIn addition, you must provide your category-specific document:\n• Work: Appointment Letter / Contract Letter.\n• Business (Trade / Artisans / Enterprise): CAC Registration Document (Optional).\n• Student (Intent): Institution Admission Letter, Acceptance Letter, and Proof of Payment.\n• Cleric / Missionaries: Appointment Letter / Contract Letter AND Evidence of Host Organization Registration or Recognition.\nAll documents may be PDF, JPG, or PNG up to 2 MB each.",
                'keywords' => ['document', 'documents', 'requirement', 'requirements', 'what documents', 'upload', 'passport', 'photo', 'photograph', 'utility', 'domicile', 'residence', 'supporting documents', 'quels documents', 'justificatifs'],
                'links' => [['label' => 'Apply now', 'href' => '/register'], ['label' => 'Requirements', 'href' => '/#req-heading']],
            ],
            [
                'id' => 'categories',
                'question' => 'What are the application categories?',
                'answer' => "There are four approved application categories on the portal:\n1. Work (Employment): For individuals employed by private, commercial, or public organizations in Nigeria.\n2. Business (Trade / Artisans / Enterprise): For business owners, traders, artisans, investors, entrepreneurs, and sole proprietors.\n3. Student (Intent): For students admitted and enrolled in accredited Nigerian educational institutions.\n4. Cleric / Missionaries: For recognized missionaries, pastors, imams, and expatriate religious clergy residing in Nigeria.\nEach category requires specific supporting documentation during application.",
                'keywords' => ['category', 'categories', 'class', 'classes', 'application categories', 'types of application', 'work', 'business', 'student', 'religious', 'cleric', 'missionaries', 'artisans', 'catégories'],
                'links' => [['label' => 'Apply now', 'href' => '/register']],
            ],
            [
                'id' => 'category_work',
                'question' => 'How do I apply under Work?',
                'answer' => "To apply under the Work category, select 'Work (Employment)' in Step 1. In addition to your international passport (or ECOWAS Travel Certificate), proof of residence in Nigeria (utility bill / tenancy agreement), and passport photograph, you must upload your official Appointment Letter / Contract Letter issued by your Nigerian employer.",
                'keywords' => ['work', 'employment', 'job', 'employee', 'employer', 'appointment letter', 'offer letter', 'contract', 'how to apply under work', 'travail', 'emploi'],
                'links' => [['label' => 'Apply under Work', 'href' => '/register']],
            ],
            [
                'id' => 'category_business',
                'question' => 'How do I apply under Business?',
                'answer' => "To apply under the Business category, select 'Business (Trade / Artisans / Enterprise)' in Step 1. Along with your valid international passport data page (or ECOWAS Travel Certificate), proof of residence in Nigeria (utility bill / tenancy agreement), and passport photograph, you may optionally upload your Corporate Affairs Commission (CAC) Registration Document.",
                'keywords' => ['business', 'trade', 'artisans', 'cac', 'investor', 'enterprise', 'company', 'registration', 'business certificate', 'how to apply under business', 'commerce', 'affaires'],
                'links' => [['label' => 'Apply under Business', 'href' => '/register']],
            ],
            [
                'id' => 'category_student',
                'question' => 'How do I apply under Student?',
                'answer' => "To apply under the Student category, select 'Student (Intent)' in Step 1. Along with your valid international passport data page (or ECOWAS Travel Certificate), proof of residence in Nigeria, and passport photograph, you must upload your Institution Admission Letter, Acceptance Letter, and Proof of Payment.",
                'keywords' => ['student', 'intent', 'school', 'university', 'college', 'admission letter', 'acceptance letter', 'proof of payment', 'institution', 'study', 'education', 'how to apply under student', 'etudiant', 'etudes'],
                'links' => [['label' => 'Apply under Student', 'href' => '/register']],
            ],
            [
                'id' => 'category_religious',
                'question' => 'How do I apply under Cleric / Missionaries?',
                'answer' => "To apply under Cleric / Missionaries, select 'Cleric / Missionaries' in Step 1. Along with your international passport data page, proof of residence in Nigeria, and passport photograph, you must upload TWO category documents: (1) Appointment Letter / Contract Letter from your host religious organization in Nigeria, and (2) Evidence of Host Organization Registration or Recognition in Nigeria.",
                'keywords' => ['religious', 'cleric', 'missionaries', 'religious activities', 'church', 'mosque', 'missionary', 'clergy', 'pastor', 'imam', 'appointment letter', 'contract letter', 'invitation letter', 'host organization', 'religion', 'religieux'],
                'links' => [['label' => 'Apply under Cleric / Missionaries', 'href' => '/register']],
            ],
            [
                'id' => 'dependants',
                'question' => 'How do I apply for my spouse or child?',
                'answer' => "Eligible dependants (Spouse or Child) can apply through the portal with a linked Principal application:\n1. The Principal applicant must first submit their own application and obtain a Principal Application Reference Number.\n2. When starting the dependant's application, specify the relationship (Spouse or Child) and input the Principal's Application Number.\n3. The dependant receives their own unique application number and pays their applicable fee.\n4. Each dependant is processed independently through security vetting, final approval, biometrics capture, and receives their own distinct ECOWAS Residence Card.",
                'keywords' => ['spouse', 'child', 'children', 'dependant', 'dependants', 'family', 'wife', 'husband', 'son', 'daughter', 'principal', 'how do i apply for spouse', 'how do i apply for child', 'conjoint', 'enfant'],
                'links' => [['label' => 'Applicant portal', 'href' => '/login/applicant']],
            ],
            [
                'id' => 'renewal',
                'question' => 'How do I renew my residence card?',
                'answer' => "An ECOWAS Residence Card is valid for {$validity} years. To renew, sign in to your applicant portal, click 'Renew Card' (or 'Residence card renewal'), and select your previous card or enter your current card number.\nYour previously recorded personal information is automatically pre-populated into the renewal application form to save you time. All pre-populated fields remain directly editable on the renewal form without incurring extra charges. Only if you request a formal Change of Name or Date of Birth will the ₦{$codFee} Change of Data administrative fee apply.",
                'keywords' => ['renew', 'renewal', 'renew card', 'how to renew', 'how do i renew', 'expire', 'expired', 'expiring', 'expiry', 'validity', 'prepopulated', 'auto-populate', 'renouveler', 'renouvellement'],
                'links' => [['label' => 'Renew Residence Card', 'href' => '/login/applicant']],
            ],
            [
                'id' => 'change_of_data',
                'question' => 'How do I correct my name or date of birth?',
                'answer' => "If you need to correct your official record during renewal, use the Change of Data (COD) feature on the renewal form:\n• Eligible Fields: COD is restricted strictly to Change of Name and/or Change of Date of Birth.\n• Supporting Evidence: You must upload authorized legal documentation verifying the change (e.g. Marriage Certificate, Newspaper Publication, Deed Poll, National Population Commission NPC Birth Certificate, or Court Affidavit).\n• Administrative Fee: A single flat administrative charge of ₦{$codFee} is added to the application fee, whether one or both fields are changed.\n• Ordinary renewal corrections (such as address, phone number, employer, or emergency contact) do NOT require COD and attract NO additional fee.",
                'keywords' => ['change of data', 'cod', 'correct name', 'correct date of birth', 'change name', 'change dob', 'error', 'mistake', 'affidavit', 'marriage certificate', 'deed poll', '15000', '15,000', 'modification', 'corriger nom'],
                'links' => [['label' => 'Renew and Change Data', 'href' => '/login/applicant']],
            ],
            [
                'id' => 'fee',
                'question' => 'How much does the application cost?',
                'answer' => "The official ECOWAS Residence Card application fee is ₦{$fee}. If you are applying for a Renewal and requesting a formal Change of Data (Change of Name and/or Date of Birth), a flat administrative fee of ₦{$codFee} applies. All fees are paid securely online on the portal. No cash payments are accepted at enrollment centers.",
                'keywords' => ['fee', 'cost', 'price', 'how much', 'how much does it cost', 'naira', 'amount', 'charge', 'application fee', 'combien', 'frais', 'prix'],
                'links' => [['label' => 'Fee payments', 'href' => '/login/applicant']],
            ],
            [
                'id' => 'payment',
                'question' => 'How do I make payment?',
                'answer' => "Payment is made in Step 6 of the online application form:\n1. Choose your preferred payment method: Debit/Credit Card, Bank Transfer, or USSD.\n2. Transactions are processed securely in real-time through Paystack.\n3. Upon successful payment, an automated digital payment confirmation and receipt with a unique transaction reference are generated.\n4. Once payment is confirmed, Step 7 (Biometrics Appointment booking) unlocks immediately.\nIf your payment does not reflect immediately, use the 'Verify Payment' button in the Applicant Portal or contact support.",
                'keywords' => ['pay', 'payment', 'make payment', 'how do i pay', 'paystack', 'card', 'bank transfer', 'ussd', 'receipt', 'payer', 'paiement'],
                'links' => [['label' => 'Payment history', 'href' => '/login/applicant']],
            ],
            [
                'id' => 'appointment',
                'question' => 'How do I book a biometrics appointment?',
                'answer' => "You book your biometrics appointment in Step 7 immediately after fee payment confirmation:\n1. Select your preferred NIS Enrollment Center.\n2. Choose an available appointment date and convenient time slot.\n3. Download and print your official Biometrics Appointment Slip.\n4. On your appointment day, arrive at the center with your original passport and the printed appointment slip.\n5. An enrollment officer will capture all 10 fingerprints, take a live facial photograph, and capture your digital signature.\n6. Immediately following capture, an official Temporary Residence Slip with a verification QR code becomes available on your console.",
                'keywords' => ['appointment', 'book appointment', 'biometrics', 'biometrics appointment', 'enrollment center', 'date', 'time', 'booking', 'capture', 'fingerprint', 'rendez-vous', 'biometrie'],
                'links' => [['label' => 'Book appointment', 'href' => '/login/applicant']],
            ],
            [
                'id' => 'track',
                'question' => 'How can I track my application?',
                'answer' => "You can track your application in two ways:\n1. Without signing in: Visit the Track page (/track), enter your Application Reference Number and your Passport Number, and click 'Track Application'.\n2. In the Applicant Portal: Sign in to your dashboard to view the real-time stage, officer notes, query responses, and next steps.",
                'keywords' => ['track', 'tracking', 'how can i track', 'check status', 'track application', 'where is my application', 'suivre', 'statut'],
                'links' => [['label' => 'Track an application', 'href' => '/track']],
            ],
            [
                'id' => 'after_approval',
                'question' => 'What happens after approval?',
                'answer' => "After an immigration Approving Officer clears your vetted application:\n1. Your status updates to 'Approved for Biometrics' and you attend your scheduled 10-finger enrollment.\n2. Upon biometrics capture, you receive a Temporary Residence Slip with a secure QR code for interim lawful identification.\n3. Your physical card enters batch production and quality printing.\n4. When printed and verified, the status becomes 'Ready for Collection', and you receive an email and portal notification to collect your physical card.",
                'keywords' => ['after approval', 'what happens after approval', 'approved', 'next steps after approval', 'biometrics after approval', 'apres approbation'],
                'links' => [['label' => 'Track application', 'href' => '/track']],
            ],
            [
                'id' => 'collection',
                'question' => 'When can I collect my residence card?',
                'answer' => "You can collect your physical residence card once your application status changes to 'Ready for Collection'. You will receive an automated email notification and portal alert. Visit your chosen NIS enrollment center with your original passport and Temporary Residence Slip. Upon card collection, your status becomes 'Issued', the physical card is activated for {$validity} years, and the temporary slip becomes automatically invalid.",
                'keywords' => ['collect', 'collection', 'ready for collection', 'when can i collect', 'pick up', 'card ready', 'receive card', 'retrait', 'carte prete'],
                'links' => [['label' => 'Verify card status', 'href' => '/track']],
            ],
            [
                'id' => 'lost_card',
                'question' => 'How do I report a lost residence card?',
                'answer' => "If your residence card is lost, damaged, or stolen, you must report it immediately:\n1. Sign in to the Applicant Portal, navigate to 'My Cards', and select 'Report Lost/Stolen Card'.\n2. Provide incident details and upload an official Police Report / Extract and an Affidavit of Loss.\n3. The lost card is instantly flagged and watchlisted in the immigration database to prevent unauthorized use.\n4. You can then submit an application for a Replacement Card.",
                'keywords' => ['lost', 'lost card', 'stolen', 'missing', 'damaged', 'report lost', 'replace', 'replacement', 'perdu', 'volee', 'remplacement'],
                'links' => [['label' => 'Applicant portal', 'href' => '/login/applicant'], ['label' => 'Contact Support', 'href' => '/contact']],
            ],
            [
                'id' => 'status_meanings',
                'question' => 'What do the application statuses mean?',
                'answer' => "Here is the meaning of each workflow status:\n• Awaiting Vetting (PENDING_APPROVAL): Submitted; awaiting initial security vetting by an immigration officer.\n• Queried (QUERIED): The officer requires clarification or replacement documents; respond via the portal.\n• Vetted (VETTED): Preliminary verification passed; queued for Comptroller approval.\n• Approved (APPROVED_FOR_BIOMETRICS): Cleared for 10-finger biometric capture.\n• Biometrics Captured (BIOMETRICS_CAPTURED): Biometrics completed; Temporary Residence Slip issued; queued for card production.\n• In Production (APPROVED on card): Card details approved and queued for physical batch printing.\n• Ready for Collection (READY_FOR_COLLECTION): Card printed; ready for collection at enrollment center.\n• Issued (ISSUED): Card officially collected and active for {$validity} years.\n• Rejected (REJECTED): Application declined with formal reasons stated.",
                'keywords' => ['status', 'meaning', 'statuses', 'what does status mean', 'pending', 'queried', 'vetted', 'in production', 'ready', 'signification statut'],
                'links' => [['label' => 'Track your application', 'href' => '/track']],
            ],
            [
                'id' => 'temporary_slip',
                'question' => 'What is the Temporary Residence Slip?',
                'answer' => 'The Temporary Residence Slip is an official interim document generated automatically on your Applicant Console immediately following your 10-finger biometric capture. It contains your live photograph, digital signature, and an encrypted QR code. It serves as temporary proof of lawful residence while your physical card is being manufactured. It becomes automatically invalid as soon as your physical card is collected.',
                'keywords' => ['temporary slip', 'temporary residence slip', 'interim slip', 'qr code slip', 'temporary proof', 'attestation temporaire'],
                'links' => [['label' => 'Applicant portal', 'href' => '/portal']],
            ],
            [
                'id' => 'verify_card',
                'question' => 'How can someone verify that an ECOWAS Residence Card is genuine?',
                'answer' => 'Anyone—including employers, financial institutions, and border authorities—can verify the authenticity of an ECOWAS Residence Card by scanning the secure QR code on the card, or by entering the Card Number and Passport Number on the public Verify Card page (/verify).',
                'keywords' => ['verify', 'verification', 'genuine', 'check card', 'authenticate', 'authentic', 'valid card', 'verifier carte'],
                'links' => [['label' => 'Verify a card', 'href' => '/verify']],
            ],
            [
                'id' => 'forgot_password',
                'question' => 'I forgot my password.',
                'answer' => 'To reset your password, visit the login page and click "Forgot password?". Enter your registered email address, and a secure password reset link will be sent to you.',
                'keywords' => ['forgot password', 'reset password', 'password', 'cannot login', 'locked account', 'mot de passe oublie'],
                'links' => [['label' => 'Forgot password', 'href' => '/forgot-password']],
            ],
            [
                'id' => 'contact_support',
                'question' => 'How do I contact official support?',
                'answer' => 'You can contact the Nigeria Immigration Service through the official Contact page (/contact) or visit the NIS Headquarters at Umar Musa Yar\'Adua Express Way, Sauka, Abuja. Support is available Monday to Friday from 08:00 to 16:00 (WAT). NIS officers will never request your password or personal bank details.',
                'keywords' => ['contact', 'support', 'help', 'phone', 'email', 'office', 'headquarters', 'enquiries', 'contacter'],
                'links' => [['label' => 'Contact Support', 'href' => '/contact']],
            ],
        ];
    }

    /**
     * Best-matching entries for a question (comprehensive keyword scoring).
     *
     * @return list<array{id: string, question: string, answer: string, keywords: list<string>, links: list<array{label: string, href: string}>}>
     */
    public function search(string $question, int $limit = 1): array
    {
        $text = ' ' . strtolower(preg_replace('/[^a-z0-9 ]+/i', ' ', $question)) . ' ';
        $scored = [];

        foreach ($this->entries() as $entry) {
            $score = 0;
            foreach ($entry['keywords'] as $keyword) {
                $kw = strtolower($keyword);
                if (str_contains($text, ' ' . $kw . ' ') || str_contains($text, $kw)) {
                    $score += str_contains($kw, ' ') ? 4 : 2;
                }
            }
            if ($score > 0) {
                $scored[] = [$score, $entry];
            }
        }

        usort($scored, fn ($a, $b) => $b[0] <=> $a[0]);

        return array_map(fn ($s) => $s[1], array_slice($scored, 0, $limit));
    }

    /**
     * The knowledge base as plain text for the language-model system prompt.
     */
    public function asPromptText(): string
    {
        return collect($this->entries())
            ->map(fn ($e) => "Q: {$e['question']}\nA: {$e['answer']}")
            ->implode("\n\n");
    }

    /** Internal pages the assistant may link to. */
    public function allowedPaths(): array
    {
        return ['/', '/about', '/faq', '/track', '/verify', '/contact', '/login', '/login/applicant', '/register', '/forgot-password', '/portal', '/portal/apply', '/portal/apply?type=renewal', '/portal/cards'];
    }

    /** @return list<string> */
    public function suggestions(bool $signedIn): array
    {
        return $signedIn
            ? ['What documents do I need?', 'How do I renew my card?', 'How do I correct my name or DOB?', 'When is my card ready for collection?']
            : ['How do I apply for a card?', 'What documents are required?', 'What are the application categories?', 'How do I apply for my spouse or child?'];
    }
}
