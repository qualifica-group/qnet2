import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { TooltipProvider } from '@/components/ui/tooltip'
import { AttributeAssignmentSection } from '@/features/product-categories/attribute-assignment-section'
import type { AttributeCatalogEntry } from '@/features/attributes/use-attribute-catalog'
import type {
  AttributeAssignmentInput,
  AttributeContext,
  ProductCategoryInheritedAttribute,
} from '@/features/product-categories/types'

interface AttributeAssignmentEditorProps {
  /** Flat, both contexts — `attribute_id` may repeat once per context (two pivot rows). */
  value: AttributeAssignmentInput[]
  onChange: (next: AttributeAssignmentInput[]) => void
  /**
   * Name/type of the attributes the category was loaded with, so an assigned
   * row labels itself without depending on the picker's search window.
   */
  known: AttributeCatalogEntry[]
  /** Read-only, flat, both contexts — attributes inherited from the selected parent's ancestry chain. */
  inherited: ProductCategoryInheritedAttribute[]
  disabled?: boolean
  /** The Product section's "inherit from parent" switch, owned by the form (RHF + field permissions); null on a root category. */
  productInheritToggle?: ReactNode
  /** Same, for the Opportunity section — the two barriers are independent. */
  opportunityInheritToggle?: ReactNode
  /** Spec 0084: la barriera di ereditarieta' del contesto `quote`, indipendente dalle altre due. */
  quoteInheritToggle?: ReactNode
}

/**
 * The category form's attribute-assignment editor (spec 0061): TWO clearly
 * separated, graphically distinct sections — "Attributi Prodotto" (loaded in
 * the Product card) and "Attributi Opportunità" (loaded in the Opportunity
 * preliminary info) — each its own picker/list/inherited-list, filtered from
 * the same flat `value`/`inherited` arrays by `context`. The same catalogue
 * attribute may be assigned to either section, or both (two independent rows
 * in `value`, distinguished by `context`). Each section also hosts its OWN
 * "inherit from parent" switch, injected by the form as a slot so the RHF and
 * field-permission wiring stays out of here.
 */
export function AttributeAssignmentEditor({
  value,
  onChange,
  known,
  inherited,
  disabled,
  productInheritToggle,
  opportunityInheritToggle,
  quoteInheritToggle,
}: AttributeAssignmentEditorProps) {
  const { t } = useTranslation()

  const byContext = (context: AttributeContext) => value.filter((a) => a.context === context)
  const inheritedByContext = (context: AttributeContext) => inherited.filter((a) => a.context === context)

  const addAssignment = (context: AttributeContext, attributeId: number) => {
    const inSection = byContext(context)
    onChange([...value, { attribute_id: attributeId, context, is_required: false, sort_order: inSection.length }])
  }

  const updateAssignment = (
    context: AttributeContext,
    attributeId: number,
    patch: Partial<AttributeAssignmentInput>,
  ) => {
    onChange(
      value.map((assignment) =>
        assignment.attribute_id === attributeId && assignment.context === context
          ? { ...assignment, ...patch }
          : assignment,
      ),
    )
  }

  const removeAssignment = (context: AttributeContext, attributeId: number) => {
    onChange(value.filter((a) => !(a.attribute_id === attributeId && a.context === context)))
  }

  return (
    <TooltipProvider>
      <div className="flex flex-col gap-3">
        <p className="text-xs text-muted-foreground">{t('productCategories.form.attributesHelp')}</p>

        <AttributeAssignmentSection
          title={t('productCategories.form.sections.productAttributes.title')}
          description={t('productCategories.form.sections.productAttributes.description')}
          assignments={byContext('product')}
          known={known}
          inherited={inheritedByContext('product')}
          inheritToggle={productInheritToggle}
          disabled={disabled}
          onAdd={(attributeId) => addAssignment('product', attributeId)}
          onUpdate={(attributeId, patch) => updateAssignment('product', attributeId, patch)}
          onRemove={(attributeId) => removeAssignment('product', attributeId)}
        />

        <AttributeAssignmentSection
          title={t('productCategories.form.sections.opportunityAttributes.title')}
          description={t('productCategories.form.sections.opportunityAttributes.description')}
          assignments={byContext('opportunity')}
          known={known}
          inherited={inheritedByContext('opportunity')}
          inheritToggle={opportunityInheritToggle}
          disabled={disabled}
          onAdd={(attributeId) => addAssignment('opportunity', attributeId)}
          onUpdate={(attributeId, patch) => updateAssignment('opportunity', attributeId, patch)}
          onRemove={(attributeId) => removeAssignment('opportunity', attributeId)}
        />

        <AttributeAssignmentSection
          title={t('productCategories.form.sections.quoteAttributes.title')}
          description={t('productCategories.form.sections.quoteAttributes.description')}
          assignments={byContext('quote')}
          known={known}
          inherited={inheritedByContext('quote')}
          inheritToggle={quoteInheritToggle}
          disabled={disabled}
          onAdd={(attributeId) => addAssignment('quote', attributeId)}
          onUpdate={(attributeId, patch) => updateAssignment('quote', attributeId, patch)}
          onRemove={(attributeId) => removeAssignment('quote', attributeId)}
        />
      </div>
    </TooltipProvider>
  )
}
