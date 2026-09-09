<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * خروجی CSV استریمی — فاز ۹ (گزارش تحلیلی با خروجی).
 *
 * BOM در ابتدای فایل تا اکسل متن فارسی را UTF-8 تشخیص دهد؛
 * نام فایل ASCII (سازگار با همهٔ مرورگرها) + filename* فارسی.
 */
class Csv
{
    /**
     * خروجی CSV دانلودی.
     *
     * @param array<int,string> $headers سرستون‌ها
     * @param iterable<array<int,mixed>>|\Closure():iterable $rows ردیف‌ها
     *        (تولید تنبل — Generator یا Closure تولیدکنندهٔ آن، برای جلوگیری از کمبود حافظه)
     */
    public static function download(string $filename, array $headers, iterable|\Closure $rows): StreamedResponse
    {
        $filename = str_replace(['"', "\n", "\r"], '', $filename);

        $response = new StreamedResponse(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');

            // BOM — تشخیص UTF-8 در اکسل و LibreOffice
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, $headers);

            $iterator = $rows instanceof \Closure ? $rows() : $rows;

            foreach ($iterator as $row) {
                fputcsv($out, array_map(
                    fn ($v) => is_string($v) ? str_replace(["\r", "\n", "\t"], ' ', $v) : $v,
                    array_values($row)
                ));
            }

            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
