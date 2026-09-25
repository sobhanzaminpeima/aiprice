<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV exports. Each report is streamed in chunks so a large export never has
 * to fit in memory, and each has its own permission on top of view_analytics
 * because exports carry personal data.
 */
class ReportController extends Controller
{
    private const REPORTS = [
        'listings' => ['label' => 'Listings', 'permission' => 'manage_listings', 'description' => 'Every listing with price, status and AI scores'],
        'users' => ['label' => 'Users', 'permission' => 'manage_users', 'description' => 'Accounts with role, verification and activity'],
        'payments' => ['label' => 'Payments', 'permission' => 'manage_payments', 'description' => 'Transactions with gateway and status'],
        'inquiries' => ['label' => 'Buyer inquiries', 'permission' => 'manage_support', 'description' => 'Inquiries and their handling status'],
    ];

    public function index(Request $request): JsonResponse
    {
        return response()->json(collect(self::REPORTS)->map(fn ($r, $key) => [
            'key' => $key,
            'label' => $r['label'],
            'description' => $r['description'],
            'allowed' => $request->user()->can($r['permission']),
        ])->values());
    }

    public function export(Request $request, string $report): StreamedResponse
    {
        abort_unless(isset(self::REPORTS[$report]), 404);
        abort_unless($request->user()->can(self::REPORTS[$report]['permission']), 403, 'You do not have access to this export.');

        $v = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $from = $v['from'] ?? null;
        $to = isset($v['to']) ? $v['to'].' 23:59:59' : null;

        [$headers, $query, $row] = $this->definition($report);
        $query->when($from, fn ($q) => $q->where('created_at', '>=', $from))
              ->when($to, fn ($q) => $q->where('created_at', '<=', $to));

        $filename = "aiprice-{$report}-".now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($headers, $query, $row) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel opens UTF-8 (Turkish/Persian names) correctly
            fputcsv($out, $headers);
            $query->chunk(500, function ($items) use ($out, $row) {
                foreach ($items as $item) {
                    fputcsv($out, array_map([$this, 'safeCell'], $row($item)));
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function definition(string $report): array
    {
        return match ($report) {
            'listings' => [
                ['Number', 'Type', 'Title', 'City', 'Price', 'Currency', 'Status', 'Owner email', 'Trust', 'Deal', 'Price status', 'Views', 'Created'],
                Listing::with('user:id,email')->orderBy('created_at'),
                fn ($l) => [$l->listing_number, $l->type, $l->title, $l->city, $l->price, $l->currency, $l->status, $l->user?->email,
                    $l->ai_trust_score, $l->ai_deal_score, $l->ai_price_status, $l->view_count, $l->created_at],
            ],
            'users' => [
                ['Name', 'Email', 'Role', 'Active', 'Email verified', 'KYC verified', 'Last login', 'Created'],
                User::query()->orderBy('created_at'),
                fn ($u) => [$u->name, $u->email, $u->role, $u->is_active ? 'yes' : 'no', $u->email_verified_at ? 'yes' : 'no',
                    $u->kyc_verified ? 'yes' : 'no', $u->last_login_at, $u->created_at],
            ],
            'payments' => [
                ['Number', 'User email', 'Amount', 'Currency', 'Status', 'Gateway', 'Gateway ref', 'Created'],
                Payment::with('user:id,email')->orderBy('created_at'),
                fn ($p) => [$p->payment_number, $p->user?->email, $p->amount, $p->currency, $p->status, $p->gateway, $p->gateway_payment_id, $p->created_at],
            ],
            'inquiries' => [
                ['Listing', 'Buyer email', 'Status', 'Message', 'Created'],
                Inquiry::with(['listing:id,title', 'buyer:id,email'])->orderBy('created_at'),
                fn ($i) => [$i->listing?->title, $i->buyer?->email, $i->status, $i->message, $i->created_at],
            ],
        };
    }

    /**
     * Neutralise CSV formula injection: a cell a user controls (a title, a
     * name) starting with = + - @ would otherwise execute when opened in Excel.
     */
    public function safeCell($value): string
    {
        $value = (string) $value;
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
