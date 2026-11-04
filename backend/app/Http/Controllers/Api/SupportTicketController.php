<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use App\Support\StaffAlerts;
use Illuminate\Http\Request;

/** Customer side of support: a user sees and replies to only their own tickets. */
class SupportTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            SupportTicket::where('user_id', $request->user()->id)
                ->withCount('replies')
                ->orderByDesc('updated_at')
                ->paginate(20)
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $ticket = SupportTicket::with('replies.user:id,name')
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json($ticket);
    }

    public function store(Request $request): JsonResponse
    {
        $v = $request->validate([
            'subject' => 'required|string|max:200',
            'description' => 'required|string|max:5000',
            'category' => 'nullable|in:account,listing,payment,technical,report,other',
        ]);

        $ticket = SupportTicket::create($v + ['user_id' => $request->user()->id, 'status' => 'open', 'priority' => 'medium']);

        StaffAlerts::send('support', "New support ticket: {$ticket->subject}", $ticket->description,
            StaffAlerts::person($request->user()), "/admin/support?ticket={$ticket->id}",
            ['Ticket' => $ticket->ticket_number, 'Category' => $ticket->category]);

        return response()->json($ticket, 201);
    }

    public function reply(Request $request, string $id): JsonResponse
    {
        $v = $request->validate(['body' => 'required|string|max:5000']);

        $ticket = SupportTicket::where('user_id', $request->user()->id)->findOrFail($id);
        abort_if($ticket->status === 'closed', 422, 'This ticket is closed. Please open a new one.');

        $reply = $ticket->replies()->create(['user_id' => $request->user()->id, 'body' => $v['body'], 'is_staff' => false]);

        // A customer reply re-opens a ticket that was waiting on them or resolved.
        if (in_array($ticket->status, ['waiting', 'resolved'], true)) {
            $ticket->update(['status' => 'open']);
        } else {
            $ticket->touch();
        }

        StaffAlerts::send('support_reply', "Customer replied: {$ticket->subject}", $v['body'],
            StaffAlerts::person($request->user()), "/admin/support?ticket={$ticket->id}",
            ['Ticket' => $ticket->ticket_number, 'Assigned to' => $ticket->assignee?->name]);

        return response()->json($reply->load('user:id,name'), 201);
    }

    /**
     * Public contact form. A hidden "website" field catches bots (humans never
     * see it); a signed-in visitor's message is attached to their account.
     */
    public function contact(Request $request): JsonResponse
    {
        $v = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190',
            'subject' => 'nullable|string|max:200',
            'message' => 'required|string|min:10|max:5000',
            'website' => 'nullable|max:0',
        ], ['website.max' => 'Message rejected.']);

        $user = auth('sanctum')->user();

        $ticket = SupportTicket::create([
            'user_id' => $user?->id,
            'guest_name' => $user ? null : $v['name'],
            'guest_email' => $user ? null : $v['email'],
            'source' => 'contact_form',
            'subject' => ($v['subject'] ?? null) ?: 'Contact form message',
            'description' => $v['message'],
            'category' => 'other',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        StaffAlerts::send('contact', 'Contact form: '.(($v['subject'] ?? null) ?: 'new message'), $v['message'],
            ['name' => $v['name'], 'email' => $v['email'], 'phone' => $user?->phone], "/admin/support?ticket={$ticket->id}",
            ['Reference' => $ticket->ticket_number, 'Signed in' => $user ? 'yes' : 'no (guest)']);

        return response()->json(['message' => 'Thanks — we received your message and will reply by email.', 'reference' => $ticket->ticket_number], 201);
    }

    public static function supportStaff()
    {
        return User::permission('manage_support')->where('is_active', true)->get();
    }
}
