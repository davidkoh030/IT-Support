<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\TicketAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TicketAttachmentController extends Controller
{
    public function show(Request $request, TicketAttachment $attachment)
    {
        $this->authorize('view', $attachment->ticket);

        if ($attachment->comment_id !== null && $attachment->comment->isInternal()) {
            $this->authorize('viewInternalNotes', $attachment->ticket);
        }

        AuditLog::record($request->user(), 'attachment.downloaded', $attachment->ticket, null, ['attachment_id' => $attachment->id]);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_filename);
    }
}
