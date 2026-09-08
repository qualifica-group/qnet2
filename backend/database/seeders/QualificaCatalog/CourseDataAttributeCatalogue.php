<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The client's "Dati corso" attributes: the total duration of the course being
 * sold and, for the self-funded catalogue only, how it is delivered. Pure
 * data, like ClassroomAttributeCatalogue — QualificaCatalogSeeder creates and
 * assigns them, QualificaQuoteLayoutSeeder groups them into the form section
 * named below.
 *
 * OFFERTA CONTEXT, not PRODUCT (user directive 2026-09-08). These two used to
 * be product-context attributes written onto every seeded course by
 * CatalogProducts; they moved to the Offerta together with the whole "Dati
 * Aula" set, so the operator records them per deal. No seeded product carries
 * a value for them any more: `AttributeValueValidator` rejects a code outside
 * the product's own applicable set, which is exactly what these are now.
 *
 * `code` is the English identifier (the catalogue's natural key, and its
 * `^[a-z0-9_]+$` format); `name` is the user-facing label, kept in its
 * original language.
 */
final class CourseDataAttributeCatalogue
{
    /**
     * The title of the layout section grouping both attributes in the offer
     * form (spec 0062). User-facing, kept in its original language.
     */
    public const string SECTION_TITLE = 'Dati corso';

    public const string TOTAL_HOURS = 'total_hours';

    public const string DELIVERY_MODE = 'delivery_mode';

    /**
     * The `delivery_mode` option values (stored keys, English; their
     * user-facing labels live on the attribute options).
     */
    public const string IN_PERSON = 'in_person';

    public const string ONLINE = 'online';

    /**
     * Assigned to the "Formazione" root, inherited by the whole branch: every
     * course has a duration, regional or self-funded.
     *
     * @var list<array{code: string, name: string, type: string}>
     */
    public const array TRAINING_ATTRIBUTES = [
        ['code' => self::TOTAL_HOURS, 'name' => 'Ore complessive', 'type' => 'integer'],
    ];

    /**
     * Confined to the "Autofinanziato" subtree: the only offer sold with a
     * delivery mode.
     *
     * @var list<array{code: string, name: string, type: string, options: list<array{value: string, label: string}>}>
     */
    public const array SELF_FUNDED_ATTRIBUTES = [
        ['code' => self::DELIVERY_MODE, 'name' => 'Modalità di svolgimento', 'type' => 'enum', 'options' => [
            ['value' => self::IN_PERSON, 'label' => 'In presenza'],
            ['value' => self::ONLINE, 'label' => 'Online'],
        ]],
    ];

    /**
     * The section's single row: the two fields sit side by side in a
     * two-column section, and a category resolving only the duration keeps it
     * alone (the row is filtered against the effective set before it is
     * written).
     *
     * @var list<list<string>>
     */
    public const array ROWS = [
        [self::TOTAL_HOURS, self::DELIVERY_MODE],
    ];
}
