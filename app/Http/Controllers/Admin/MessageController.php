<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Services\ActivityLog\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Content → Messages (Phase 11A): messages from Contact form blocks, kept 90 days.
 */
class MessageController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->query('show') === 'unread' ? 'unread' : 'all';

        return view('admin.messages.index', [
            'messages' => ContactMessage::query()->when($filter === 'unread', fn ($q) => $q->whereNull('read_at'))->latest('id')->paginate(25)->withQueryString(),
            'filter' => $filter,
            'unread' => ContactMessage::query()->whereNull('read_at')->count(),
        ]);
    }

    public function show(ContactMessage $message): View
    {
        if ($message->read_at === null) {
            $message->forceFill(['read_at' => now()])->save();
        }

        return view('admin.messages.show', ['message' => $message]);
    }

    public function unread(ContactMessage $message): RedirectResponse
    {
        $message->forceFill(['read_at' => null])->save();

        return redirect()->route('admin.messages.index')->with('success', __('Marked as unread.'));
    }

    public function destroy(Request $request, ContactMessage $message, ActivityLogger $logger): RedirectResponse
    {
        $message->delete();
        $logger->log('contact_message.deleted', null, ['id' => $message->id], $request->user(), 'Message from '.$message->name);

        return redirect()->route('admin.messages.index')->with('success', __('Message deleted.'));
    }
}
