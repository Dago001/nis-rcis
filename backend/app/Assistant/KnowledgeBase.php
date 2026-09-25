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
                'answer' => 'Foreign nationals who live in Nigeria for employment, business or study and hold a valid residence visa (STR) must obtain a residence card, and renew it before it expires.',
                'keywords' => ['who', 'need', 'eligible', 'eligibility', 'qualify', 'foreigner', 'expatriate', 'student', 'require'],
                'links' => [['label' => 'About the card', 'href' => '/about']],
            ],
            [
                'id' => 'documents',
                'question' => 'What documents do I need?',
                'answer' => 'You need: your international passport data page (valid for at least six more months), your residence visa (STR), a recent colour passport photograph on a white background and, if you are employed, your expatriate quota approval. Proof of address is optional. Files can be JPG, PNG or PDF, up to 5 MB each; the photograph must be JPG or PNG.',
                'keywords' => ['document', 'documents', 'requirement', 'requirements', 'upload', 'passport', 'visa', 'str', 'photo', 'photograph', 'quota', 'pdf', 'file', 'size'],
                'links' => [['label' => 'Start an application', 'href' => '/portal/apply']],
            ],
            [
                'id' => 'steps',
                'question' => 'How do I apply?',
                'answer' => "1. Create an account and sign in to the applicant portal.\n2. Complete the 7-step form: personal details, passport, address and emergency contact, documents, payment, appointment and declaration.\n3. Pay the fee online and book a biometrics appointment.\n4. An Approving Officer reviews your application.\n5. Attend your biometrics appointment.\n6. Collect your card when you are told it is ready.",
                'keywords' => ['apply', 'application', 'how', 'start', 'process', 'steps', 'register', 'new', 'form'],
                'links' => [['label' => 'Create an account', 'href' => '/register'], ['label' => 'Apply now', 'href' => '/portal/apply']],
            ],
            [
                'id' => 'fee',
                'question' => 'How much does it cost and how do I pay?',
                'answer' => "The residence card fee is ₦{$fee}. You pay online by card, bank transfer or USSD through Paystack during the application. Your payment is confirmed automatically before the application is submitted. Never pay anyone outside the portal.",
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
                'answer' => "Sign in to the portal and open your application. Read the officer's note, upload the corrected document and send your response. The application then returns to the approval queue.",
                'keywords' => ['query', 'queried', 'correct', 'correction', 'respond', 'response', 'officer', 'note', 'fix', 'reupload'],
                'links' => [['label' => 'Applicant portal', 'href' => '/portal']],
            ],
            [
                'id' => 'rejected',
                'question' => 'My application was rejected.',
                'answer' => 'A rejected application is closed. The reason is shown in the portal. You can submit a new application once you have the correct documents, or contact the Service for advice.',
                'keywords' => ['reject', 'rejected', 'refused', 'denied', 'decline'],
                'links' => [['label' => 'Contact us', 'href' => '/contact']],
            ],
            [
                'id' => 'biometrics',
                'question' => 'What happens at the biometrics appointment?',
                'answer' => 'Bring your original passport and your printed appointment slip. An officer confirms your identity, takes a live photograph and records your signature. Appointments are on weekdays at the enrollment center you chose.',
                'keywords' => ['biometric', 'biometrics', 'appointment', 'capture', 'fingerprint', 'enrollment', 'enrolment', 'center', 'centre', 'bring', 'slip', 'book', 'reschedule'],
                'links' => [],
            ],
            [
                'id' => 'status',
                'question' => 'What do the application statuses mean?',
                'answer' => "Pending approval: waiting for an Approving Officer.\nQueried: the officer needs a correction from you.\nApproved for biometrics: attend your appointment.\nBiometrics captured: your card is being produced.\nReady for collection: collect your card at your enrollment center.\nCard collected: the process is complete.\nRejected: the application was closed.",
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
                'keywords' => ['renew', 'renewal', 'expire', 'expired', 'expiring', 'expiry', 'valid', 'validity', 'years'],
                'links' => [['label' => 'Renew a card', 'href' => '/portal/apply?type=renewal']],
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
        return ['/', '/about', '/faq', '/track', '/verify', '/contact', '/login', '/register', '/forgot-password', '/portal', '/portal/apply', '/portal/apply?type=renewal'];
    }

    /** @return list<string> */
    public function suggestions(bool $signedIn): array
    {
        return $signedIn
            ? ['What is the status of my application?', 'When is my appointment?', 'What documents do I need?', 'How do I renew my card?']
            : ['How do I apply?', 'What documents do I need?', 'How much does it cost?', 'How do I track my application?'];
    }
}
