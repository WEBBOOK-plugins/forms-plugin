<?php

declare(strict_types=1);

namespace WebBook\Forms\Models;

use Backend\Facades\Backend;
use October\Rain\Database\Model;

class Record extends Model
{
    public $table = 'webbook_forms_records';

    public $timestamps = true;

    protected $jsonable = ['form_data'];

    public $attachMany = [
        'files' => ['System\Models\File', 'public' => false, 'delete' => true],
    ];

    public function filterGroups(): array
    {
        return Record::orderBy('group')->groupBy('group')->lists('group', 'group');
    }

    public function getGroupsOptions(): array
    {
        return $this->filterGroups();
    }

    public function filesList(): string
    {
        return $this->files->map(function ($file) {
            return Backend::url('webbook/forms/records/download', [$this->id, $file->id]);
        })->implode(',');
    }

    public static function getUnread(?string $group = null): ?int
    {
        $unread = Record::query();
        if (! empty($group)) {
            $unread = $unread->where('group', '=', $group);
        }
        $unread = $unread->where('unread', 1);
        $unread = $unread->count();

        return ($unread > 0) ? $unread : null;
    }
}
