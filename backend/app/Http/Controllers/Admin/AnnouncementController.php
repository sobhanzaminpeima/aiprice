<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendAnnouncement;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AnnouncementController extends Controller
{
    public function index(): JsonResponse
    {
        $since = now()->subDays(30);
        $recent = DB::table('notifications')->where('created_at', '>=', $since);

        return response()->json([
            'announcements' => Announcement::with('sender:id,name')->orderByDesc('created_at')->paginate(20),
            'stats' => [
                'sent_30d' => Announcement::where('status', 'sent')->where('sent_at', '>=', $since)->count(),
                'recipients_30d' => (int) Announcement::where('sent_at', '>=', $since)->sum('recipients_count'),
                'notifications_30d' => (clone $recent)->count(),
                'read_rate_30d' => $this->readRate(clone $recent),
            ],
            'by_kind_30d' => (clone $recent)->get(['data'])
                ->countBy(fn ($n) => json_decode($n->data, true)['kind'] ?? 'other')
                ->sortDesc(),
        ]);
    }

    public function audienceSize(Request $request): JsonResponse
    {
        $v = $request->validate(['audience' => ['required', Rule::in(Announcement::AUDIENCES)]]);
        return response()->json(['count' => (new Announcement(['audience' => $v['audience']]))->recipientsQuery()->count()]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = $request->validate([
            'title' => 'required|string|max:150',
            'body' => 'required|string|max:2000',
            'link' => 'nullable|url|max:500',
            'audience' => ['required', Rule::in(Announcement::AUDIENCES)],
            'send_email' => 'boolean',
        ]);

        $announcement = Announcement::create([
            'user_id' => $request->user()->id,
            'title' => $v['title'],
            'body' => $v['body'],
            'link' => $v['link'] ?? null,
            'audience' => $v['audience'],
            'channels' => ($v['send_email'] ?? false) ? ['database', 'mail'] : ['database'],
            'status' => 'queued',
        ]);

        SendAnnouncement::dispatch($announcement);

        return response()->json($announcement, 202);
    }

    private function readRate($query): ?float
    {
        $total = (clone $query)->count();
        if ($total === 0) {
            return null;
        }
        return round((clone $query)->whereNotNull('read_at')->count() / $total * 100, 1);
    }
}
