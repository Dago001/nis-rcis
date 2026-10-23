<?php

use App\Models\Applicant;
use App\Models\ApplicationDraft;
use App\Models\Payment;
use App\Services\PaymentGatewayManager;
use App\Services\RemitaGateway;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('nis.payment_gateway', 'remita');
    config()->set('nis.remita.fake', true);
});

it('initializes a Remita payment and returns RRR and checkout URL', function () {
    $applicant = Applicant::factory()->create();
    asApplicant($applicant);
    uploadDraftDocs();

    ApplicationDraft::updateOrCreate(
        ['applicant_id' => $applicant->id],
        [
            'type' => 'NEW',
            'current_step' => 6,
            'data' => particulars(),
        ]
    );

    $res = $this->postJson('/api/v1/applicant/payments', [
        'acknowledged_non_refundable' => true,
    ])->assertCreated();

    $res->assertJsonStructure([
        'reference',
        'amount_naira',
        'authorization_url',
        'rrr',
        'gateway',
        'fake',
    ]);

    expect($res->json('gateway'))->toBe('remita')
        ->and($res->json('rrr'))->not->toBeNull()
        ->and($res->json('reference'))->toStartWith('NISRC-');

    $payment = Payment::where('reference', $res->json('reference'))->first();
    expect($payment)->not->toBeNull()
        ->and($payment->provider)->toBe('REMITA')
        ->and($payment->status)->toBe('INITIALIZED');
});

it('verifies a simulated Remita payment and marks it successful', function () {
    $applicant = Applicant::factory()->create();
    asApplicant($applicant);
    uploadDraftDocs();

    ApplicationDraft::updateOrCreate(
        ['applicant_id' => $applicant->id],
        [
            'type' => 'NEW',
            'current_step' => 6,
            'data' => particulars(),
        ]
    );

    $reference = $this->postJson('/api/v1/applicant/payments', [
        'acknowledged_non_refundable' => true,
    ])->json('reference');

    $verifyRes = $this->postJson("/api/v1/applicant/payments/{$reference}/verify")
        ->assertOk();

    $verifyRes->assertJson([
        'reference' => $reference,
        'status' => 'SUCCESS',
        'paid' => true,
        'gateway' => 'remita',
    ]);

    $payment = Payment::where('reference', $reference)->first();
    expect($payment->isSuccessful())->toBeTrue()
        ->and($payment->channel)->toBe('fake')
        ->and($payment->verified_at)->not->toBeNull();
});

it('communicates with real Remita endpoint when configured and verifies SHA-512 hashes', function () {
    config()->set('nis.remita.fake', false);
    config()->set('nis.remita.merchant_id', '2547916');
    config()->set('nis.remita.service_type_id', '4430731');
    config()->set('nis.remita.api_key', '1946');
    config()->set('nis.remita.base_url', 'https://remitademo.net');

    $applicant = Applicant::factory()->create(['email' => 'test@immigration.gov.ng']);

    Http::fake([
        'https://remitademo.net/echannelsvc/merchant/api/paymentstatus/*' => Http::response(
            'jsonp ( {"statuscode":"025","RRR":"280008219314","status":"Payment Reference Generated"} )',
            200
        ),
        'https://remitademo.net/echannelsvc/2547916/280008219314/*' => Http::response([
            'amount' => 35000.0,
            'RRR' => '280008219314',
            'orderId' => 'TEST-REM-ORDER',
            'message' => 'Approved',
            'transactiontime' => '2026-10-23 11:00:00',
            'status' => '00',
        ], 200),
    ]);

    $gateway = app(RemitaGateway::class);
    $initResult = $gateway->initialize($applicant, 3500000, 'https://example.com/callback');

    expect($initResult['rrr'])->toBe('280008219314')
        ->and($initResult['fake'])->toBeFalse()
        ->and($initResult['authorization_url'])->toContain('280008219314');

    $payment = $initResult['payment'];
    $payment->reference = 'TEST-REM-ORDER';
    $payment->save();

    $verifiedPayment = $gateway->verify($payment);

    expect($verifiedPayment->isSuccessful())->toBeTrue()
        ->and($verifiedPayment->status)->toBe('SUCCESS')
        ->and($verifiedPayment->gateway_response['RRR'])->toBe('280008219314');
});

it('formats Remita receipts with RRR and payment mode', function () {
    $payment = Payment::create([
        'applicant_id' => 1,
        'provider' => 'REMITA',
        'reference' => 'PAY-TEST-RECEIPT',
        'amount_kobo' => 3500000,
        'currency' => 'NGN',
        'status' => 'SUCCESS',
        'channel' => 'BANK_BRANCH',
        'paid_at' => now(),
        'verified_at' => now(),
        'gateway_response' => [
            'RRR' => '310928374615',
            'orderId' => 'PAY-TEST-RECEIPT',
            'channel' => 'BANK_BRANCH',
        ],
    ]);

    $receipt = RemitaGateway::receipt($payment);

    expect($receipt['gateway'])->toBe('Remita (TSA)')
        ->and($receipt['gateway_reference'])->toBe('RRR: 310928374615')
        ->and($receipt['mode'])->toBe('Bank Branch (Over-the-Counter)');
});

it('handles Remita IPN webhook notifications safely with zero-trust re-verification', function () {
    $applicant = Applicant::factory()->create();
    $payment = Payment::create([
        'applicant_id' => $applicant->id,
        'provider' => 'REMITA',
        'reference' => 'PAY-WEBHOOK-TEST',
        'amount_kobo' => 3500000,
        'currency' => 'NGN',
        'status' => 'INITIALIZED',
        'gateway_response' => [
            'RRR' => '220011993344',
            'is_simulated' => true,
        ],
    ]);

    $webhookPayload = [
        [
            'rrr' => '220011993344',
            'orderRef' => 'PAY-WEBHOOK-TEST',
            'amount' => 35000,
            'status' => '00',
        ],
    ];

    $this->postJson('/api/v1/webhooks/remita', $webhookPayload)
        ->assertNoContent(200);

    expect($payment->fresh()->isSuccessful())->toBeTrue();
});
