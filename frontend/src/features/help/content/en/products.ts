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
            'Open Warehouse & Equipment › Products.',
            'Type in the Search… field at the top of the table.',
            'Narrow the list with the column filters: Category, Typology, Business function or Offer usage.',
            'From the row Actions menu choose View to open the card: its data is edited right there, one field at a time.',
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
            'Press New product: the form looks like the card, with the same sections and closed rows (the Code is already suggested).',
            'Click a row (or its pencil) to open it, fill it in and press Done to keep the value or Revert to put it back. Sections: Identity, Classification, Pricing and supply, Suppliers, Attributes.',
            'The header shows Price, Cost and Margin as you fill them in.',
            'Press Save: QNet checks everything and shows "Product created successfully.". Leaving without saving asks for confirmation.',
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
            ['Description', 'Free text. In the product pickers it shows next to the category and is searchable.'],
          ],
        },
        {
          type: 'tip',
          text: 'If you do not have your own code, keep the proposed one: the numbering stays orderly. The suggested code is provisional: if you keep it and another user takes it in the meantime, saving assigns the next free code.',
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
            ['Cost type', 'Optional. The product’s default cost type (for example Collaboratori or Fitti), meant for products Usable as cost: it is proposed on purchase order lines, passive invoice lines and work order costs when you pick the product. It also shows as a colored badge in the grid (filterable by name).'],
            ['Unit of measure', 'For example hours or pieces.'],
            ['Type', 'Service or Physical good (an object, for example a laptop or a piece of furniture).'],
            ['Offer usage', 'Where the product can be used: in an offer, or as a good for internal use (see below).'],
          ],
        },
        {
          type: 'warning',
          text: 'Choose the Category first: the attributes that appear further down depend on it.',
        },
        {
          type: 'paragraph',
          text: "Some categories appear in the list but cannot be chosen: they only group subcategories. In that case choose a subcategory. Inactive categories (with their whole branch) are not offered; when editing, the category already saved stays visible. The product's Business function derives from the category and is read-only on the card.",
        },
      ],
    },
    {
      id: 'bulk-cost-type',
      title: 'Setting the cost type in bulk',
      blocks: [
        {
          type: 'steps',
          items: [
            'Open Warehouse & Equipment › Products and, if needed, filter by Category or Offer usage.',
            'Select the rows with the checkboxes on the left.',
            'From the selection actions menu choose **Set cost type** (it only appears if you can edit products).',
            'In the dialog pick the cost type, or **None** to remove it, and confirm: it applies to all the selected products or to none.',
          ],
        },
        {
          type: 'tip',
          text: 'No mapping between category and cost type is preset: existing products start without a type and you assign them with this action.',
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
      id: 'internal-use-stock',
      title: 'Internal use and stock',
      blocks: [
        {
          type: 'paragraph',
          text: 'The **Internal use** box can only be ticked when the Type is **Physical good**: with Type Service the form shows the error on the field and does not save. A Physical good with Internal use is a **tracked** product: in the **Equipment** module you record its loads, assignments and transfers by site and person.',
        },
        {
          type: 'list',
          items: [
            'The record of a tracked product has the **Stock** section (site, person or “In site”, quantity and unit) and the grid has the **Stock** column: both are only visible with the View list permission on Equipment.',
            'While the stock is greater than zero you cannot untick Internal use or change the Type.',
            'A product with stock movements cannot be deleted.',
          ],
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
            ['Standard price', 'The price of the product when it has no suppliers. Required, zero or positive (default 0). With suppliers it no longer appears.'],
            ['Standard cost', 'How much the product costs you when it has no suppliers. Required, zero or positive (default 0). With suppliers it no longer appears.'],
            ['VAT', 'The VAT rate, from the VAT list.'],
          ],
        },
        {
          type: 'list',
          items: [
            "Every product, whatever its usage (Sellable, Usable as cost, Internal), has a **Price** and a **Cost**: usages only decide where the product can be chosen.",
            "In the **Suppliers** section you can add several suppliers, each with its own **Price** and **Cost** (required, default 0). One is the **Default**: with a single supplier it is automatic, with several suppliers you must choose it. With suppliers the Standard price and Standard cost no longer appear. The Suppliers section spans the full width: a table with supplier, price, cost and margin (in euro and as a percentage, red when negative), the Default badge next to the name and, instead of the cost, the commission when the supplier has one. While editing, each row shows its margin as you type.",
            "If the supplier has a **Supplier** commission on that product (the Commission Configurator rule that would apply today: dedicated to the supplier on the product or its category, or the generic Supplier role rule; only if the typology has **Supplier commission calculation** on), its **Cost** is locked at 0 and the notice \"10% commission paid\" or \"received\" (or \"Fixed commission ...\") appears.",
            "The grid **Price** and **Cost** columns (filters and sorting too) and the product **Price**, **Cost** and **Margin** box show the default supplier's values; without suppliers the standard ones.",
            "A registry that is the supplier of a product cannot be deleted.",
          ],
        },
        {
          type: 'paragraph',
          text: 'The Margin (price minus cost, with suppliers those of the default supplier) appears below price and cost with its percentage of the price. It updates as you type.',
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
            'Without a category the section does not appear.',
            'Once the category is chosen, its fields appear, including those inherited from higher categories, each as a row to open.',
            'The category can group them into several sections; ungrouped ones end up in Other information.',
            'Attributes marked as Required on the category must always be filled in.',
            'A section can appear only when a yes/no field is on: with the field off the section disappears, its required fields are not needed and its values are cleared on save.',
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
          text: "From the Actions menu choose View to open the card. You find the code and name, Price, Cost and Margin in evidence (of the default supplier, or standard if there are no suppliers), the Identity, Classification, Pricing and supply and Suppliers sections, the attributes (split into the same sections set up for the category, empty ones included), the custom fields, the change history (if you have permission) and the Created at date.",
        },
        {
          type: 'steps',
          items: [
            'There is no separate edit page: hover the field to change and press the pencil (or click the value).',
            'Change the value in the control that appears.',
            'Press Save to save that field alone; Cancel (or Esc, or a click outside the open field) closes it unchanged.',
          ],
        },
        {
          type: 'note',
          text: "The Code cannot change after creation. Changing the Category, the attributes the new category adds are filled in the same box; turning on a Usage asks the Price and Cost in the same box. A field without a pencil is read-only for your role.",
        },
        {
          type: 'paragraph',
          text: 'To delete a product choose Delete from the Actions menu and confirm.',
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
            'Enter the Cost (and the Price), then choose the VAT.',
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
