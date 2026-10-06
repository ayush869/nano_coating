<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Sequential document numbers per Indian financial year (April–March):
 * QT/2026-27/0001, INV/2026-27/0001.
 */
class DocumentNumber
{
    public static function financialYear(CarbonInterface $date): string
    {
        $start = $date->month >= 4 ? $date->year : $date->year - 1;

        return $start.'-'.str_pad((string) (($start + 1) % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function next(string $prefix, CarbonInterface $date): string
    {
        $key = $prefix.'/'.self::financialYear($date);

        $n = DB::transaction(function () use ($key) {
            $row = DB::table('sequences')->where('key', $key)->lockForUpdate()->first();
            if (! $row) {
                DB::table('sequences')->insert(['key' => $key, 'last' => 1]);

                return 1;
            }
            DB::table('sequences')->where('key', $key)->update(['last' => $row->last + 1]);

            return $row->last + 1;
        });

        return $key.'/'.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
