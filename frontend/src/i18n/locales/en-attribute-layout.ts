/**
 * Localized strings for the attribute-layout configurator (spec 0062,
 * `features/attributes/layout-configurator/`) and its mount inside the
 * Product Category detail (`features/product-categories/
 * product-category-attribute-layout-section.tsx`). Registered as its own
 * i18next namespace (`attributeLayout`), mirroring `en-migrations.ts` —
 * `en.ts`/`it.ts` stay untouched (owned by other in-flight lanes).
 */
export const attributeLayout = {
  configurator: {
    paletteTitle: 'Unplaced attributes',
    paletteDescription: 'Drag an attribute into a row to place it.',
    paletteEmpty: 'Every effective attribute is placed.',
    paletteDragHandleLabel: 'Drag {{name}}',
    addSection: 'Add section',
    removeSectionLabel: 'Remove section',
    moveSectionUpLabel: 'Move section up',
    moveSectionDownLabel: 'Move section down',
    sectionTitleLabel: 'Title',
    sectionTitlePlaceholder: 'Section title',
    sectionDescriptionLabel: 'Description',
    sectionVariantLabel: 'Appearance',
    sectionColumnsLabel: 'Columns',
    sectionCollapsibleLabel: 'Collapsible',
    sectionDefaultCollapsedLabel: 'Collapsed by default',
    sectionAdvancedLabel: 'Advanced section',
    addRow: 'Add row',
    removeRowLabel: 'Remove row',
    moveRowUpLabel: 'Move row up',
    moveRowDownLabel: 'Move row down',
    rowEmpty: 'Drop an attribute here',
    itemWidthLabel: 'Width',
    removeItemLabel: 'Remove {{name}} from the section',
    variant: {
      default: 'Default',
      highlighted: 'Highlighted',
      informative: 'Informative',
      secondary: 'Secondary',
    },
    width: {
      full: 'Full',
      two_thirds: 'Two thirds',
      half: 'Half',
      third: 'One third',
    },
    preview: {
      title: 'Live preview',
      description: 'Reflects the layout exactly as it will render.',
    },
    untitledSection: 'Untitled section',
  },
  section: {
    title: 'Attribute layout',
    description: 'Arrange this category’s attributes into sections and grids for forms and the detail view.',
    contextLabel: 'Context',
    context: {
      product: 'Product',
      opportunity: 'Opportunity',
    },
    modeLabel: 'Form mode',
    mode: {
      create: 'Create',
      edit: 'Edit',
      view: 'View',
    },
    save: 'Save layout',
    saving: 'Saving…',
    saved: 'Layout saved.',
    loadError: 'Unable to load the layout.',
    saveError: 'Unable to save the layout.',
    forbidden: 'You do not have permission to edit this layout.',
    invalid: 'The layout is invalid — check the placed attributes and field limits.',
    empty: 'No layout configured: the plain attribute list is used instead.',
    retry: 'Retry',
    editorHint: 'The layout is saved separately from the category’s own data.',
    createHint: 'Save the category to configure the attribute layout.',
    previewEmpty: 'No layout configured for this combination.',
  },
}
