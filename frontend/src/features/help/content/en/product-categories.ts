import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'product-categories',
  title: 'Product Categories',
  summary: 'Product Categories are the heart of QNet: they drive the fields you fill in, the rules the system applies and the numbers you read in reports.',
  sections: [
    {
      id: 'why-it-is-central',
      title: 'Why it is the central module',
      blocks: [
        {
          type: 'paragraph',
          text: 'Every product belongs to a category. Categories form a tree: root categories at the top, subcategories below. A root with all its descendants is called a branch. A rule set on a category applies across many other modules.',
        },
        {
          type: 'table',
          headers: ['Category setting', 'Where it applies'],
          rows: [
            ['Parent category (position in the tree)', 'Decides what the category inherits. On product lines of projects, campaigns, opportunities and leads you first choose the Parent category (a root) and then one of its descendants.'],
            ['Business function', 'Derived automatically on product lines; used for user competencies and lead/request assignment. The product shows it read-only.'],
            ['Product Attributes', 'Extra fields on the Product card.'],
            ['Offer Attributes', "Fields in the Offer's Additional information, and columns in the per-category cards of Request Management."],
            ['Work Order Attributes', "Fields in the Work Order's Additional information."],
            ['Attribute layout', 'How these fields are arranged on screen: sections, rows and widths.'],
            ['Management mode', 'How many Product Category rows a card can have, in Opportunities and Request Management.'],
            ['Single offer per opportunity', 'How many offers an opportunity can have.'],
            ['Generates a contract', 'Whether an offer closed with a positive outcome opens a contract.'],
            ['Simplified offer line', 'Whether the offer line fills itself in Request Management.'],
            ['Selectable', 'Whether the category appears in the selection lists of products, product lines, projects, campaigns and commission rules.'],
            ['Reportable and Report columns', 'Rows and columns of the report and dashboard of Request Management and Enrollee Management.'],
            ['Account Managers', 'The name of each Account Manager level (for example "Tutor" instead of "Account manager 1").'],
            ['The category itself', 'Criterion of the offer status workflows (Product category or Product category (branch)) and scope of commission rules.'],
          ],
        },
        {
          type: 'warning',
          text: 'A change to a root category can change the behaviour of the whole branch. Before saving, check the Summary panel next to the form.',
        },
      ],
    },
    {
      id: 'list-page',
      title: 'The list page',
      blocks: [
        {
          type: 'paragraph',
          text: 'Open Products › Product Categories. Categories appear in a table, not a tree: the Parent column says where each one sits.',
        },
        {
          type: 'table',
          headers: ['Column', 'What it shows'],
          rows: [
            ['Name', 'The name; the quick search looks here.'],
            ['Parent', 'The category above it; empty for roots.'],
            ['Description', 'Free text.'],
            ['Business function', 'The valid function, own or inherited.'],
            ['Generates quote, Selectable, Generates a contract', 'Yes or no.'],
            ['Reportable', 'The effective value, own or inherited.'],
            ['Management mode', 'Single or Multiple.'],
            ['Single offer per opportunity, Simplified offer line', 'Hidden at first; shown from the column picker.'],
            ['Attributes', 'How many attributes are assigned directly (inherited ones do not count).'],
            ['Products', 'How many products belong to the category.'],
            ['Created at', 'Creation date; the list starts from the most recent.'],
          ],
        },
        {
          type: 'tip',
          text: 'In the Parent column filter choose the empty value to see only root categories.',
        },
        {
          type: 'paragraph',
          text: 'The Statistics button shows Categories, Root categories, With products, Inherit attributes and Products per category.',
        },
        {
          type: 'table',
          headers: ['Row action', 'What it does'],
          rows: [
            ['View', 'Read-only card, with rules, attributes, account managers and a layout preview.'],
            ['Edit', 'Opens the form.'],
            ['Attribute layout', 'Opens the layout editor.'],
            ['Duplicate', "Opens the create form pre-filled with the category's data (see Duplicating a category)."],
            ['Delete', 'Deletes after confirmation (see the constraints further below).'],
            ['Activity', 'Change history.'],
          ],
        },
        {
          type: 'paragraph',
          text: 'Ticking several rows, the Actions (n) menu offers Move under… to change the parent of all of them at once.',
        },
        {
          type: 'warning',
          text: 'There is no "create subcategory" row action: use New category and choose the Parent category.',
        },
      ],
    },
    {
      id: 'create-root-category',
      title: 'Creating a root category',
      blocks: [
        {
          type: 'steps',
          items: [
            'Click New category.',
            'Fill in Details, leaving Parent category on No parent (root category).',
            'Set the Management rules: on the root they are all editable.',
            'Assign attributes in the three sections.',
            'If needed, name the Account Manager levels.',
            'Check the Summary and click Save. "Category created successfully." appears.',
          ],
        },
        {
          type: 'table',
          headers: ['Field (Details)', 'What to enter'],
          rows: [
            ['Name', 'Required, at most 191 characters. It will appear in every list: choose a clear one.'],
            ['Parent category', 'For a root: No parent (root category).'],
            ['Description', 'Optional.'],
            ['Business function', "The branch's function. Locked if an ancestor already has one."],
          ],
        },
        {
          type: 'paragraph',
          text: 'Management rules: each rule is a panel with a switch; the (i) icon explains its effect.',
        },
        {
          type: 'table',
          headers: ['Rule', 'What to enter', 'Initial value'],
          rows: [
            ['Generates quote', 'Whether the branch works with offers.', 'No'],
            ['Management mode', 'Single (one row per card) or Multiple (several rows per card).', 'Multiple'],
            ['Single offer per opportunity', 'Whether each opportunity can have only one offer.', 'No'],
            ['Generates a contract', 'Whether a positive close opens a contract.', 'Yes'],
            ['Simplified offer line', 'Whether the offer line fills itself in Request Management.', 'No'],
            ['Selectable', 'Whether the category can be chosen in lists.', 'Yes'],
            ['Reportable', 'Whether the category is a report row.', 'No'],
            ['Report columns', 'Appears only if the category is reportable.', '—'],
          ],
        },
        {
          type: 'paragraph',
          text: 'Account Managers: click Add level for each level used and write the display name (at most 60 characters), for example "Operator". An empty field keeps "Account manager N". The reset icon returns to the default name without removing the account managers already assigned.',
        },
        {
          type: 'note',
          text: 'The Other fields section only appears if the administrator has created custom fields for categories.',
        },
      ],
    },
    {
      id: 'create-subcategory',
      title: 'Creating a subcategory',
      blocks: [
        {
          type: 'steps',
          items: [
            'Click New category and write the Name.',
            'In Parent category search for and choose the category above it: the form updates immediately.',
            'Set what applies only here: Selectable, Reportable, Report columns, your attributes and the manager labels.',
            'Click Save.',
          ],
        },
        {
          type: 'list',
          items: [
            'The branch\'s five rules lock with the Inherited from "Root name" badge.',
            'The Business function locks if an ancestor already has one.',
            'Each attribute block shows Inherit from parent and the Inherited from ancestor categories list.',
          ],
        },
      ],
    },
    {
      id: 'duplicate-category',
      title: 'Duplicating a category',
      blocks: [
        {
          type: 'steps',
          items: [
            'On the row of the category to copy, choose Duplicate (you need the permission to create categories).',
            'The create form opens with all the category\'s data: the Name gets the " (copy)" suffix, parent, rules, attributes, account managers and other fields are the same.',
            'Change what you need (at least the Name) and click Save.',
          ],
        },
        {
          type: 'list',
          items: [
            "The category's attribute layout is copied too. If you remove an attribute from the copy, its field disappears from the copied layout.",
            'Subcategories, products and history are not copied: the copy is a new, empty category.',
            'The original category does not change.',
          ],
        },
      ],
    },
    {
      id: 'inheritance',
      title: 'Inheritance',
      blocks: [
        {
          type: 'paragraph',
          text: 'Settings inherit in four different ways.',
        },
        {
          type: 'table',
          headers: ['Type', 'Settings', 'How it works'],
          rows: [
            ['Only the root decides', 'Generates quote, Management mode, Single offer per opportunity, Generates a contract, Simplified offer line', 'The whole branch follows them; they are locked on subcategories. Changing them on the root changes them immediately for the whole branch.'],
            ['From the nearest ancestor', 'Business function', "The nearest ancestor's function applies; if none has one, you choose it and it is inherited from there down."],
            ['Inherited but overridable', 'Reportable, Report columns', "The subcategory takes the parent's value (Inherited from …); changing it shows Overridden. Setting it back to the parent's value resumes inheriting."],
            ['Only for the category', 'Selectable', 'Never inherited: a non-selectable parent can have selectable children.'],
          ],
        },
        {
          type: 'paragraph',
          text: 'If you assign a function to a category whose subcategories have their own, "The subcategories will lose their business function" appears with the list of those involved. Assign and reset confirms: the subcategories inherit the new function.',
        },
        {
          type: 'paragraph',
          text: 'Attributes and "Inherit from parent": a category uses its own attributes plus those of all its ancestors. Each block (Product, Offer, Work Order) has its own Inherit from parent: turning it off, the category and its subcategories ignore the attributes above only in that block.',
        },
        {
          type: 'steps',
          items: [
            'Training (root): Generates a contract no, Management mode Multiple, Offer Attributes "Total hours".',
            'GOL, child of Training: not selectable, Reportable.',
            'Self-funded, child of Training: adds the "Delivery mode" attribute.',
            'GOL - Lombardy, child of GOL: inherits the rules, reportability and "Total hours".',
            'GOL - Lazio, child of GOL: Reportable overridden to no.',
            'DIL, child of Training: Inherit from parent turned off in the Offer Attributes block.',
            'DIL - Lombardy, child of DIL: does not receive "Total hours".',
          ],
        },
        {
          type: 'paragraph',
          text: 'The example shows a branch: rules flow down from the root, reportability can be overridden and an attribute block can be detached from the ancestors.',
        },
      ],
    },
    {
      id: 'management-rules-in-practice',
      title: 'Management rules in practice',
      blocks: [
        {
          type: 'table',
          headers: ['Rule', 'Concrete effect'],
          rows: [
            ['Generates quote', 'Says whether the branch works with offers and appears as a column in the list. Today the Offers panel is available on every opportunity regardless.'],
            ['Management mode: Single', 'An opportunity or request has only one Product Category row; Add product row does not allow a second one. The offer also has a single product row, and a product from another category is rejected.'],
            ['Management mode: Multiple', 'No limit on the number of rows.'],
            ['Single offer per opportunity', 'A second offer on the same opportunity is rejected. Opportunities that already have more than one keep them.'],
            ['Generates a contract', 'On: a positive close opens a contract. Off: no contract, but a positive close is still possible. Contracts already open remain. If a card covers several categories, one without a contract is enough to prevent it.'],
            ['Simplified offer line', 'In Request Management the operator only chooses the product: quantity 1, price and VAT come from the product. In the Offers module they stay manually editable.'],
            ['Selectable off', 'The category becomes a container and disappears from selection lists; existing associations remain. Using it anyway shows "This product category is not selectable."'],
            ['Reportable', "The category becomes a row of the report and dashboard of Request Management and Enrollee Management; choosing it in the report includes its subcategories. An excluded category is not even counted in the parent's totals."],
          ],
        },
      ],
    },
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
            'Click Save.',
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
          type: 'tip',
          text: 'An unplaced field is not lost: it appears at the bottom, in the Other information section.',
        },
      ],
    },
    {
      id: 'report-columns',
      title: 'Report columns',
      blocks: [
        {
          type: 'paragraph',
          text: 'They decide which Request Management statistics are calculated for the category, both in the CSV/Excel report and in the dashboard.',
        },
        {
          type: 'table',
          headers: ['Column', 'Note'],
          rows: [
            ['Calls made', ''],
            ['Unhandled Callbacks, Unhandled New Contacts, Potential Leads', 'They ignore the period and look at the situation today. Selected by default in the predefined categories.'],
            ['Unhandled Callbacks (selected period), Unhandled New Contacts (selected period), Potential Leads (selected period)', 'The same figure calculated on the period picked in the report. Not selected by default: tick them if you need them.'],
            ['Companies entered', ''],
            ['Associates, Deals closed, Handover sent', 'Count requests that moved in the period to a status closed with a positive outcome (details in Request Management › Statistics and reports).'],
            ['Classrooms in progress, Classrooms starting, Appointments taken', 'Today they have no calculation and always show 0.'],
          ],
        },
        {
          type: 'steps',
          items: [
            'Turn on Reportable: the Report columns panel appears.',
            'Tick the columns you need; the counter shows how many you chose (for example "7/14"). All and None select or clear them.',
            'Click Save.',
          ],
        },
        {
          type: 'paragraph',
          text: 'A subcategory uses the columns of the nearest ancestor that set them (Inherited from … badge). Choosing different ones shows Own ("Overriding those inherited from …"); Return to inherited cancels it.',
        },
        {
          type: 'paragraph',
          text: 'A column not chosen stays empty in the file and does not appear in the dashboard. The file contains the union of the columns of the selected categories. Without columns the dashboard shows "No column configured for this category."',
        },
      ],
    },
    {
      id: 'move-reorganize-delete',
      title: 'Moving, reorganizing, deleting',
      blocks: [
        {
          type: 'paragraph',
          text: 'To move a category open Edit and change the Parent category (you cannot choose the category itself or one of its descendants). To move several at once:',
        },
        {
          type: 'steps',
          items: [
            'Select the rows and choose Actions (n) › Move under….',
            'In the Move categories window choose the Destination, including No parent (root category).',
            'Click Move.',
          ],
        },
        {
          type: 'paragraph',
          text: 'Moved categories bring their subcategories with them; the operation is all or nothing. After the move:',
        },
        {
          type: 'list',
          items: [
            'Under a new branch, the category adopts the five rules of the new root.',
            'If it becomes a root, it keeps its current values and decides them itself from then on.',
            'Attributes, layout, reportability, columns and manager names follow the new ancestors.',
            'Products stay in their category and follow it, showing the fields of the new ancestors.',
          ],
        },
        {
          type: 'warning',
          text: 'Delete only works if the category has no subcategories, products or linked opportunities; otherwise "This category has subcategories or products." appears. Move subcategories and products first. To only hide it, turn off Selectable.',
        },
      ],
    },
    {
      id: 'training-branch-example',
      title: 'Full example: the "Training" branch',
      blocks: [
        {
          type: 'steps',
          items: [
            'Root: Name "Training", No parent, Business function "Training". Turn off Generates a contract, leave Multiple, turn off Selectable (it is only a container).',
            'Common attributes: in Offer Attributes add "Total hours" as Required: the whole branch inherits it.',
            '"GOL": parent "Training"; rules and function are already locked. Turn off Selectable, turn on Reportable and choose the useful Report columns.',
            'Regional: "GOL - Lombardy" and "GOL - Lazio", parent "GOL", selectable: they inherit reportability, columns and "Total hours". On "GOL - Lazio" turn off Reportable (Overridden appears).',
            '"Self-funded": parent "Training", not selectable, with the extra "Delivery mode" in Offer Attributes. Below it, one selectable child per region ("Autofinanziato - Campania", "Autofinanziato - Lazio", "Autofinanziato - Lombardia", "Autofinanziato - Sicilia") holding that region\'s courses.',
            '"DIL": if it has entirely its own fields, turn off Inherit from parent in the Offer Attributes block.',
            'Layout: on "Training" open Attribute layout, context Offer, All modes: a "Course data" section with 2 columns and "Total hours" at Half. Every subcategory will use it.',
            'Managers: on "Training" set A.M. 1 = "Tutor" and A.M. 2 = "Operator".',
          ],
        },
        {
          type: 'paragraph',
          text: 'Result: an offer on "GOL - Lombardy" asks for "Total hours", closing positively does not open a contract, it appears in the report under GOL and can be assigned to users competent for the "Training" function.',
        },
      ],
    },
    {
      id: 'common-messages-and-solutions',
      title: 'Common messages and solutions',
      blocks: [
        {
          type: 'table',
          headers: ['Message', 'Cause', 'What to do'],
          rows: [
            ['"Name is required."', 'Empty name.', 'Write the name.'],
            ['"This category has subcategories or products."', 'Deleting a category in use.', 'Move subcategories and products, or turn off Selectable.'],
            ['"The destination is one of the selected categories…"', 'Destination equal to a category being moved.', 'Choose another destination.'],
            ['"The selection contains nested categories…"', 'You selected a parent and one of its children.', 'Deselect the child: it follows the parent.'],
            ['"The destination is inside one of the selected categories…"', 'You would move a category under one of its descendants.', 'Choose a destination outside the branch.'],
            ['"Some categories would override the inherited business function…"', 'The moved categories have their own function and the destination already inherits one.', 'Remove their function first, then move.'],
            ['"Inherited from \'…\'. To change it, act on that category."', 'Rule or function decided higher up.', 'Edit the indicated category.'],
            ['"This product category is not selectable."', 'A container category was chosen in another module.', 'Choose a subcategory or turn on Selectable.'],
            ['"This opportunity is managed on a single product category…"', 'Branch in Single mode.', 'Use a single row or switch the root to Multiple.'],
            ['"This opportunity\'s product category allows only one offer: one already exists."', 'Single offer per opportunity is on.', 'Edit the existing offer.'],
            ['"The layout is not valid…"', 'A section without a title or a field placed twice.', 'Fix it and save again.'],
          ],
        },
      ],
    },
    {
      id: 'best-practices',
      title: 'Best practices',
      blocks: [
        {
          type: 'list',
          items: [
            'Design the tree before loading products: roots for each business area, containers at the second level, selectable categories at the bottom.',
            'Decide the rules on the root and check them in the Summary before saving.',
            'Assign the business function only once, as high up as possible.',
            'Put common attributes near the top and specific ones near the bottom.',
            'Design the layout on the root and customize only where needed.',
            'To retire a category turn off Selectable instead of deleting it.',
            'After every important change open View and check the Inherited from … and Overridden badges.',
          ],
        },
      ],
    },
  ],
}

export default guide
