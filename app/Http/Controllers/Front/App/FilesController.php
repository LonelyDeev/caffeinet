<?php

namespace App\Http\Controllers\Front\App;

use App\Http\Controllers\Controller;
use App\Models\OrderFile;
use App\Services\Audit\AuditLogger;
use App\Support\SecureFile;
use Illuminate\Http\Request;

/**
 * دانلود مدارک سفارش — فقط با URL موقتِ امضاشده (۶ ساعت اعتبار).
 *
 * فاز ۱۱: فایل روی دیسک رمزنگاری‌شده است و هنگام سرو رمزگشایی
 * می‌شود؛ هر دانلود در لاگ فعالیت ثبت می‌شود.
 */
class FilesController extends Controller
{
    /** GET /files/order/{file}?expires=…&signature=… */
    public function download(Request $request, OrderFile $file)
    {
        abort_unless($request->hasValidSignature(), 403, 'لینک فایل منقضی یا نامعتبر است.');

        $disk = \Illuminate\Support\Facades\Storage::disk(SecureFile::DISK);

        abort_unless($disk->exists($file->path), 404, 'فایل یافت نشد.');

        AuditLogger::log('files.download', $file, null, [
            'order_id' => $file->order_id,
            'name' => $file->original_name,
            'type' => $file->file_type,
        ], 'دانلود مدرک سفارش (لینک امضاشده)');

        $mime = $file->mime ?: 'application/octet-stream';

        return SecureFile::response(
            SecureFile::DISK,
            $file->path,
            $file->original_name ?: 'document',
            $mime,
            str_starts_with((string) $mime, 'image/'),
        );
    }
}
