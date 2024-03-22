<?php

declare(strict_types=1);

namespace WebBook\Forms\Models;

use October\Rain\Database\Model;

class Settings extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $implement = ['System.Behaviors.SettingsModel'];

    public string $settingsCode = 'webbook_forms_settings';
    public string $settingsFields = 'fields.yaml';

    public $rules = [
        'gdpr_days' => 'required|numeric',
    ];

    public array $attributeNames = [
        'gdpr_days' => 'GDPR',
    ];
}
