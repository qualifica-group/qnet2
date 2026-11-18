/**
 * Product Categories + Products domains (spec 0017). Extracted to a sibling
 * file to keep `en.ts` within the engineering size limits (see
 * `.claude/rules/engineering.md` §6); its public shape is unchanged. The
 * Attributes domain lives in `en-attributes.ts`, the Products one in
 * `en-product-items.ts`.
 */

export { attributes } from './en-attributes'
export { products } from './en-product-items'

export const productCategories = {
  title: 'Product Categories',
  subtitle: 'Browse, filter and manage your product categories.',
  forbidden: "You don't have permission to view product categories.",
  // Identity pills, shared by the form's identity bar and the record.
  badges: {
    root: 'Root',
    notSelectable: 'Not selectable',
    notActive: 'Not active',
    reportable: 'Visible in reports',
  },
  columns: {
    name: 'Name',
    parent: 'Parent',
    description: 'Description',
    attributes_count: 'Attributes',
    products_count: 'Products',
    business_function: 'Business function',
    requires_quote: 'Quoted',
    is_selectable: 'Selectable',
    is_active: 'Active',
    is_reportable: 'Visible in reports',
    management_mode: 'Management mode',
    single_quote_per_opportunity: 'One offer per opportunity',
    generates_contract: 'Includes a contract',
    multi_quote_work_order: "Multi-offer work order",
    usage_area: 'Used in',
    multiple_open_opportunities: 'Several open opportunities per registry',
    request_registry_type: 'Registry in Gestione Richieste',
    simplified_offer_line: 'Simplified offer line',
    created_at: 'Created at',
    tooltipEmpty: 'Nothing to show.',
    productsMore: '+{{count}} more',
  },
  detail: {
    title: 'Category details',
    subtitle: 'Read-only view of the selected category.',
    loadError: 'Unable to load the category. Please try again.',
    businessFunctionInherited: 'Inherited from {{category}}',
    requiresQuoteInherited: 'Inherited from {{category}}',
    isReportableInherited: 'Inherited from {{category}}',
    reportColumnsInherited: 'Inherited from {{category}}',
    managementModeInherited: 'Inherited from {{category}}',
    singleQuotePerOpportunityInherited: 'Inherited from {{category}}',
    multipleOpenOpportunitiesInherited: 'Inherited from {{category}}',
    generatesContractInherited: 'Inherited from {{category}}',
    multiQuoteWorkOrderInherited: "Inherited from {{category}}",
    usageAreaInherited: 'Inherited from {{category}}',
    requestRegistryTypeInherited: 'Inherited from {{category}}',
    simplifiedOfferLineInherited: 'Inherited from {{category}}',
    requestOfferHiddenInherited: 'Inherited from {{category}}',
    managerLabelInherited: 'Inherited',
    inheritedCount: '+{{count}} inherited',
  },
  bulkMove: {
    tableButton: 'Move under…',
    title: 'Move categories',
    description: '{{count}} category(ies) selected. They keep their own subcategories.',
    destination: 'Destination',
    confirm: 'Move',
    success: '{{count}} category(ies) moved.',
    genericError: 'Unable to move the categories. Please try again.',
    reasons: {
      self_parent: 'The destination is one of the selected categories. Nothing was moved.',
      nested_selection:
        'The selection contains categories nested inside one another. Deselect the ones below, they follow their parent. Nothing was moved.',
      cycle: 'The destination is inside one of the selected categories. Nothing was moved.',
      business_function_conflict:
        'Some categories would override the business function inherited from the destination. Nothing was moved.',
    },
  },
  form: {
    newRootCategory: 'New category',
    createTitle: 'Create category',
    createSubtitle: 'Add a new product category.',
    name: 'Name',
    nameRequired: 'Name is required.',
    nameMax: 'Name must be at most 191 characters.',
    parent: 'Parent category',
    parentPlaceholder: 'Select a parent category…',
    parentSearch: 'Search categories…',
    parentEmpty: 'No categories found.',
    parentNoMatch: 'No matches found.',
    parentError: 'Unable to load categories.',
    noParent: 'No parent (root category)',
    description: 'Description',
    inheritsAttributes: 'Inherit from parent',
    inheritsAttributesHint:
      'Applies to this section only. When off, this category ignores its ancestors: neither it nor its subcategories inherit these attributes from above.',
    attributes: 'Attributes',
    attributesHelp:
      'Assign the attributes that products in this category must fill in (in addition to what they inherit).',
    attributesEmpty: 'No attributes assigned yet.',
    addAttributePlaceholder: 'Assign an attribute…',
    attributeSearch: 'Search attributes…',
    attributeEmpty: 'No attributes found.',
    attributeNoMatch: 'No matches found.',
    attributeError: 'Unable to load attributes.',
    isRequired: 'Required',
    isRequiredHelp: 'When on, the product MUST fill in this attribute.',
    sortOrder: 'Order',
    sortOrderHelp: 'The position this field appears at in the product form.',
    removeAttribute: 'Remove attribute',
    inheritedAttributes: 'Inherited from ancestor categories',
    businessFunction: 'Business function',
    businessFunctionPlaceholder: 'Select a business function…',
    businessFunctionSearch: 'Search business functions…',
    businessFunctionEmpty: 'No business functions found.',
    businessFunctionError: 'Unable to load business functions.',
    currentCategory: 'Current category',
    currentCategoryPlaceholder: 'Select the current category…',
    currentCategorySearch: 'Search current parent categories…',
    currentCategoryEmpty: 'No current parent category found.',
    currentCategoryError: 'Unable to load the current parent categories.',
    currentCategoryHint: 'The current parent category that replaced this one. It links them in the filters and in the flexible-field columns.',
    businessFunctionInheritedHint:
      'Inherited from "{{category}}". To change it, edit that category instead.',
    businessFunctionResetTitle: 'Subcategories will lose their own business function',
    businessFunctionResetDescription_one:
      'Assigning a business function to this category clears the one the subcategory below owns: it will inherit the function picked here instead.',
    businessFunctionResetDescription_other:
      'Assigning a business function to this category clears the ones the {{count}} subcategories below own: they will inherit the function picked here instead.',
    businessFunctionResetConfirm: 'Assign and clear',
    requiresQuote: 'Quoted',
    requiresQuoteHint: 'When on, this category and every subcategory below it are quoted.',
    requiresQuoteInheritedHint:
      'Quoting is inherited from the root category "{{category}}". To change it, edit that category instead.',
    isSelectable: 'Selectable',
    requiresContactTransfer: 'Contact transfer before the contract',
    requiresContactTransferHint:
      'When on, an offer of this category cannot move to Won (and open its contract) until its contact has been transferred with Transfer contact. Provisional win is always allowed.',
    requiresContactTransferInfo:
      'It applies to this category only, not to its subcategories. The check runs when the offer would move to Won opening a contract, from any module (Offers, Gestione Richieste, Gestione Iscritti, grid). Offers already won and categories without a contract are not affected.',
    requiresContactTransferInfoLabel: 'More info about Contact transfer before the contract',
    isSelectableHint:
      'When off, the category can only group subcategories: it disappears from the pickers and can no longer be assigned to a product, a product line, a project, a campaign or a commission rule. Existing associations are kept.',
    isActive: 'Active',
    isActiveHint:
      'When off, the category disappears from the input fields together with its whole branch and its products. Existing links are kept.',
    isActiveInheritedNotice: 'Not active because "{{category}}" is not',
    isReportable: 'Visible in reports',
    isReportableHint:
      'The category appears in the Gestione Richieste and Gestione Iscritti reports and dashboard; selecting it also shows its subcategories.',
    isReportableInheritedHint:
      'Inherited from "{{category}}". Change it to force it on this category and its subcategories only.',
    isReportableForcedHint:
      'Forced on this category: it no longer follows "{{category}}". Set it back to the parent\'s value to inherit it again.',
    isReportableForcedBadge: 'Forced',
    isReportableOriginHint:
      'Set on this category: every subcategory inherits it, unless forced otherwise.',
    isReportableNoSourceHint:
      'No parent category is visible in the reports. Turn it on here to include this category and all of its subcategories.',
    managementMode: 'Management mode',
    managementModeHint:
      'How Category Product lines behave on a card: this category and every subcategory below it follow the same rule.',
    managementModeInheritedHint:
      'The management mode is inherited from the root category "{{category}}". To change it, edit that category instead.',
    managementModeSingle: 'Single (one line per card)',
    managementModeMultiple: 'Multiple (several lines per card)',
    // Short form for the side recap, where the row is truncated.
    managementModeSingleShort: 'Single',
    managementModeMultipleShort: 'Multiple',
    requiresQuoteInfo:
      'Governs whether the opportunities of this branch go through an offer at all. When on, the Offers module is part of the flow for every product of this category and of its subcategories; when off, the branch is worked without one. The ROOT category sets the rule and the whole subtree follows it, unless a subcategory forces its own value (that choice then applies to its own subtree).',
    requiresQuoteInfoLabel: 'More info about Quoted',
    managementModeInfo:
      'Bounds how many Category Product lines one card may carry. "Single" locks the card on ONE product category — its offer then carries a single product row too, and a product from another category is refused instead of silently widening the coverage. "Multiple" is the unconstrained behaviour. The ROOT category sets the rule and the whole subtree follows it, unless a subcategory forces its own value (that choice then applies to its own subtree).',
    managementModeInfoLabel: 'More info about Management mode',
    singleQuotePerOpportunity: 'One offer per opportunity',
    singleQuotePerOpportunityHint:
      'When on, an opportunity on this category accepts a single offer.',
    singleQuotePerOpportunityInheritedHint:
      'The one-offer rule is inherited from the root category "{{category}}". To change it, edit that category instead.',
    singleQuotePerOpportunityInfo:
      'Bounds how many OFFER DOCUMENTS an opportunity may hold, which is a different rule from the management mode (that one bounds the product lines of a card). When on, creating a second offer on an opportunity of this branch is refused. Opportunities that already carry several offers keep them and stay editable. The ROOT category sets the rule and the whole subtree follows it, unless a subcategory forces its own value (that choice then applies to its own subtree).',
    singleQuotePerOpportunityInfoLabel: 'More info about One offer per opportunity',
    requestRegistryType: 'Registry in Gestione Richieste',
    requestRegistryTypeHint:
      'Which type the new-client card starts on when a request of this category is created in Gestione Richieste.',
    requestRegistryTypeInheritedHint: 'Inherited from the root category "{{category}}". To change it, edit that category.',
    multipleOpenOpportunities: 'Several open opportunities per registry',
    multipleOpenOpportunitiesHint:
      'When on, a registry may keep several open opportunities on this category.',
    multipleOpenOpportunitiesInheritedHint:
      'The rule is inherited from the root category "{{category}}". To change it, edit that category instead.',
    multipleOpenOpportunitiesInfo:
      'By default a registry may keep only one open opportunity: a second one is refused and points to the existing one. When on, the opportunities of this branch neither block nor are blocked, without changing the management mode: with Multiple mode their offers keep several rows. It applies only when every current category of the opportunity has the rule on. Single-mode categories never block. The ROOT category sets the rule and the whole subtree follows it.',
    multipleOpenOpportunitiesInfoLabel: 'More info about Several open opportunities per registry',
    multipleOpenOpportunitiesOffWarning: 'Off: in Gestione Richieste a new request for a client with an open opportunity is refused.',
    generatesContract: 'Includes a contract',
    generatesContractHint:
      'When on, an offer of this category that closes with a positive outcome opens a contract.',
    generatesContractInheritedHint:
      'The contract rule is inherited from the root category "{{category}}". To change it, edit that category instead.',
    generatesContractInfo:
      'Decides whether the branch is sold under a contract. When off, an offer reaching a positively closed working status opens NO contract and the deal never appears in the Contratti module; closing positively stays possible all the same. Contracts already opened before it was turned off stay where they are and go on living normally. When a card covers several categories, a single one without the rule is enough to withhold the contract. The ROOT category sets the rule and the whole subtree follows it, unless a subcategory forces its own value (that choice then applies to its own subtree).',
    generatesContractLockedByMulti: 'Turn off Multi-offer work order first.',
    generatesContractInfoLabel: 'More info about Includes a contract',
    multiQuoteWorkOrder: "Multi-offer work order",
    usageArea: 'Used in',
    usageAreaHint:
      'The modules where this category and all its subcategories can be picked: elsewhere it is not offered and has no tab. Values already saved are kept.',
    usageAreaInheritedHint: 'Inherited from the root category "{{category}}". To change it, edit that category.',
    multiQuoteWorkOrderHint:
      "Lets you schedule from the Contracts table a single work order with lines of several won contracts of this branch.",
    multiQuoteWorkOrderInheritedHint:
      "The multi-offer work order rule is inherited from the root category \"{{category}}\". To change it, edit that category instead.",
    multiQuoteWorkOrderInfo:
      "Decides whether, from the Contracts table, a single work order can be scheduled gathering revenue lines of several won contracts (even of different customers) of this branch. When off, every work order stays tied to a single offer. Work orders already created do not change. The ROOT category sets the rule and the whole subtree follows it.",
    multiQuoteWorkOrderNeedsContract: 'Requires Includes a contract.',
    multiQuoteWorkOrderInfoLabel: "More info about Multi-offer work order",
    simplifiedOfferLine: 'Simplified offer line',
    simplifiedOfferLineHint:
      'When on, in Gestione Richieste the offer becomes a plain product choice: quantity, unit price and VAT rate are set by the system and prices are shown for information only.',
    simplifiedOfferLineInheritedHint:
      'Inherited from "{{category}}". Change it to force the rule only on this category and its subcategories.',
    simplifiedOfferLineForcedHint:
      'Forced on this category: it no longer follows "{{category}}". Set it back to the inherited value to inherit it again.',
    simplifiedOfferLineForcedBadge: 'Forced',
    simplifiedOfferLineInfo:
      "Removes the manual compilation of the offer row in Gestione Richieste: on requests of this branch the operator picks ONLY the product and the system fills in quantity (always 1), unit price and VAT rate from the product. The Offerte module is untouched: whoever works the commercial offer keeps governing quantity and prices even on a simplified category. The ROOT category sets the rule and the whole subtree follows it, unless a subcategory forces its own value (that choice then applies to its own subtree).",
    simplifiedOfferLineInfoLabel: 'More info about Simplified offer line',
    simplifiedOfferLineNeedsRequests: 'No effect: the branch is not used in Gestione Richieste (Used in).',
    requestOfferHidden: 'Hide the offer in Gestione Richieste',
    requestOfferHiddenHint: 'When on, the Offer section does not appear on the requests of this branch: the operator picks no products or prices.',
    requestOfferHiddenInheritedHint: 'Inherited from the root category "{{category}}". To change it, edit that category.',
    requestOfferHiddenInfo:
      'Hides the Offer section in Gestione Richieste (New request, Work panel and the edit from the table) when every product line of the request belongs to a branch with this rule; with mixed branches the catalogue shows only the products of the visible branches. The offer is still created and existing rows stay as they are; the Offerte module and Contracts are untouched. The ROOT category sets the rule and the whole subtree follows it.',
    requestOfferHiddenInfoLabel: 'More info about Hide the offer in Gestione Richieste',
    isSelectableInfo:
      'Turns the category into a pure container. It stays a parent for its subcategories and keeps every association already made, but it no longer appears in the pickers. Unlike the other rules, this one belongs to THIS category alone: it is never inherited, so an unselectable parent can still have selectable children.',
    isSelectableInfoLabel: 'More info about Selectable',
    isActiveInfo:
      'When the category is not active it disappears, together with all its subcategories and the products of that branch, from the input and edit fields: nothing new can be linked to it. Existing links stay valid and are still shown, and the grid filters keep seeing the category. Unlike Selectable (which keeps the category visible as a container) the Active flag applies to the whole branch, without changing the subcategories\' own flag.',
    isActiveInfoLabel: 'More info about Active',
    isReportableInfo:
      'Decides whether the category shows up as a selectable row in the Gestione Richieste and Gestione Iscritti reports and dashboard. A subcategory inherits its parent\'s value: when the parent is visible in the reports so are its children, but each child can be forced to "no" (and its subcategories with it). An excluded category is not counted in its parent\'s totals either.',
    isReportableInfoLabel: 'More info about Visible in reports',
    reportColumns: 'Report columns',
    reportColumnsInfo:
      'Picks which Gestione Richieste statistics columns are computed for this category: the same selection drives the report (CSV/Excel) and the dashboard. A subcategory inherits the columns of the nearest ancestor that has some configured.',
    reportColumnsInfoLabel: 'More info about Report columns',
    // Counter badge in the card header ("7/11").
    reportColumnsCount: '{{selected}}/{{total}}',
    reportColumnsStatusOwn: 'Own',
    reportColumnsStatusNone: 'No columns',
    reportColumnsSelectAll: 'All',
    reportColumnsSelectNone: 'None',
    reportColumnsError: 'Unable to load the column catalogue. Try again.',
    // No ancestor configured: this category is the configuration's own origin (spec 0141 rev-1).
    reportColumnsOwnHint: 'Set on this category: every subcategory inherits it.',
    // An ancestor has its own columns: this category overrides them ("Back to inherited" is available).
    reportColumnsOverridesHint: 'Overrides the columns inherited from "{{category}}".',
    reportColumnsInheritedHint: 'Inherited from "{{category}}".',
    reportColumnsNoSourceHint:
      'No ancestor category has columns configured. Pick some to include this category in the report and dashboard.',
    reportColumnsUnknownHint: 'Inherited columns will show once this is saved.',
    reportColumnsResetToInherited: 'Back to inherited',
    inheritedFrom: 'Inherited from {{category}}',
    managerLabelLevel: 'A.M. {{n}}',
    managerLabelPlaceholder: 'Account manager {{n}}',
    managerLabelMax: 'The label must be at most 60 characters.',
    managerLabelsHelp:
      'Add a level for each Account Manager rank this category uses. Resetting a level only clears its custom label — it never removes an assigned account manager.',
    addManagerLabelLevel: 'Add level',
    resetManagerLabelLevel: 'Reset A.M. {{n}} to the default label',
    inheritsManagerLabels: 'Inherit from parent',
    inheritsManagerLabelsHint:
      'When off, this category ignores its ancestors: neither it nor its subcategories inherit these labels from above.',
    inheritedManagerLabels: 'Inherited from ancestor categories',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    leaveConfirm: {
      title: 'Leave without saving?',
      description: 'The category has not been created yet: what you entered will be lost.',
      confirm: 'Leave without saving',
      cancel: 'Keep editing',
    },
    created: 'Category created successfully.',
    updated: 'Category updated successfully.',
    deleted: 'Category deleted successfully.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the category. Please try again.',
    sections: {
      identity: {
        title: 'Details',
      },
      rules: {
        title: 'Management rules',
      },
      attributes: {
        title: 'Attributes',
      },
      productAttributes: {
        title: 'Product attributes',
        description: 'Loaded in the Product card (create/edit) for products in this category.',
      },
      quoteAttributes: {
        title: 'Quote attributes',
        description:
          'Loaded in the Quote additional information, when a line uses a product from this category.',
      },
      workOrderAttributes: {
        title: 'Work order attributes',
        description:
          'Loaded in the Work order additional information, when one of its lines uses a product from this category.',
      },
      managerLabels: {
        title: 'Account managers',
      },
    },
  },
}
