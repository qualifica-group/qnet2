import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'products',
  title: 'Products',
  summary: 'The catalogue gathers every product and service used in offers and requests.',
  sections: [
    {
      id: 'overview',
      title: 'The product catalogue',
      blocks: [
        {
          type: 'paragraph',
          text: "The catalogue's supporting tables (categories, attributes, VAT, units of measure, typologies) are managed in their own configuration modules.",
        },
        {
          type: 'tip',
          text: 'Before entering products, check that categories, VAT rates and units of measure are already set up.',
        },
      ],
    },
    {
      id: 'search-products',
      title: 'Searching for a product',
      blocks: [
        {
          type: 'steps',
          items: [
            'Open Products › Products.',
            'Type in the Search… field at the top of the table.',
            'Narrow the list with the column filters: Category, Typology, Business function or Offer usage.',
            'From the row Actions menu choose View to see the card, or Edit to change it.',
          ],
        },
      ],
    },
    {
      id: 'create-a-product',
      title: 'Creating a product',
      blocks: [
        {
          type: 'steps',
          items: [
            'Press New product.',
            'Fill in the sections in order: Identity, Classification, Pricing and supply, Attributes.',
            'Check the Summary panel on the right.',
            'Press Save. "Product created successfully." appears.',
          ],
        },
      ],
    },
    {
      id: 'identity-section',
      title: 'Identity section',
      blocks: [
        {
          type: 'table',
          headers: ['Field', 'What to enter'],
          rows: [
            ['Code', 'QNet proposes the next code in the numbering, but you can change it. Required, at most 32 characters.'],
            ['Name', "The product's name. Required."],
            ['Description', 'Free text.'],
          ],
        },
        {
          type: 'tip',
          text: 'If you do not have your own code, keep the proposed one: the numbering stays orderly.',
        },
      ],
    },
    {
      id: 'classification-section',
      title: 'Classification section',
      blocks: [
        {
          type: 'paragraph',
          text: 'This section says where the product sits in the catalogue and where it can be used.',
        },
        {
          type: 'table',
          headers: ['Field', 'What to enter'],
          rows: [
            ['Category', "The product's category. Required."],
            ['Typology', 'Chosen from the Product Typologies list.'],
            ['Unit of measure', 'For example hours or pieces.'],
            ['Type', 'Today the only value is Service.'],
            ['Offer usage', 'Where the product can be used in an offer (see below).'],
          ],
        },
        {
          type: 'warning',
          text: 'Choose the Category first: the attributes that appear further down depend on it.',
        },
        {
          type: 'paragraph',
          text: "Some categories appear in the list but cannot be chosen: they only group subcategories. In that case choose a subcategory. The product's Business function derives from the category and is read-only on the card.",
        },
      ],
    },
    {
      id: 'offer-usage',
      title: 'Offer usage',
      blocks: [
        {
          type: 'paragraph',
          text: 'Two independent checkboxes: you can tick one or both.',
        },
        {
          type: 'table',
          headers: ['Checkbox', 'Effect'],
          rows: [
            ['Sellable', "The product can be chosen in an offer's Products card and in Request Management offer lines."],
            ['Usable as cost', "The product can be chosen in an offer's Costs card."],
          ],
        },
        {
          type: 'list',
          items: [
            'On a new product, Sellable is already ticked.',
            'At least one checkbox is required, otherwise "Select at least one usage." appears.',
            'An expense item, such as a train trip or a hotel, is usually marked only as Usable as cost.',
          ],
        },
        {
          type: 'warning',
          text: "A product not enabled for a card does not appear among the selectable ones there. If you later remove a checkbox, offers that already use it remain savable, but you will not be able to add it to new lines of that card.",
        },
        {
          type: 'tip',
          text: "When you convert a lead, its non-sellable products of interest do not become offer lines.",
        },
      ],
    },
    {
      id: 'pricing-and-supply-section',
      title: 'Pricing and supply section',
      blocks: [
        {
          type: 'table',
          headers: ['Field', 'What to enter'],
          rows: [
            ['Cost', 'How much the product costs you. Required, zero or positive.'],
            ['Price', 'The selling price. Required, zero or positive.'],
            ['VAT', 'The VAT rate, from the VAT list.'],
            ['Supplier', 'Who supplies the product.'],
          ],
        },
        {
          type: 'paragraph',
          text: 'Below cost and price the Margin (price minus cost) appears with its percentage of the price. It updates as you type.',
        },
        {
          type: 'warning',
          text: "The Supplier field only lists registries marked as Supplier. If you cannot find a supplier, open its registry and enable that option.",
        },
      ],
    },
    {
      id: 'attributes-section',
      title: 'Attributes section',
      blocks: [
        {
          type: 'paragraph',
          text: 'Attributes are extra fields set by the category, for example a course duration or a service level.',
        },
        {
          type: 'list',
          items: [
            'Without a category the section shows: "Select a category to see its attributes."',
            'Once the category is chosen, its fields appear, including those inherited from higher categories.',
            'The category can group them into several sections; ungrouped ones end up in Other information.',
            'Attributes marked as Required on the category must always be filled in.',
          ],
        },
        {
          type: 'tip',
          text: 'If an attribute is missing, whoever manages the catalogue can add it to the category in Product Categories.',
        },
      ],
    },
    {
      id: 'product-detail',
      title: 'The product detail',
      blocks: [
        {
          type: 'paragraph',
          text: "From the Actions menu choose View to open the read-only card. You find the code and name, the Margin in evidence, the Identity, Classification and Pricing and supply sections, the attribute values, the change history (if you have permission) and the Created at date.",
        },
        {
          type: 'paragraph',
          text: 'To change the data use Edit. To delete a product choose Delete from the Actions menu and confirm.',
        },
      ],
    },
    {
      id: 'cost-item-flow',
      title: 'Typical flow: entering a cost item',
      blocks: [
        {
          type: 'steps',
          items: [
            'Press New product.',
            'Keep the proposed Code and write the Name, for example "Train trip".',
            'Choose the right Category.',
            'In Offer usage remove Sellable and tick Usable as cost.',
            'Enter Cost and Price, then choose the VAT.',
            'Fill in the required attributes and press Save.',
          ],
        },
        {
          type: 'paragraph',
          text: "From this point the item can be chosen in offers' Costs card.",
        },
      ],
    },
  ],
}

export default guide
