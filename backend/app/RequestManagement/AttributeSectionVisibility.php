<?php

declare(strict_types=1);

namespace App\RequestManagement;

/**
 * Server-side evaluation of the conditional sections of an attribute layout
 * (spec 0288, D-3/D-4): a section carrying `visible_when: {attribute_code}`
 * is shown only while that boolean attribute is active. The single
 * implementation of the rule — validator and writers both go through here
 * (the frontend mirrors it in `attribute-section-visibility.ts`).
 */
final class AttributeSectionVisibility
{
    /**
     * Codes placed ONLY in hidden sections. A section is visible when it has
     * no condition, or when its control attribute is active AND the section
     * holding the control is itself visible (chains can only come from a
     * multi-category merge). A control missing from the layout, or a cycle,
     * hides the section.
     *
     * @param  array<string, mixed>|null  $layout
     * @param  array<string, mixed>  $values  {code => value}
     * @return array<int, string>
     */
    public function hiddenCodes(?array $layout, array $values): array
    {
        $sections = array_values(array_filter($layout['sections'] ?? [], 'is_array'));

        if ($sections === []) {
            return [];
        }

        $codesBySection = array_map($this->codesOf(...), $sections);
        $sectionOfCode = [];

        foreach ($codesBySection as $index => $codes) {
            foreach ($codes as $code) {
                $sectionOfCode[$code] ??= $index;
            }
        }

        $hidden = [];
        $shown = [];

        foreach ($sections as $index => $_) {
            $isVisible = $this->isVisible($index, $sections, $sectionOfCode, $values, []);

            foreach ($codesBySection[$index] as $code) {
                if ($isVisible) {
                    $shown[$code] = true;
                } else {
                    $hidden[$code] = true;
                }
            }
        }

        return array_map('strval', array_keys(array_diff_key($hidden, $shown)));
    }

    /**
     * The sparse merge of a write (spec 0288, D-4): $normalized over
     * $current, then every code of a section that is hidden in the RESULT
     * is emptied. Evaluated on the merged map so a PATCH carrying only the
     * toggle switched off already empties the section it controls.
     *
     * @param  array<string, mixed>|null  $layout
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    public function mergeAndClear(?array $layout, array $current, array $normalized): array
    {
        $merged = array_merge($current, $normalized);

        foreach ($this->hiddenCodes($layout, $merged) as $code) {
            if (($merged[$code] ?? null) !== null) {
                $merged[$code] = null;
            }
        }

        return $merged;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @param  array<string, int>  $sectionOfCode
     * @param  array<string, mixed>  $values
     * @param  array<int, true>  $visiting
     */
    private function isVisible(int $index, array $sections, array $sectionOfCode, array $values, array $visiting): bool
    {
        $control = $sections[$index]['visible_when']['attribute_code'] ?? null;

        if ($control === null) {
            return true;
        }

        $container = $sectionOfCode[$control] ?? null;

        if ($container === null || isset($visiting[$index])) {
            return false;
        }

        return $this->isActive($values[$control] ?? null)
            && $this->isVisible($container, $sections, $sectionOfCode, $values, $visiting + [$index => true]);
    }

    /** Same truthy set as Laravel's `boolean` rule, since validation also sees unnormalized input. */
    private function isActive(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array<int, string>
     */
    private function codesOf(array $section): array
    {
        $codes = [];

        foreach ($section['rows'] ?? [] as $row) {
            foreach ($row['items'] ?? [] as $item) {
                if (is_string($item['attribute_code'] ?? null)) {
                    $codes[] = $item['attribute_code'];
                }
            }
        }

        return $codes;
    }
}
