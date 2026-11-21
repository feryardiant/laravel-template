<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Human-readable labels for field names recorded in the audit log.
 */
final class FieldLabel
{
    public static function label(string $field): string
    {
        return match ($field) {
            'code' => __('Code'),
            'type' => __('Type'),
            'start_date' => __('Start date'),
            'finish_date' => __('Finish date'),
            'name' => __('Name'),
            'summary' => __('Summary'),
            'gender' => __('Gender'),
            'photo_url' => __('Photo'),
            'logo_url' => __('Organization Logo'),
            'contact_name' => __('Contact name'),
            'contact_summary' => __('Contact summary'),
            'contact_gender' => __('Contact gender'),
            default => $field,
        };
    }
}
