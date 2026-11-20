<?php

declare(strict_types=1);

namespace App\Services\WorkOrders\Page;

use App\RichText\RichText;
use App\RichText\RichTextDom;
use App\RichText\RichTextPageProfile;
use Dom\Element;

/**
 * Reads and cleans the task-ref / poll nodes of a (sanitized) page content
 * (spec 0380): the content only stores their ids, never the data.
 */
final class WorkOrderPageReferences
{
    /**
     * @return list<int>
     */
    public function taskIds(?string $html): array
    {
        return $this->ids($html, RichTextPageProfile::TASK_REF_TYPE, RichTextPageProfile::TASK_ID_ATTR);
    }

    /**
     * @return list<int>
     */
    public function pollIds(?string $html): array
    {
        return $this->ids($html, RichTextPageProfile::POLL_TYPE, RichTextPageProfile::POLL_ID_ATTR);
    }

    /**
     * Drops the id attribute of every task-ref/poll node that does not point to a
     * task of the work order / a poll of the page: the node stays and renders as
     * "not available" (spec side_effects).
     *
     * @param  list<int>  $taskIds
     * @param  list<int>  $pollIds
     */
    public function scrub(string $html, array $taskIds, array $pollIds): string
    {
        $body = RichTextDom::parse($html);

        $this->dropForeign($body, RichTextPageProfile::TASK_REF_TYPE, RichTextPageProfile::TASK_ID_ATTR, $taskIds);
        $this->dropForeign($body, RichTextPageProfile::POLL_TYPE, RichTextPageProfile::POLL_ID_ATTR, $pollIds);

        return RichTextDom::serializeInner($body);
    }

    /**
     * @return list<int>
     */
    private function ids(?string $html, string $type, string $attribute): array
    {
        if ($html === null || trim($html) === '') {
            return [];
        }

        $ids = [];

        foreach ($this->nodes(RichTextDom::parse($html), $type) as $node) {
            $value = RichTextDom::attr($node, $attribute);

            if (RichText::isPositiveIntString($value)) {
                $ids[] = (int) $value;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<int>  $kept
     */
    private function dropForeign(Element $body, string $type, string $attribute, array $kept): void
    {
        foreach ($this->nodes($body, $type) as $node) {
            if (! in_array((int) RichTextDom::attr($node, $attribute), $kept, true)) {
                $node->removeAttribute($attribute);
            }
        }
    }

    /**
     * @return list<Element>
     */
    private function nodes(Element $body, string $type): array
    {
        return array_values(array_filter(
            iterator_to_array($body->getElementsByTagName('div')),
            static fn (Element $div): bool => RichTextDom::attr($div, 'data-type') === $type,
        ));
    }
}
