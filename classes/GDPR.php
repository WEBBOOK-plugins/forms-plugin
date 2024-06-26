<?php

declare(strict_types=1);

namespace WebBook\Forms\Classes;

use Carbon\Carbon;
use WebBook\Forms\Models\Record;
use WebBook\Forms\Models\Settings;

class GDPR
{
    public static function cleanRecords()
    {
        $gdpr_enable = Settings::get('gdpr_enable', false);
        $gdpr_days = Settings::get('gdpr_days', false);

        if (! $gdpr_enable) {
            \Flash::error(__('GDPR options are disabled'));

            return;
        }

        if ($gdpr_enable && is_numeric($gdpr_days)) {
            $days = Carbon::now()->subDays($gdpr_days);

            return Record::whereDate('created_at', '<', $days)->forceDelete();
        }

        \Flash::error(__('Invalid GDPR days setting value'));
    }
}
