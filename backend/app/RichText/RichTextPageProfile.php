<?php

declare(strict_types=1);

namespace App\RichText;

use Dom\Element;

/**
 * The extra vocabulary of the `page` sanitizer profile (spec 0380, D-4,
 * data_contract/html_profile): the element/attribute allow-list that
 * symfony/html-sanitizer applies, plus the DOM pass that validates what an
 * attribute allow-list cannot express (enums, palettes, integers). A value
 * outside its enum/palette removes the attribute; a node that is meaningless
 * without it (span colour, date, task/poll reference) loses the node instead.
 */
final class RichTextPageProfile
{
    /**
     * Element => allowed attributes, merged over the standard allow-list.
     *
     * @var array<string, list<string>>
     */
    public const array ELEMENTS = [
        'h1' => ['data-collapsible'],
        'h2' => ['data-collapsible'],
        'h3' => ['data-collapsible'],
        'h4' => ['data-collapsible'],
        'hr' => [],
        'ul' => ['data-type'],
        'li' => ['data-type', 'data-checked'],
        'mark' => ['data-color'],
        'span' => ['data-color'],
        'table' => [],
        'thead' => [],
        'tbody' => [],
        'tr' => [],
        'th' => ['colspan', 'rowspan'],
        'td' => ['colspan', 'rowspan'],
        'div' => ['data-type', 'data-variant', 'data-task-id', 'data-poll-id'],
        'time' => ['data-type', 'datetime'],
    ];

    public const array HIGHLIGHT_COLORS = ['yellow', 'green', 'blue', 'pink', 'purple'];

    public const array TEXT_COLORS = ['gray', 'red', 'orange', 'green', 'blue', 'purple'];

    public const array CALLOUT_VARIANTS = ['info', 'success', 'warning', 'error'];

    public const string TASK_REF_TYPE = 'task-ref';

    public const string POLL_TYPE = 'poll';

    public const string TASK_ID_ATTR = 'data-task-id';

    public const string POLL_ID_ATTR = 'data-poll-id';

    private const int MAX_SPAN = 50;

    /** Allowed `data-type` of a div => the attributes that type may carry. */
    private const array DIV_TYPES = [
        'callout' => ['data-type', 'data-variant'],
        'collapsible' => ['data-type'],
        'collapsible-summary' => ['data-type'],
        'collapsible-content' => ['data-type'],
        self::TASK_REF_TYPE => ['data-type', self::TASK_ID_ATTR],
        self::POLL_TYPE => ['data-type', self::POLL_ID_ATTR],
    ];

    public function enforce(Element $body): void
    {
        $this->enforceDivs($body);
        $this->enforceLists($body);
        $this->enforceColors($body);
        $this->enforceHeadings($body);
        $this->enforceDates($body);
        $this->enforceCells($body);
    }

    private function enforceDivs(Element $body): void
    {
        foreach (iterator_to_array($body->getElementsByTagName('div')) as $div) {
            $type = RichTextDom::attr($div, 'data-type');

            if (! isset(self::DIV_TYPES[$type])) {
                RichTextDom::unwrap($div);

                continue;
            }

            $this->keepOnly($div, self::DIV_TYPES[$type]);

            if ($type === 'callout' && ! in_array(RichTextDom::attr($div, 'data-variant'), self::CALLOUT_VARIANTS, true)) {
                $div->removeAttribute('data-variant');
            }

            $idAttr = match ($type) {
                self::TASK_REF_TYPE => self::TASK_ID_ATTR,
                self::POLL_TYPE => self::POLL_ID_ATTR,
                default => null,
            };

            if ($idAttr !== null && ! RichText::isPositiveIntString(RichTextDom::attr($div, $idAttr))) {
                $div->remove();
            }
        }
    }

    private function enforceLists(Element $body): void
    {
        foreach (iterator_to_array($body->getElementsByTagName('ul')) as $list) {
            if (RichTextDom::attr($list, 'data-type') !== 'taskList') {
                $list->removeAttribute('data-type');
            }
        }

        foreach (iterator_to_array($body->getElementsByTagName('li')) as $item) {
            if (RichTextDom::attr($item, 'data-type') !== 'taskItem') {
                $item->removeAttribute('data-type');
                $item->removeAttribute('data-checked');

                continue;
            }

            if (! in_array(RichTextDom::attr($item, 'data-checked'), ['true', 'false'], true)) {
                $item->removeAttribute('data-checked');
            }
        }
    }

    private function enforceColors(Element $body): void
    {
        foreach (iterator_to_array($body->getElementsByTagName('mark')) as $mark) {
            if (! in_array(RichTextDom::attr($mark, 'data-color'), self::HIGHLIGHT_COLORS, true)) {
                $mark->removeAttribute('data-color');
            }
        }

        foreach (iterator_to_array($body->getElementsByTagName('span')) as $span) {
            if (in_array(RichTextDom::attr($span, 'data-color'), self::TEXT_COLORS, true)) {
                $this->keepOnly($span, ['data-color']);
            } else {
                RichTextDom::unwrap($span);
            }
        }
    }

    private function enforceHeadings(Element $body): void
    {
        foreach (['h1', 'h2', 'h3', 'h4'] as $tag) {
            foreach (iterator_to_array($body->getElementsByTagName($tag)) as $heading) {
                if (RichTextDom::attr($heading, 'data-collapsible') !== 'true') {
                    $heading->removeAttribute('data-collapsible');
                }
            }
        }
    }

    private function enforceDates(Element $body): void
    {
        foreach (iterator_to_array($body->getElementsByTagName('time')) as $time) {
            if (RichTextDom::attr($time, 'data-type') === 'date' && $this->isIsoDate(RichTextDom::attr($time, 'datetime'))) {
                $this->keepOnly($time, ['data-type', 'datetime']);
            } else {
                RichTextDom::unwrap($time);
            }
        }
    }

    private function enforceCells(Element $body): void
    {
        foreach (['th', 'td'] as $tag) {
            foreach (iterator_to_array($body->getElementsByTagName($tag)) as $cell) {
                foreach (['colspan', 'rowspan'] as $attribute) {
                    $value = RichTextDom::attr($cell, $attribute);

                    if ($cell->hasAttribute($attribute) && (! RichText::isPositiveIntString($value) || (int) $value > self::MAX_SPAN)) {
                        $cell->removeAttribute($attribute);
                    }
                }
            }
        }
    }

    private function isIsoDate(string $value): bool
    {
        return (bool) preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /**
     * @param  list<string>  $allowed
     */
    private function keepOnly(Element $element, array $allowed): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            if (! in_array($attribute->name, $allowed, true)) {
                $element->removeAttribute($attribute->name);
            }
        }
    }
}
