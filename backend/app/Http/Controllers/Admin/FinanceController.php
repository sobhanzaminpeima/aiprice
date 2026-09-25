<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    public function overview(): JsonResponse
    {
        $now = CarbonImmutable::now();
        $thisMonth = $now->startOfMonth();
        $lastMonth = $thisMonth->subMonth();

        $revenue = fn ($from, $to) => (float) Payment::where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])->sum('amount');

        $revThis = $revenue($thisMonth, $now);
        $revLast = $revenue($lastMonth, $thisMonth->subSecond());

        $active = Subscription::with('plan:id,name')->where('status', 'active')->get();
        // Normalise yearly plans to a monthly figure so MRR is comparable.
        $mrr = $active->sum(fn ($s) => $s->billing_cycle === 'yearly' ? $s->amount / 12 : $s->amount);

        $newSubsThis = Subscription::where('created_at', '>=', $thisMonth)->count();
        $newSubsLast = Subscription::whereBetween('created_at', [$lastMonth, $thisMonth->subSecond()])->count();

        $churnedThis = Subscription::whereIn('status', ['expired', 'cancelled'])->where('updated_at', '>=', $thisMonth)->count();
        $activeAtStart = max(1, $active->count() + $churnedThis - $newSubsThis);

        // Last 6 calendar months, including empty ones, so the chart has no gaps.
        $months = collect(range(5, 0))->map(fn ($i) => $thisMonth->subMonths($i));
        $byMonth = Payment::where('status', 'completed')
            ->where('created_at', '>=', $months->first())
            ->get(['amount', 'created_at'])
            ->groupBy(fn ($p) => $p->created_at->format('Y-m'))
            ->map(fn ($g) => round((float) $g->sum('amount'), 2));

        $planCounts = $active->groupBy(fn ($s) => $s->plan?->name ?? 'Unknown')->map->count()->sortDesc();

        return response()->json([
            'currency' => 'GBP',
            'stats' => [
                'revenue_this_month' => round($revThis, 2),
                'revenue_change_pct' => $this->pctChange($revThis, $revLast),
                'mrr' => round($mrr, 2),
                'active_subscriptions' => $active->count(),
                'new_subscriptions' => $newSubsThis,
                'new_subscriptions_change_pct' => $this->pctChange($newSubsThis, $newSubsLast),
                'churn_rate_pct' => round($churnedThis / $activeAtStart * 100, 1),
                'failed_payments_this_month' => Payment::where('status', 'failed')->where('created_at', '>=', $thisMonth)->count(),
            ],
            'monthly_revenue' => $months->map(fn ($m) => [
                'month' => $m->format('M'),
                'key' => $m->format('Y-m'),
                'amount' => $byMonth[$m->format('Y-m')] ?? 0,
            ])->values(),
            'plan_breakdown' => $planCounts->map(fn ($count, $name) => ['name' => $name, 'count' => $count])->values(),
        ]);
    }

    public function payments(Request $request): JsonResponse
    {
        $q = Payment::with(['user:id,name,email', 'subscription.plan:id,name'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->gateway, fn ($q, $g) => $q->where('gateway', $g))
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('payment_number', 'like', "%{$s}%")
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%"));
            }))
            ->orderByDesc('created_at');

        return response()->json($q->paginate(25));
    }

    private function pctChange(float $now, float $before): ?float
    {
        if ($before == 0.0) {
            return $now > 0 ? null : 0.0; // null = "new", no baseline to compare
        }
        return round(($now - $before) / $before * 100, 1);
    }
}
