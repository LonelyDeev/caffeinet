<?php

namespace App\Http\Controllers\Back\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * فاز ۱۵ — مدیریت اطلاعیه‌های سامانه (متن / تصویر / ویدیو).
 *
 * ارسال به: مشتریان، پنل‌ها (هر نقش جدا یا همه) یا همه.
 * نمایش: مودال زیبا در مقصد + ثبت «دیده‌شده» per-user.
 */
class AnnouncementsController extends Controller
{
    /** حداکثر حجم فایل رسانه (KB) */
    protected const MAX_MEDIA_KB = 15360; // 15MB

    public function index(): View
    {
        return view('back.admin.announcements.index', [
            'audiences' => Announcement::AUDIENCES,
        ]);
    }

    /** لیست اطلاعیه‌ها (AJAX + صفحه‌بندی) */
    public function data(Request $request): JsonResponse
    {
        $paginator = Announcement::query()
            ->with('creator:id,name,family')
            ->withCount('reads')
            ->orderByDesc('id')
            ->paginate(20);

        $rows = $paginator->through(fn (Announcement $a) => [
            'id' => $a->id,
            'title' => $a->title,
            'body' => $a->body,
            'media_type' => $a->media_type,
            'media_url' => match ($a->media_type) {
                'image' => $a->mediaUrl(),
                'video' => $a->videoUrl(),
                default => null,
            },
            'is_video_upload' => $a->media_type === 'video' && (bool) $a->media_path,
            'audience' => $a->audience,
            'audience_label' => $a->audienceLabel(),
            'is_active' => (bool) $a->is_active,
            'in_window' => $a->inWindow(),
            'starts_at_label' => $a->starts_at ? fa_date($a->starts_at, 'Y/m/d H:i') : null,
            'ends_at_label' => $a->ends_at ? fa_date($a->ends_at, 'Y/m/d H:i') : null,
            'creator' => trim(($a->creator?->name ?? 'سیستم').' '.($a->creator?->family ?? '')),
            'reads_count' => (int) $a->reads_count,
            'created_at_label' => fa_date($a->created_at, 'Y/m/d H:i'),
        ]);

        return response()->json($rows);
    }

    /** ساختار آمادهٔ ویرایش یک اطلاعیه (AJAX) */
    public function show(Announcement $announcement): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'body' => $announcement->body,
                'media_type' => $announcement->media_type,
                'media_url' => match ($announcement->media_type) {
                    'image' => $announcement->mediaUrl(),
                    'video' => $announcement->videoUrl(),
                    default => null,
                },
                'is_video_upload' => $announcement->media_type === 'video' && (bool) $announcement->media_path,
                'video_url' => $announcement->video_url,
                'audience' => $announcement->audience,
                'is_active' => (bool) $announcement->is_active,
                'starts_at' => $announcement->starts_at?->format('Y-m-d\TH:i'),
                'ends_at' => $announcement->ends_at?->format('Y-m-d\TH:i'),
            ],
        ]);
    }

    /** ایجاد (AJAX — multipart برای رسانه) */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $media = $this->receiveMedia($request);

        $announcement = Announcement::create([
            ...$data,
            'media_type' => $media['type'],
            'media_path' => $media['path'],
            'video_url' => $media['video_url'],
            'created_by' => $request->user()->id,
        ]);

        AuditLogger::log('announcement.created', $announcement, null, [
            'title' => $announcement->title,
            'audience' => $announcement->audience,
            'media_type' => $announcement->media_type,
        ], 'ایجاد اطلاعیه «'.$announcement->title.'» ('.$announcement->audienceLabel().')');

        return response()->json([
            'message' => 'اطلاعیه «'.$announcement->title.'» ایجاد و برای مخاطبان ارسال شد.',
            'id' => $announcement->id,
        ], 201);
    }

    /** ویرایش (AJAX — multipart) */
    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        $data = $this->validated($request);

        $old = $announcement->only(['title', 'audience', 'media_type', 'is_active']);

        $media = $this->receiveMedia($request, $announcement);

        $announcement->fill([
            ...$data,
            'media_type' => $media['type'],
            'media_path' => $media['path'],
            'video_url' => $media['video_url'],
        ])->save();

        // اگر مخاطب/محتوا تغییر کرده، وضعیت خوانده‌شدن‌ها حفظ می‌شود (دیده‌شده‌ها مجدد نمی‌بینند)

        AuditLogger::log('announcement.updated', $announcement, $old, [
            'title' => $announcement->title,
            'audience' => $announcement->audience,
            'media_type' => $announcement->media_type,
        ], 'ویرایش اطلاعیه «'.$announcement->title.'»');

        return response()->json([
            'message' => 'اطلاعیه «'.$announcement->title.'» بروزرسانی شد.',
        ]);
    }

    /** فعال/غیرفعال سریع (AJAX) */
    public function toggle(Announcement $announcement): JsonResponse
    {
        $announcement->update(['is_active' => ! $announcement->is_active]);

        AuditLogger::log('announcement.toggled', $announcement,
            ['is_active' => ! $announcement->is_active],
            ['is_active' => $announcement->is_active],
            ($announcement->is_active ? 'فعال‌سازی' : 'غیرفعال‌سازی').' اطلاعیه «'.$announcement->title.'»');

        return response()->json([
            'message' => $announcement->is_active ? 'اطلاعیه فعال شد.' : 'اطلاعیه غیرفعال شد.',
            'is_active' => $announcement->is_active,
        ]);
    }

    /** حذف (AJAX) */
    public function destroy(Announcement $announcement): JsonResponse
    {
        $title = $announcement->title;

        if ($announcement->media_path) {
            Storage::disk('public')->delete($announcement->media_path);
        }

        $announcement->delete();

        AuditLogger::log('announcement.deleted', null, ['title' => $title], null, 'حذف اطلاعیه «'.$title.'»');

        return response()->json(['message' => 'اطلاعیه «'.$title.'» حذف شد.']);
    }

    /* ----------------------------------------------------------------
     |  داخلی
     * ---------------------------------------------------------------- */

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['nullable', 'string', 'max:4000'],
            'audience' => ['required', 'string', 'in:'.implode(',', array_keys(Announcement::AUDIENCES))],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'string', 'max:40'],
            'ends_at' => ['nullable', 'string', 'max:40'],
            'video_url' => ['nullable', 'string', 'max:500', 'url'],
            'media_type' => ['nullable', 'string', 'in:none,image,video'],
            'remove_media' => ['nullable', 'boolean'],
        ], [
            'title.required' => 'عنوان اطلاعیه الزامی است.',
            'title.max' => 'عنوان حداکثر ۱۵۰ کاراکتر است.',
            'audience.required' => 'انتخاب مخاطب الزامی است.',
            'video_url.url' => 'آدرس ویدیو باید یک لینک معتبر باشد.',
        ]);

        $starts = $data['starts_at'] ?? null;
        $ends = $data['ends_at'] ?? null;

        $data['starts_at'] = $starts ? jalali_or_iso_to_carbon($starts, '00:00') : null;
        $data['ends_at'] = $ends ? jalali_or_iso_to_carbon($ends, '23:59') : null;

        if ($data['starts_at'] && $data['ends_at'] && $data['ends_at']->lt($data['starts_at'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'ends_at' => ['پایان پنجرهٔ نمایش نمی‌تواند قبل از شروع باشد.'],
            ]);
        }

        $data['is_active'] = filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $data['body'] = $data['body'] ?? null;
        unset($data['starts_at_raw'], $data['ends_at_raw']);

        return $data;
    }

    /**
     * دریافت رسانه (multipart): image یا video.
     * خروجی: [type, path, video_url]
     */
    protected function receiveMedia(Request $request, ?Announcement $existing = null): array
    {
        $type = (string) $request->input('media_type', 'none');
        $removeMedia = filter_var($request->input('remove_media', false), FILTER_VALIDATE_BOOLEAN);
        $videoUrl = trim((string) $request->input('video_url', '')) ?: null;

        // حذف رسانهٔ قبلی در صورت درخواست یا جایگزینی
        $oldPath = $existing?->media_path;

        if ($type === 'none') {
            if ($oldPath) {
                Storage::disk('public')->delete($oldPath);
            }

            return ['type' => 'none', 'path' => null, 'video_url' => null];
        }

        if ($type === 'image') {
            if ($request->hasFile('media_file')) {
                $request->validate([
                    'media_file' => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:'.self::MAX_MEDIA_KB],
                ], [
                    'media_file.image' => 'فایل انتخاب‌شده تصویر معتبر نیست.',
                    'media_file.mimes' => 'فرمت تصویر باید JPG، PNG، WebP یا GIF باشد.',
                    'media_file.max' => 'حجم تصویر حداکثر ۱۵ مگابایت است.',
                ]);

                if ($oldPath) {
                    Storage::disk('public')->delete($oldPath);
                }

                $path = $request->file('media_file')->store('announcements', 'public');

                return ['type' => 'image', 'path' => $path, 'video_url' => null];
            }

            if ($removeMedia && $oldPath) {
                Storage::disk('public')->delete($oldPath);

                return ['type' => 'none', 'path' => null, 'video_url' => null];
            }

            // حفظ تصویر قبلی
            return ['type' => $existing?->media_path ? 'image' : 'none', 'path' => $existing?->media_path, 'video_url' => null];
        }

        // video
        if ($request->hasFile('media_file')) {
            $request->validate([
                'media_file' => ['mimetypes:video/mp4,video/webm,video/quicktime', 'max:'.self::MAX_MEDIA_KB],
            ], [
                'media_file.mimetypes' => 'فرمت ویدیو باید MP4، WebM یا MOV باشد.',
                'media_file.max' => 'حجم ویدیو حداکثر ۱۵ مگابایت است.',
            ]);

            if ($oldPath) {
                Storage::disk('public')->delete($oldPath);
            }

            $path = $request->file('media_file')->store('announcements', 'public');

            return ['type' => 'video', 'path' => $path, 'video_url' => null];
        }

        if ($removeMedia && $oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        // ویدیوی لینکی (یا حفظ آپلود قبلی)
        if ($videoUrl) {
            $keepUpload = ! $removeMedia && $existing?->media_type === 'video' && $existing?->media_path;

            return ['type' => 'video', 'path' => $keepUpload ? $existing->media_path : null, 'video_url' => $videoUrl];
        }

        if (! $removeMedia && $existing?->media_type === 'video' && $existing?->media_path) {
            return ['type' => 'video', 'path' => $existing->media_path, 'video_url' => $existing->video_url];
        }

        return ['type' => 'video', 'path' => null, 'video_url' => $videoUrl];
    }
}
