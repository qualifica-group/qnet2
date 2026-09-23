import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'attributes',
  title: 'Attributes',
  summary: 'Attributes are reusable extra fields you assign to product categories.',
  sections: [
    {
      id: 'overview',
      title: 'What attributes are',
      blocks: [
        {
          type: 'paragraph',
          text: 'Attributes are reusable extra fields (colour, power, duration) assigned to product categories. Once created, the same attribute can be used by several categories.',
        },
      ],
    },
    {
      id: 'field-types',
      title: 'Field types',
      blocks: [
        {
          type: 'paragraph',
          text: 'For each attribute you enter a Code, a Name and the field type:',
        },
        {
          type: 'list',
          items: [
            'Text',
            'Long text',
            'Integer number',
            'Decimal number',
            'Yes/No',
            'Option list',
            'Relation',
            'Date',
            'Date and time',
            'Time',
            'Email',
            'URL',
            'Colour',
          ],
        },
        {
          type: 'note',
          text: 'An Option list requires at least one option, with all values different; a Relation requires the linked module.',
        },
      ],
    },
    {
      id: 'creating-an-attribute',
      title: 'Creating an attribute',
      blocks: [
        {
          type: 'steps',
          items: [
            'Open Products › Attributes and press New attribute.',
            'Enter Code and Name.',
            'Choose the field Type.',
            'For an Option list add at least one option, with a Value and a Label.',
            'For a Relation choose the linked module.',
            'Press Save.',
          ],
        },
      ],
    },
    {
      id: 'duplicate-attribute',
      title: 'Duplicating an attribute',
      blocks: [
        {
          type: 'steps',
          items: [
            'On the row of the attribute to copy choose Duplicate (you need the permission to create attributes).',
            'The create form opens pre-filled with the attribute\'s data: the Code gets the "_copy" suffix, the Name the " (copy)" suffix; type, options, configuration and other fields are the same.',
            'Change what you need (the Code must stay unique) and press Save.',
          ],
        },
        {
          type: 'list',
          items: [
            'The copy is a new attribute: it is not assigned to any category and has no values on products.',
            'The original attribute does not change.',
          ],
        },
      ],
    },
    {
      id: 'constraints',
      title: 'Constraints',
      blocks: [
        {
          type: 'warning',
          text: 'The field type cannot be changed after creation. An attribute assigned to a category or with values on products cannot be deleted.',
        },
        {
          type: 'note',
          text: 'To decide where an attribute appears (Product, Offer or Work Order) go to the product category: see the Product Categories guide.',
        },
      ],
    },
  ],
}

export default guide
