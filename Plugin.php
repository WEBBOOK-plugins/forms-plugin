<?php

declare(strict_types=1);

namespace WebBook\Forms;

use Backend\Facades\Backend;
use System\Classes\PluginBase;
use System\Classes\SettingsManager;
use WebBook\Forms\Classes\BackendHelpers;
use WebBook\Forms\Components\UploadForm;
use WebBook\Forms\Models\Record;

class Plugin extends PluginBase
{
    public function pluginDetails(): array
    {
        return [
            'name' => __('Magic forms'),
            'description' => __('Create easy ajax forms'),
            'author' => 'WebBook',
            'icon' => 'icon-bolt',
            'homepage' => 'https://github.com/WEBBOOK-plugins/forms-plugin',
        ];
    }

    public function registerNavigation(): array
    {
        // Add menu item for all records
        $menu = [
            'forms' => [
                'label' => __('Forms'),
                'icon' => 'icon-bolt',
                'url' => BackendHelpers::getBackendURL(['webbook.forms.access_records' => 'webbook/forms/records', 'webbook.forms.access_exports' => 'webbook/forms/exports'], 'webbook.forms.access_records'),
                'permissions' => ['webbook.forms.*'],
                'counter' => Record::getUnread(),
                'counterLabel' => __('Unread messages'),
                'sideMenu' => [
                    'records' => [
                        'label' => __('All records'),
                        'icon' => 'icon-database',
                        'url' => Backend::url('webbook/forms/records'),
                        'permissions' => ['webbook.forms.access_records'],
                        'counter' => Record::getUnread(),
                        'counterLabel' => __('Unread messages'),
                    ],
                ],
            ],
        ];

        // Add menu item for each groups
        $groups = Record::all()->pluck('group')->unique();
        foreach ($groups as $group) {
            $slug = str_slug($group);
            $menu['forms']['sideMenu'][$slug] = [
                'label' => $group,
                'icon' => 'icon-database',
                'url' => Backend::url('webbook/forms/records?group='.$group),
                'permissions' => ['webbook.forms.access_records'],
                'counter' => Record::getUnread($group),
                'counterLabel' => __('Unread messages'),
            ];
        }

        // Add menu item to export datas
        $menu['forms']['sideMenu']['exports'] = [
            'label' => __('Export'),
            'icon' => 'icon-download',
            'url' => Backend::url('webbook/forms/exports'),
            'permissions' => ['webbook.forms.access_exports'],
        ];

        return $menu;
    }

    public function registerSettings(): array
    {
        return [
            'config' => [
                'label' => __('Magic forms'),
                'description' => __('Configure magic forms parameters'),
                'category' => SettingsManager::CATEGORY_CMS,
                'icon' => 'icon-bolt',
                'class' => 'WebBook\Forms\Models\Settings',
                'permissions' => ['webbook.forms.access_settings'],
                'order' => 500,
            ],
        ];
    }

    public function registerPermissions(): array
    {
        return [
            'webbook.forms.access_settings' => ['tab' => __('Magic forms'), 'label' => __('Access settings')],
            'webbook.forms.access_records' => ['tab' => __('Magic forms'), 'label' => __('Access records')],
            'webbook.forms.access_exports' => ['tab' => __('Magic forms'), 'label' => __('Can export records')],
            'webbook.forms.gdpr_cleanup' => ['tab' => __('Magic forms'), 'label' => __('Gdpr cleanup')],
        ];
    }

    public function registerComponents(): array
    {
        return [
            UploadForm::class => 'uploadForm',
        ];
    }

    public function registerMailTemplates(): array
    {
        return [
            'webbook.forms::mail.notification',
            'webbook.forms::mail.autoresponse',
        ];
    }

    public function registerSchedule($schedule): void
    {
        $schedule->call(function () {
            UploadForm::gdprClean();
        })->daily();
    }
}
