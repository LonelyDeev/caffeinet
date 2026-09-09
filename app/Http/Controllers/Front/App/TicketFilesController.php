<?php

namespace App\Http\Controllers\Front\App;

use App\Http\Controllers\Controller;
use App\Models\TicketMessage;
use App\Services\Audit\AuditLogger;
use App\Support\SecureFile;
use Illuminate\Http\Request;

/**
 * دانلود پیوست تیکت پشتیبانی — فقط با URL موقتِ امضاشده (۶ ساعت).
 *
 * فاز ۱۱: فایل روی دیسک رمزنگاری‌شده است و هنگام سرو رمزگشایی
 * می‌شود؛ هر دانلود در لاگ فعالیت ثبت می‌شود.
 */
class TicketFilesController extends Controller
{
    /** GET /files/ticket/{message}?expires=…&signature=… */
    public function download(Request $request, TicketMessage $message)
    {
        abort_unless($request->hasValidSignature(), 403, 'لینک فایل منقضی یا نامعتبر است.');

        $attachments = (array) ($message->attachments ?? []);

        abort_unless(! empty($attachments[0]['path'] ?? null), 404, 'این پیام پیوست ندارد.');

        $file = (array) $attachments[0];

        $disk = \Illuminate\Support\Facades\Storage::disk(SecureFile::DISK);

        abort_unless($disk->exists($file['path']), 404, 'فایل یافت نشد.');

        $name = $file['name'] ?? 'ticket-file';
        $mime = $file['mime'] ?? 'application/octet-stream';

        AuditLogger::log('files.download', $message, null, [
            'ticket_id' => $message->ticket_id,
            'name' => $name,
        ], 'دریافت پیوست تیکت (لینک امضاشده)');

        // تصاویر درون‌برنامه‌ای نمایش داده شوند
        return SecureFile::response(
            SecureFile::DISK,
            $file['path'],
            $name,
            $mime,
            str_starts_with((string) $mime, 'image/'),
        );
    }
}
