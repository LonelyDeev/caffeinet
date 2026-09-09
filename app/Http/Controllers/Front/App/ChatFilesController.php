<?php

namespace App\Http\Controllers\Front\App;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use App\Services\Chat\ChatService;
use App\Support\SecureFile;
use Illuminate\Http\Request;

/**
 * دانلود/نمایش فایل‌های گفتگو — فقط با URL موقتِ امضاشده (۶ ساعت اعتبار).
 *
 * فاز ۱۱: فایل روی دیسک رمزنگاری‌شده است و هنگام سرو رمزگشایی
 * می‌شود؛ هر دانلود در لاگ فعالیت ثبت می‌شود.
 * تصویر/صدا/ویدیو به‌صورت درون‌برنامه‌ای (inline) سرو می‌شوند تا
 * مستقیم داخل حباب چت نمایش/پخش شوند؛ سایر فایل‌ها دانلود می‌شوند.
 */
class ChatFilesController extends Controller
{
    public function __construct(protected ChatService $chat) {}

    /** GET /files/chat/{message}?expires=…&signature=… */
    public function download(Request $request, \App\Models\Message $message)
    {
        abort_unless($request->hasValidSignature(), 403, 'لینک فایل منقضی یا نامعتبر است.');
        abort_unless($message->file_path, 404, 'این پیام فایل ندارد.');

        $disk = \Illuminate\Support\Facades\Storage::disk(SecureFile::DISK);

        abort_unless($disk->exists($message->file_path), 404, 'فایل یافت نشد.');

        $meta = (array) ($message->file_meta ?? []);
        $name = $meta['name'] ?? 'chat-file';
        $mime = $meta['mime'] ?? 'application/octet-stream';

        AuditLogger::log('files.download', $message, null, [
            'order_id' => $message->conversation->order_id ?? null,
            'name' => $name,
        ], 'دریافت فایل گفتگو (لینک امضاشده)');

        $inline = in_array($message->message_type, ['image', 'audio', 'video'], true)
            || str_starts_with((string) $mime, 'image/');

        return SecureFile::response(SecureFile::DISK, $message->file_path, $name, $mime, $inline);
    }
}
