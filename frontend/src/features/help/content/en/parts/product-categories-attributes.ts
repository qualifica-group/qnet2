import type { HelpSection } from '../../../types'

/** Attribute sections of the product-categories guide, split out to stay under the file-size limit. */
export const attributeSections: HelpSection[] = [
    {
      id: 'attributes',
      title: 'Attributes',
      blocks: [
        {
          type: 'paragraph',
          text: 'Attributes are created in the Attributes module; on the category you decide where to use them. The same attribute can live in one, two or all three blocks.',
        },
        {
          type: 'table',
          headers: ['Block', 'Where the fields appear'],
          rows: [
            ['Product Attributes', 'On the Product card, for products of the category.'],
            ['Offer Attributes', "In the Offer's Additional information, when a line uses a product of the category; also as columns of the category card in Request Management."],
            ['Work Order Attributes', "In the Work Order's Additional information, when one of its lines uses a product of the category."],
          ],
        },
        {
          type: 'steps',
          items: [
            'In the chosen block open Assign an attribute….',
            'Search by name and select it: it appears in the list with its type.',
            'Turn on Required if the field must always be filled in.',
            'In Order write a number: lower numbers come first.',
            'To remove an attribute use Remove attribute.',
            'Press Save (on the card of an existing category) to save all the attributes and the three Inherit from parent switches together; Cancel discards the changes.',
          ],
        },
        {
          type: 'paragraph',
          text: 'Below your list you see, read-only, the attributes Inherited from ancestor categories.',
        },
        {
          type: 'tip',
          text: 'Assign an attribute to the highest category that needs it: every subcategory will receive it. You can reassign it on a subcategory to make it required only there; the setting of the nearest category applies.',
        },
        {
          type: 'paragraph',
          text: 'If you remove an attribute that already has values, the values on products are not deleted, but the field no longer appears and is no longer required. The same happens by turning off Inherit from parent.',
        },
      ],
    },
    {
      id: 'attribute-layout',
      title: 'Attribute layout',
      blocks: [
        {
          type: 'paragraph',
          text: 'The layout decides how the fields are arranged; without a layout they appear as a simple list.',
        },
        {
          type: 'steps',
          items: [
            "On the category's row choose Attribute layout.",
            'Choose the Context: Product, Offer or Work Order.',
            'Choose the Form mode: All modes, Create, Edit or View.',
            'Click Add section and fill in Title (required), Description, Appearance (Standard, Highlighted, Informative, Secondary), Columns (1 to 4), Collapsible and Closed by default.',
            'If the section should appear only when a yes/no field is on, choose that field in Visible only if active; with Always visible the section always appears.',
            'Click Add row inside the section.',
            'Drag a field from Unplaced attributes into the row.',
            'For each field choose the Width: Full, Two thirds, Half, One third or One quarter.',
            'Move sections and rows with the arrows and check the live Preview.',
            'Click Save layout. "Layout saved." appears and you can switch to another context; Cancel closes without saving.',
          ],
        },
        {
          type: 'table',
          headers: ['Situation', 'What you see', 'What you can do'],
          rows: [
            ['Mode without a dedicated layout', '"This mode uses the layout of \'All modes\'." appears', 'Customize this mode; to go back, Return to all modes (confirm with Remove).'],
            ['Category without its own layout', '"This category uses the layout of \'…\'." appears and the editor is locked', 'Customize this category; to go back, Return to the inherited layout.'],
          ],
        },
        {
          type: 'note',
          text: "If Inherit from parent is off for a context, the category does not inherit that context's layout either.",
        },
        {
          type: 'table',
          headers: ['Conditional section', 'What happens'],
          rows: [
            ['Which field opens it', 'A yes/no attribute (checkbox or switch) placed in another section of the layout, which is not conditional itself. A section other sections depend on cannot be conditional.'],
            ['In the form', 'With the field off the section is hidden; switching it on shows the section in its place. In a record detail it appears only when the field is on.'],
            ['Required fields', 'They apply only while the section is visible.'],
            ['On save', 'If the field is off, the values of the section are cleared.'],
            ['In the editor', 'If you remove the field from the layout or move it into the section it opens, the section goes back to Always visible. In the live Preview switch the field on to see the section, marked "Visible if: …".'],
          ],
        },
        {
          type: 'tip',
          text: 'An unplaced field is not lost: it appears at the bottom, in the Other information section.',
        },
        {
          type: 'note',
          text: 'When a quote or work order contains products from several categories, their sections are merged and sorted by position: for example, with ISO 9001 and 50001 lines the groups of both standards appear before the consultancy type section.',
        },
      ],
    },
]
