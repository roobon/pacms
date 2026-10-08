<?php

namespace App\Cms\Content\Types;

use App\Cms\Content\ContentType;
use App\Models\ContentItem;
use App\Models\Program;

/**
 * Programs: long-running areas of work with objectives, activities and documents.
 */
class ProgramType extends ContentType
{
    public function key(): string
    {
        return 'programs';
    }

    public function label(): string
    {
        return 'Programs';
    }

    public function singular(): string
    {
        return 'program';
    }

    public function icon(): string
    {
        return 'bi-diagram-3';
    }

    public function modelClass(): string
    {
        return Program::class;
    }

    public function documents(): bool
    {
        return true;
    }

    public function fields(): array
    {
        return [
            'objectives' => [
                'type' => 'repeater', 'label' => 'Objectives', 'section' => 'Objectives & activities',
                'rules' => ['nullable', 'array', 'max:30'], 'max' => 30, 'add_label' => 'Add objective',
                'fields' => ['text' => ['type' => 'text', 'label' => 'Objective', 'rules' => ['nullable', 'string', 'max:500']]],
            ],
            'activities' => [
                'type' => 'repeater', 'label' => 'Activities', 'section' => 'Objectives & activities',
                'rules' => ['nullable', 'array', 'max:30'], 'max' => 30, 'add_label' => 'Add activity',
                'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Activity', 'rules' => ['nullable', 'string', 'max:191']],
                    'text' => ['type' => 'textarea', 'label' => 'Description', 'rules' => ['nullable', 'string', 'max:2000']],
                ],
            ],
            'partners' => ['type' => 'relation', 'target' => 'partners', 'multiple' => true, 'display' => 'logos', 'label' => 'Partners', 'title' => 'Partners', 'section' => 'Partners and gallery'],
            'gallery' => ['type' => 'relation', 'target' => 'galleries', 'multiple' => false, 'display' => 'gallery', 'label' => 'Gallery', 'title' => 'Gallery', 'section' => 'Partners and gallery'],
        ];
    }

    public function details(ContentItem $item): array
    {
        /** @var Program $item */
        $lists = [];
        if ($item->objectives) {
            $lists[] = ['key' => 'objectives', 'title' => 'Objectives', 'items' => array_map(fn (array $row) => ['title' => null, 'text' => $row['text'] ?? null], $item->objectives)];
        }
        if ($item->activities) {
            $lists[] = ['key' => 'activities', 'title' => 'Activities', 'items' => array_map(fn (array $row) => ['title' => $row['title'] ?? null, 'text' => $row['text'] ?? null], $item->activities)];
        }

        return ['lists' => $lists];
    }
}
