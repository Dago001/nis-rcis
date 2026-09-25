<?php

namespace App\Console\Commands;

use App\Enums\ApplicationStatus;
use App\Enums\CardStatus;
use App\Enums\DocumentType;
use App\Enums\StaffRole;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\EnrollmentCenter;
use App\Models\Payment;
use App\Models\ResidenceCard;
use App\Models\User;
use App\Services\DocumentStorage;
use App\Services\NumberGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Local test data: officers for every role, applicants, and applications
 * sitting at every stage of the workflow, so each screen can be tried.
 *
 * DEVELOPMENT ONLY — refuses to run in production.
 */
class SeedDemoData extends Command
{
    public const PASSWORD = 'NisDemo-2026!';

    protected $signature = 'nis:demo';

    protected $description = 'Load demo officers, applicants and applications for local testing (never in production)';

    public function __construct(private NumberGenerator $numbers, private DocumentStorage $storage)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Refusing to load demo data in production.');

            return self::FAILURE;
        }

        if (User::where('username', 'demo.admin')->exists()) {
            $this->info('Demo data is already loaded.');
            $this->printAccounts();

            return self::SUCCESS;
        }

        $center = EnrollmentCenter::orderBy('id')->firstOrFail();

        DB::transaction(function () use ($center) {
            $staff = $this->createStaff();
            $applicants = $this->createApplicants();

            // 1. Awaiting approval (Approving Officer: approve / query / reject)
            $this->application($applicants['kwame'], $center, 'GHANA', 'G4455667', ApplicationStatus::PendingApproval, $staff);

            // 2. Queried (applicant: re-upload and respond)
            $this->application($applicants['amara'], $center, 'SENEGAL', 'SN7788990', ApplicationStatus::Queried, $staff,
                'The residence visa copy is not legible. Please upload a clear scan.');

            // 3. Approved for biometrics, appointment today (Issuing Officer: biometrics desk)
            $this->application($applicants['li'], $center, 'CHINA', 'E12345678', ApplicationStatus::ApprovedForBiometrics, $staff);

            // 4. Biometrics captured, card awaiting final approval (Approving Officer)
            $this->application($applicants['elena'], $center, 'ITALY', 'YA1122334', ApplicationStatus::BiometricsCaptured, $staff);

            // 5. Card issued and collected (applicant: renew; public: verify)
            $this->application($applicants['john'], $center, 'UNITED KINGDOM', 'GB9876543', ApplicationStatus::Issued, $staff);
        });

        $this->info('Demo data loaded.');
        $this->printAccounts();

        return self::SUCCESS;
    }

    /** @return array<string, User> */
    private function createStaff(): array
    {
        $rows = [
            'admin' => ['demo.admin', 'Ibrahim Musa', '10001', StaffRole::SuperAdmin],
            'approver' => ['demo.approver', 'Ngozi Adeleke', '10002', StaffRole::ApprovingOfficer],
            'issuer' => ['demo.issuer', 'Chinedu Okoro', '10003', StaffRole::IssuingOfficer],
            'inspector' => ['demo.inspector', 'Fatima Bello', '10004', StaffRole::Inspector],
            'auditor' => ['demo.auditor', 'Emeka Nwosu', '10005', StaffRole::Auditor],
        ];

        return collect($rows)->map(fn ($r) => User::create([
            'username' => $r[0],
            'fullname' => $r[1],
            'service_number' => $r[2],
            'email' => "{$r[0]}@example.com",
            'password' => self::PASSWORD,
            'role' => $r[3],
            'command' => 'Alagbon Passport & Residence Office, Lagos',
            'must_change_password' => false,
        ]))->all();
    }

    /** @return array<string, Applicant> */
    private function createApplicants(): array
    {
        $rows = [
            'john' => ['john.smith@example.com', 'SMITH', 'JOHN'],
            'kwame' => ['kwame.mensah@example.com', 'MENSAH', 'KWAME'],
            'amara' => ['amara.diallo@example.com', 'DIALLO', 'AMARA'],
            'li' => ['li.wei@example.com', 'WEI', 'LI'],
            'elena' => ['elena.rossi@example.com', 'ROSSI', 'ELENA'],
        ];

        return collect($rows)->map(function ($r) {
            $applicant = Applicant::create([
                'email' => $r[0], 'password' => self::PASSWORD, 'surname' => $r[1], 'forenames' => $r[2], 'phone' => '+2348030000000',
            ]);
            $applicant->markEmailAsVerified();

            return $applicant;
        })->all();
    }

    /**
     * @param  array<string, User>  $staff
     */
    private function application(Applicant $applicant, EnrollmentCenter $center, string $nationality, string $passport,
        ApplicationStatus $target, array $staff, ?string $queryNotes = null): Application
    {
        $today = CarbonImmutable::today();
        $appointment = $target === ApplicationStatus::ApprovedForBiometrics ? $today : $this->nextWeekday($today->addDays(3));
        $submitted = $today->subDays(match ($target) {
            ApplicationStatus::Issued => 40,
            ApplicationStatus::BiometricsCaptured => 10,
            default => 2,
        });

        $application = new Application([
            'applicant_id' => $applicant->id,
            'surname' => $applicant->surname,
            'forenames' => $applicant->forenames,
            'nationality' => $nationality,
            'date_of_birth' => '1984-03-12',
            'place_of_birth' => 'ACCRA',
            'sex' => in_array($applicant->forenames, ['AMARA', 'ELENA'], true) ? 'FEMALE' : 'MALE',
            'height' => '1.78m',
            'complexion' => 'FAIR',
            'eye_color' => 'BROWN',
            'hair_color' => 'BLACK',
            'distinguished_features' => 'NONE',
            'blood_group' => 'O+',
            'profession' => 'PETROLEUM ENGINEER',
            'domicile' => '12 Adeola Odeku Street, Victoria Island, Lagos',
            'passport_number' => $passport,
            'passport_issue_date' => '2022-01-10',
            'passport_expiry' => '2032-01-09',
            'emergency_contact_name' => 'ADAEZE OKAFOR',
            'emergency_contact_relation' => 'COLLEAGUE',
            'emergency_contact_phone' => '+2348031111111',
            'emergency_contact_address' => '5 Awolowo Road, Ikoyi, Lagos',
            'phone' => $applicant->phone,
            'email' => $applicant->email,
            'enrollment_center_id' => $center->id,
            'appointment_date' => $appointment->toDateString(),
            'appointment_time' => '10:00',
            'fee_amount_kobo' => config('nis.fee_naira') * 100,
            'payment_status' => 'PAID',
            'submitted_at' => $submitted,
        ]);
        $application->application_number = $this->numbers->applicationNumber();
        $application->reference_number = $this->numbers->referenceNumber();
        $application->status = ApplicationStatus::PendingApproval;
        $application->save();

        Payment::create([
            'applicant_id' => $applicant->id, 'application_id' => $application->id,
            'reference' => $this->numbers->paymentReference(), 'amount_kobo' => $application->fee_amount_kobo,
            'status' => 'SUCCESS', 'channel' => 'demo', 'paid_at' => $submitted, 'verified_at' => $submitted,
        ]);

        $owner = ['application_id' => $application->id];
        $photo = $this->storage->storeBytes($this->image(240, 300, [200, 170, 140], 'PHOTO'), DocumentType::Photo, $owner, $applicant, 'photo.png');
        foreach ([DocumentType::PassportCopy, DocumentType::ResidenceVisa, DocumentType::QuotaApproval] as $type) {
            $this->storage->storeBytes($this->image(600, 400, [236, 240, 236], 'SAMPLE'), $type, $owner, $applicant, "{$type->value}.png");
        }
        $application->photo_path = $photo->path;
        $application->save();

        $this->history($application, null, ApplicationStatus::PendingApproval, $applicant, 'Submitted online', $submitted);

        if ($target === ApplicationStatus::PendingApproval) {
            return $application;
        }

        $decider = $staff['approver'];
        $decidedAt = $submitted->addDay();
        $notes = $queryNotes ?? 'Approved for physical biometrics capture at the enrollment center.';
        $to = $target === ApplicationStatus::Queried ? ApplicationStatus::Queried : ApplicationStatus::ApprovedForBiometrics;
        $application->forceFill(['decided_by' => $decider->id, 'decided_at' => $decidedAt, 'decision_notes' => $notes]);
        $this->move($application, ApplicationStatus::PendingApproval, $to, $decider, $notes, $decidedAt);

        if (in_array($target, [ApplicationStatus::Queried, ApplicationStatus::ApprovedForBiometrics], true)) {
            return $application;
        }

        // Biometrics captured -> card created (APPROVED = awaiting final approval)
        $issuer = $staff['issuer'];
        $capturedAt = $decidedAt->addDays(2);
        $signature = $this->storage->storeBytes($this->image(300, 100, [255, 255, 255], 'SIGN'), DocumentType::Signature, $owner, $issuer, 'signature.png');

        $cardNumber = $this->numbers->cardNumber();
        $card = new ResidenceCard([
            ...$application->only(Application::PARTICULARS),
            'booklet_number' => $this->numbers->bookletNumber($cardNumber),
            'decision_reference' => $application->application_number,
            'decision_date' => $decidedAt->toDateString(),
            'photo_path' => $photo->path,
            'signature_path' => $signature->path,
            'issuing_officer_id' => $issuer->id,
            'issuing_officer_name' => mb_strtoupper($issuer->fullname),
            'issuing_officer_service_no' => $issuer->service_number,
            'issued_on' => $capturedAt->toDateString(),
            'issued_at' => mb_strtoupper($center->name),
            'expires_on' => $capturedAt->addYears(config('nis.card_validity_years'))->subDay()->toDateString(),
            'authority_signature' => 'COMPTROLLER GENERAL',
            'created_by' => $issuer->id,
            'applicant_id' => $application->applicant_id,
        ]);
        $card->card_number = $cardNumber;
        $card->verification_token = $this->numbers->verificationToken();
        $card->status = CardStatus::Approved;
        $card->save();

        $application->forceFill([
            'signature_path' => $signature->path, 'card_id' => $card->id,
            'biometrics_captured_by' => $issuer->id, 'biometrics_captured_at' => $capturedAt,
        ]);
        $this->move($application, ApplicationStatus::ApprovedForBiometrics, ApplicationStatus::BiometricsCaptured, $issuer,
            "Biometrics captured at {$center->name}", $capturedAt);

        if ($target === ApplicationStatus::BiometricsCaptured) {
            return $application;
        }

        // Card approved, printed, ready, collected
        $card->forceFill(['approved_by' => $decider->id, 'approved_at' => $capturedAt->addDay()]);
        $card->status = CardStatus::Issued;
        $card->save();

        $application->forceFill(['ready_at' => $capturedAt->addDays(3)]);
        $this->move($application, ApplicationStatus::BiometricsCaptured, ApplicationStatus::ReadyForCollection, $issuer,
            'Card ready for collection', $capturedAt->addDays(3));

        $application->forceFill(['collected_at' => $capturedAt->addDays(5), 'collected_by' => $issuer->id]);
        $this->move($application, ApplicationStatus::ReadyForCollection, ApplicationStatus::Issued, $issuer,
            'Card collected by holder', $capturedAt->addDays(5));

        return $application;
    }

    private function move(Application $application, ApplicationStatus $from, ApplicationStatus $to, Model $actor, string $notes, CarbonImmutable $at): void
    {
        $application->status = $to;
        $application->save();
        $this->history($application, $from, $to, $actor, $notes, $at);
    }

    private function history(Application $application, ?ApplicationStatus $from, ApplicationStatus $to, Model $actor, string $notes, CarbonImmutable $at): void
    {
        $row = new ApplicationStatusHistory([
            'application_id' => $application->id, 'from_status' => $from, 'to_status' => $to, 'notes' => $notes,
        ]);
        $row->actor()->associate($actor);
        $row->created_at = $at;
        $row->save();
    }

    private function nextWeekday(CarbonImmutable $date): CarbonImmutable
    {
        while ($date->isWeekend()) {
            $date = $date->addDay();
        }

        return $date;
    }

    /**
     * Placeholder PNG built in pure PHP (no GD needed): a solid background
     * with a darker band so it is visibly an image.
     */
    private function image(int $width, int $height, array $rgb, string $label): string
    {
        $dark = array_map(fn ($c) => max(0, $c - 60), $rgb);
        $raw = '';
        for ($y = 0; $y < $height; $y++) {
            $colour = ($y > $height * 0.4 && $y < $height * 0.6) ? $dark : $rgb;
            $raw .= "\0".str_repeat(pack('C3', ...$colour), $width);
        }

        $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

        return "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            .$chunk('tEXt', "Comment\0NIS-RCIS demo {$label}")
            .$chunk('IDAT', gzcompress($raw))
            .$chunk('IEND', '');
    }

    private function printAccounts(): void
    {
        $this->newLine();
        $this->line('All demo accounts use the password: <comment>'.self::PASSWORD.'</comment>');
        $this->table(['Staff (sign in at /staff)', 'Service no.', 'Role'], [
            ['demo.admin', '10001', 'SuperAdmin'],
            ['demo.approver', '10002', 'ApprovingOfficer'],
            ['demo.issuer', '10003', 'IssuingOfficer'],
            ['demo.inspector', '10004', 'Inspector'],
            ['demo.auditor', '10005', 'Auditor'],
        ]);
        $this->table(['Applicant (sign in at /portal)', 'Application is'], [
            ['john.smith@example.com', 'ISSUED - card collected (try renewal / verification)'],
            ['kwame.mensah@example.com', 'PENDING_APPROVAL'],
            ['amara.diallo@example.com', 'QUERIED - respond to the query'],
            ['li.wei@example.com', 'APPROVED_FOR_BIOMETRICS - appointment today'],
            ['elena.rossi@example.com', 'BIOMETRICS_CAPTURED - card awaiting approval'],
        ]);
    }
}
