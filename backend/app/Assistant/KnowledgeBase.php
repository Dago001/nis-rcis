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
        $fee = number_format((int) config('nis.fee_naira'));
        $validity = (int) config('nis.card_validity_years');

        return [
            [
                'id' => 'eligibility',
                'question' => 'Who needs a residence card?',
                'answer' => 'Citizens of ECOWAS member states (Benin, Cabo Verde, Cote d\'Ivoire, The Gambia, Ghana, Guinea, Guinea-Bissau, Liberia, Senegal, Sierra Leone and Togo) who live in Nigeria for employment, business or study and hold a valid residence visa (STR) must obtain a residence card, and renew it before it expires.',
                'keywords' => ['who', 'need', 'eligible', 'eligibility', 'qualify', 'foreigner', 'expatriate', 'student', 'require'],
                'links' => [['label' => 'About the card', 'href' => '/about']],
            ],
            [
                'id' => 'documents',
                'question' => 'What documents do I need?',
                'answer' => 'You need: your international passport data page or ECOWAS Travel Certificate (valid for at least six months), proof of domicile in Nigeria (utility bill), a recent colour passport photograph on a white background, and category-specific documents: Student (Institution Admission Letter), Work (Employment Appointment/Offer Letter), Business (CAC Business Registration Certificate/Documents). Files can be JPG, PNG or PDF up to 2 MB each.',
                'keywords' => ['document', 'documents', 'requirement', 'requirements', 'upload', 'passport', 'visa', 'photo', 'photograph', 'admission', 'appointment', 'cac', 'pdf', 'file', 'size'],
                'links' => [['label' => 'Start an application', 'href' => '/register']],
            ],
            [
                'id' => 'steps',
                'question' => 'How do I apply?',
                'answer' => "The application follows an 8-step wizard:\n1. General Information: select your nationality, category (Student, Work, Business), and document type (Passport or ECOWAS Travel Certificate).\n2. Personal details: upload passport photograph and particulars.\n3. Travel / Identity document: passport or travel certificate details.\n4. Residence & contacts: home country address, domicile in Nigeria, and emergency contact.\n5. Documents: upload domicile proof and category document (Admission letter, Appointment letter, or CAC certificate).\n6. Review & declaration: check all details and accept declaration.\n7. Fee payment: pay securely online via Paystack.\n8. Biometrics appointment: choose center and time.\nA Vetting Officer cross-checks all particulars and documents before approving for biometrics.",
                'keywords' => ['apply', 'application', 'how to apply', 'how do i apply', 'start', 'process', 'steps', 'register', 'new', 'form', 'vetting'],
                'links' => [['label' => 'Create an account', 'href' => '/register'], ['label' => 'Apply now', 'href' => '/register']],
            ],
            [
                'id' => 'fee',
                'question' => 'How much does it cost and how do I pay?',
                'answer' => "The residence card fee is ₦{$fee}. You pay online by card, bank transfer or USSD through Paystack in Step 7. Card is valid for {$validity} years from the date of production. Your payment is confirmed automatically before the appointment is booked.",
                'keywords' => ['fee', 'cost', 'price', 'pay', 'payment', 'paystack', 'naira', 'money', 'charge', 'how much', 'ussd', 'transfer'],
                'links' => [],
            ],
            [
                'id' => 'save',
                'question' => 'Can I stop and continue later?',
                'answer' => 'Yes. Click "Save & exit" at any step. Your answers and uploaded documents are kept, and you can continue after signing in again.',
                'keywords' => ['save', 'continue', 'later', 'draft', 'resume', 'exit'],
                'links' => [['label' => 'Applicant portal', 'href' => '/portal']],
            ],
            [
                'id' => 'queried',
                'question' => 'My application was queried. What should I do?',
                'answer' => "Sign in to the portal and open your application. Read the Vetting Officer's query note and reason, upload the corrected document and send your response. The Vetting Officer will re-verify it.",
                'keywords' => ['query', 'queried', 'correct', 'correction', 'respond', 'response', 'officer', 'vetting', 'note', 'fix', 'reupload'],
                'links' => [['label' => 'Applicant portal', 'href' => '/portal']],
            ],
            [
                'id' => 'rejected',
                'question' => 'My application was rejected.',
                'answer' => 'A rejected application is closed. The reason given by the officer is shown in the portal. You can submit a new application once you have the correct documents, or contact the Service for advice.',
                'keywords' => ['reject', 'rejected', 'refused', 'denied', 'decline'],
                'links' => [['label' => 'Contact us', 'href' => '/contact']],
            ],
            [
                'id' => 'biometrics',
                'question' => 'What happens at the biometrics appointment?',
                'answer' => 'Bring your original passport and your printed appointment slip. An enrollment officer captures all 10 fingerprints (right and left hands), takes a live facial photograph and captures your signature. Afterwards, a Temporary Residence Slip becomes available on your console.',
                'keywords' => ['biometric', 'biometrics', 'appointment', 'capture', 'fingerprint', '10 fingers', 'enrollment', 'enrolment', 'center', 'centre', 'bring', 'slip'],
                'links' => [],
            ],
            [
                'id' => 'temporary_slip',
                'question' => 'What is the Temporary Residence Slip?',
                'answer' => 'Once your 10-finger biometrics are captured, a Temporary Residence Slip appears on your Applicant Console (and the Staff Console). It features your live photo, signature, and verification QR code. It proves you have enrolled while waiting for your original card to be produced. When the original card is produced, this temporary slip becomes automatically invalid.',
                'keywords' => ['temporary slip', 'interim', 'temporary', 'slip', 'enrolled', 'waiting', 'qr code', 'photo', 'signature'],
                'links' => [['label' => 'Applicant portal', 'href' => '/portal']],
            ],
            [
                'id' => 'status',
                'question' => 'What do the application statuses mean?',
                'answer' => "Pending approval: waiting for an immigration Vetting Officer to cross-check.\nQueried: the Vetting Officer requires corrections or further documentation.\nApproved for biometrics: vetted and cleared to attend your 10-finger capture.\nBiometrics captured: biometrics taken; Temporary Residence Slip is issued while card is produced.\nReady for collection: collect your card at your enrollment center (Temporary slip becomes invalid).\nCard collected: issued and active (valid 2 years).\nRejected: closed with reasons provided.",
                'keywords' => ['status', 'stage', 'pending', 'approved', 'approval', 'mean', 'meaning', 'progress', 'production'],
                'links' => [['label' => 'Track an application', 'href' => '/track']],
            ],
            [
                'id' => 'track',
                'question' => 'How do I track my application?',
                'answer' => 'Sign in to the portal to see full details. Without signing in, open the Track page and enter your application number and your passport number. For your privacy, both are required.',
                'keywords' => ['track', 'tracking', 'check', 'where', 'follow'],
                'links' => [['label' => 'Track an application', 'href' => '/track']],
            ],
            [
                'id' => 'ready',
                'question' => 'How do I know when my card is ready?',
                'answer' => 'You receive an e-mail and a notification in the portal when your card is ready for collection at your enrollment center.',
                'keywords' => ['ready', 'collect', 'collection', 'pick', 'when', 'long', 'time', 'notify', 'notification'],
                'links' => [],
            ],
            [
                'id' => 'renewal',
                'question' => 'How do I renew my card?',
                'answer' => "A residence card is valid for {$validity} years. To renew, sign in, choose \"Renew a card\" and enter your current card number. The renewal follows the same steps as a new application.",
                'keywords' => ['renew', 'renewal', 'renew card', 'how to renew', 'how do i renew', 'expire', 'expired', 'expiring', 'expiry', 'valid', 'validity', 'years'],
                'links' => [['label' => 'Renew a card', 'href' => '/login/applicant']],
            ],
            [
                'id' => 'verify',
                'question' => 'How can someone check that a card is genuine?',
                'answer' => 'Anyone can scan the QR code on the card, or enter the card number and passport number on the Verify card page.',
                'keywords' => ['verify', 'verification', 'genuine', 'fake', 'authentic', 'qr', 'employer', 'valid card'],
                'links' => [['label' => 'Verify a card', 'href' => '/verify']],
            ],
            [
                'id' => 'password',
                'question' => 'I forgot my password.',
                'answer' => 'Use "Forgot password" on the sign-in page to receive a reset link by e-mail. Staff should ask their administrator.',
                'keywords' => ['password', 'forgot', 'reset', 'login', 'sign in', 'signin', 'locked', 'account'],
                'links' => [['label' => 'Forgot password', 'href' => '/forgot-password']],
            ],
            [
                'id' => 'lost',
                'question' => 'My card was lost or stolen.',
                'answer' => 'Report the loss at your enrollment center or any NIS office as soon as possible, so the card can be flagged. Bring your passport.',
                'keywords' => ['lost', 'stolen', 'missing', 'damaged', 'replace', 'replacement'],
                'links' => [['label' => 'Contact us', 'href' => '/contact']],
            ],
            [
                'id' => 'contact',
                'question' => 'How do I contact the Service?',
                'answer' => 'See the Contact page for the NIS Headquarters address and official channels. Office hours are Monday to Friday. NIS staff will never ask for your password.',
                'keywords' => ['contact', 'phone', 'email', 'address', 'office', 'headquarters', 'help', 'support', 'hours'],
                'links' => [['label' => 'Contact us', 'href' => '/contact']],
            ],
            [
                'id' => 'security',
                'question' => 'Is my information safe?',
                'answer' => 'Yes. The portal uses secure sign-in (OAuth2), encrypted connections, and private document storage. Only you and authorised officers can see your application. This assistant cannot see or share anyone else\'s records.',
                'keywords' => ['safe', 'secure', 'security', 'privacy', 'private', 'data', 'protect'],
                'links' => [],
            ],
        ];
    }

    /**
     * Best-matching entries for a question (simple keyword scoring).
     *
     * @return list<array{id: string, question: string, answer: string, keywords: list<string>, links: list<array{label: string, href: string}>}>
     */
    public function search(string $question, int $limit = 1): array
    {
        $text = ' '.strtolower(preg_replace('/[^a-z0-9 ]+/i', ' ', $question)).' ';
        $scored = [];

        foreach ($this->entries() as $entry) {
            $score = 0;
            foreach ($entry['keywords'] as $keyword) {
                if (str_contains($text, ' '.$keyword)) {
                    $score += str_contains($keyword, ' ') ? 3 : 2;
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
        return ['/', '/about', '/faq', '/track', '/verify', '/contact', '/login', '/login/applicant', '/register', '/forgot-password', '/portal', '/portal/apply', '/portal/apply?type=renewal'];
    }

    /** @return list<string> */
    public function suggestions(bool $signedIn): array
    {
        return $signedIn
            ? ['What is the status of my application?', 'When is my appointment?', 'What documents do I need?', 'How do I renew my card?']
            : ['How do I apply?', 'What documents do I need?', 'How much does it cost?', 'How do I track my application?'];
    }
}
