<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketUpdated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->input('status', 'active');

        $q = SupportTicket::with(['user:id,name,email', 'assignee:id,name'])
            ->withCount('replies')
            ->when($status === 'active', fn ($q) => $q->whereIn('status', ['open', 'in_progress', 'waiting']))
            ->when(!in_array($status, ['active', 'all'], true), fn ($q) => $q->where('status', $status))
            ->when($request->boolean('mine'), fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('subject', 'like', "%{$s}%")->orWhere('ticket_number', 'like', "%{$s}%")))
            // Urgent first, then oldest waiting — the ticket that has waited longest gets answered first.
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->orderBy('updated_at');

        return response()->json([
            'tickets' => $q->paginate(25),
            'counts' => SupportTicket::selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(
            SupportTicket::with(['user:id,name,email,phone,role,created_at', 'assignee:id,name', 'replies.user:id,name'])->findOrFail($id)
        );
    }

    public function reply(Request $request, string $id): JsonResponse
    {
        $v = $request->validate([
            'body' => 'required|string|max:5000',
            'status' => ['nullable', Rule::in(SupportTicket::STATUSES)],
        ]);

        $ticket = SupportTicket::with('user')->findOrFail($id);
        $reply = $ticket->replies()->create(['user_id' => $request->user()->id, 'body' => $v['body'], 'is_staff' => true]);

        // Replying means we're now waiting on the customer unless told otherwise.
        $ticket->update([
            'status' => $v['status'] ?? 'waiting',
            'assigned_to' => $ticket->assigned_to ?? $request->user()->id,
        ]);

        $ticket->user?->notify(new SupportTicketUpdated($ticket, 'staff_reply'));

        return response()->json($reply->load('user:id,name'), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $v = $request->validate([
            'status' => ['sometimes', Rule::in(SupportTicket::STATUSES)],
            'priority' => ['sometimes', Rule::in(SupportTicket::PRIORITIES)],
            'assigned_to' => ['sometimes', 'nullable', 'exists:users,id'],
        ]);

        if (!empty($v['assigned_to'])) {
            $assignee = User::findOrFail($v['assigned_to']);
            abort_unless($assignee->can('manage_support'), 422, 'That user does not handle support.');
        }

        $ticket = SupportTicket::with('user')->findOrFail($id);
        $statusChanged = isset($v['status']) && $v['status'] !== $ticket->status;
        $ticket->update($v);

        if ($statusChanged && in_array($ticket->status, ['resolved', 'closed'], true)) {
            $ticket->user?->notify(new SupportTicketUpdated($ticket, 'status'));
        }

        return response()->json($ticket->fresh(['user:id,name,email', 'assignee:id,name']));
    }

    public function agents(): JsonResponse
    {
        return response()->json(User::permission('manage_support')->where('is_active', true)->get(['id', 'name']));
    }
}
