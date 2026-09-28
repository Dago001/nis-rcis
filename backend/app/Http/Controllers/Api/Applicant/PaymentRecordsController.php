<?php

namespace App\Http\Controllers\Api\Applicant;

use App\Models\Applicant;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\PaystackGateway;
use App\Services\RefundService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The applicant's payments: PDF receipts and refund requests.
 */
class PaymentRecordsController
{
    public function index(Request $request, RefundService $refunds): JsonResponse
    {
        $applicant = $this->applicant($request);
        $payments = Payment::with('application:id,application_number,status')->where('applicant_id', $applicant->id)
            ->whereIn('status', ['SUCCESS', 'REFUNDED'])->latest('id')->get();
        $requests = Refund::where('applicant_id', $applicant->id)->get()->keyBy('payment_id');

        return response()->json(['data' => $payments->map(fn (Payment $p) => [
            'reference' => $p->reference,
            'amount_naira' => $p->amount_kobo / 100,
            'status' => $p->status,
            'paid_at' => $p->paid_at?->toIso8601String(),
            'application' => $p->application ? ['id' => $p->application->id, 'application_number' => $p->application->application_number, 'status' => $p->application->status->value] : null,
            'refundable' => $refunds->eligible($p),
            'refund' => ($r = $requests->get($p->id)) ? ['status' => $r->status, 'reason' => $r->reason, 'decision_notes' => $r->decision_notes, 'requested_at' => $r->created_at?->toIso8601String()] : null,
        ])]);
    }

    public function receipt(Request $request, string $reference): Response
    {
        $applicant = $this->applicant($request);
        $payment = Payment::with('application')->where('reference', $reference)->where('applicant_id', $applicant->id)
            ->whereIn('status', ['SUCCESS', 'REFUNDED'])->firstOrFail();

        $options = new Options;
        $options->setChroot(public_path());
        $options->setIsRemoteEnabled(false);
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('pdf.receipt', [
            'payment' => $payment, 'applicant' => $applicant, 'application' => $payment->application,
            'receipt' => PaystackGateway::receipt($payment), 'logo' => public_path('images/nis-logo.png'),
        ])->render());
        $pdf->setPaper('A4');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="NIS-receipt-'.$payment->reference.'.pdf"',
        ]);
    }

    public function requestRefund(Request $request, RefundService $refunds): JsonResponse
    {
        $data = $request->validate([
            'payment_reference' => ['required', 'string', 'max:64'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        $applicant = $this->applicant($request);
        $payment = Payment::where('reference', $data['payment_reference'])->where('applicant_id', $applicant->id)->firstOrFail();
        $refund = $refunds->request($applicant, $payment, $data['reason']);

        return response()->json(['message' => 'Refund requested. You will be told the decision by e-mail.', 'status' => $refund->status], 201);
    }

    private function applicant(Request $request): Applicant
    {
        return $request->user();
    }
}
