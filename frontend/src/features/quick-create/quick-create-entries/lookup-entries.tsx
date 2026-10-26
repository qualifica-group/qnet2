import { lazy } from 'react'
import type { QuickCreateEntry, QuickCreateFormProps } from '@/features/quick-create/types'
import { COMPANY_SITES_FOR_SELECT_RESOURCE } from '@/features/company-sites/for-select-api'
import { CONTRACT_TYPES_FOR_SELECT_RESOURCE } from '@/features/contract-types/for-select-api'
import { DOCUMENT_LAYOUTS_FOR_SELECT_RESOURCE } from '@/features/document-layouts/for-select-api'
import { INVOICE_TAGS_FOR_SELECT_RESOURCE } from '@/features/invoice-tags/for-select-api'
import { PAYMENT_METHODS_FOR_SELECT_RESOURCE } from '@/features/payment-methods/for-select-api'
import { PRODUCT_TYPOLOGIES_FOR_SELECT_RESOURCE } from '@/features/product-typologies/for-select-api'
import { PURCHASE_REQUEST_PRIORITIES_FOR_SELECT_RESOURCE } from '@/features/purchase-request-priorities/for-select-api'
import { TASK_CATEGORIES_FOR_SELECT_RESOURCE } from '@/features/task-categories/for-select-api'
import { TASK_IMPORTANCES_FOR_SELECT_RESOURCE } from '@/features/task-importances/for-select-api'
import { TASK_PRIORITIES_FOR_SELECT_RESOURCE } from '@/features/task-priorities/for-select-api'
import { TASK_STATUSES_FOR_SELECT_RESOURCE } from '@/features/task-statuses/for-select-api'
import { TASK_TYPES_FOR_SELECT_RESOURCE } from '@/features/task-types/for-select-api'
import { TASK_TEMPLATES_FOR_SELECT_RESOURCE } from '@/features/task-templates/for-select-api'
import { UNITS_OF_MEASURE_FOR_SELECT_RESOURCE } from '@/features/units-of-measure/for-select-api'
import { VAT_RATES_FOR_SELECT_RESOURCE } from '@/features/vat-rates/for-select-api'

/**
 * Quick-create entries for the lookup catalogs (task lookups, commerce and
 * document catalogs, company sites): their create form takes no mode and
 * their select label is the record's `name`, like the entries in
 * `module-entries.tsx` (spec 0028 D3: every async relation to a module).
 */

const companySites: QuickCreateEntry = {
  titleKey: 'companySites.form.createTitle',
  descriptionKey: 'companySites.form.createSubtitle',
  permission: 'company-sites.create',
  form: lazy(async () => {
    const { CompanySiteForm } = await import('@/features/company-sites/company-site-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <CompanySiteForm onSuccess={(companySite) => onSuccess({ id: companySite.id, name: companySite.name })} onCancel={onCancel} />
      ),
    }
  }),
}

const contractTypes: QuickCreateEntry = {
  titleKey: 'contractTypes.form.createTitle',
  descriptionKey: 'contractTypes.form.createSubtitle',
  permission: 'contract-types.create',
  form: lazy(async () => {
    const { ContractTypeForm } = await import('@/features/contract-types/contract-type-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <ContractTypeForm onSuccess={(contractType) => onSuccess({ id: contractType.id, name: contractType.name })} onCancel={onCancel} />
      ),
    }
  }),
}

const documentLayouts: QuickCreateEntry = {
  titleKey: 'documentLayouts.form.createTitle',
  descriptionKey: 'documentLayouts.form.createSubtitle',
  permission: 'document-layouts.create',
  form: lazy(async () => {
    const { DocumentLayoutForm } = await import('@/features/document-layouts/document-layout-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <DocumentLayoutForm onSuccess={(documentLayout) => onSuccess({ id: documentLayout.id, name: documentLayout.name })} onCancel={onCancel} />
      ),
    }
  }),
}

const invoiceTags: QuickCreateEntry = {
  titleKey: 'invoiceTags.form.createTitle',
  descriptionKey: 'invoiceTags.form.createSubtitle',
  permission: 'invoice-tags.create',
  form: lazy(async () => {
    const { InvoiceTagForm } = await import('@/features/invoice-tags/invoice-tag-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <InvoiceTagForm onSuccess={(invoiceTag) => onSuccess({ id: invoiceTag.id, name: invoiceTag.name })} onCancel={onCancel} />
      ),
    }
  }),
}

const paymentMethods: QuickCreateEntry = {
  titleKey: 'paymentMethods.form.createTitle',
  descriptionKey: 'paymentMethods.form.createSubtitle',
  permission: 'payment-methods.create',
  form: lazy(async () => {
    const { PaymentMethodForm } = await import('@/features/payment-methods/payment-method-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <PaymentMethodForm onSuccess={(paymentMethod) => onSuccess({ id: paymentMethod.id, name: paymentMethod.name })} onCancel={onCancel} />
      ),
    }
  }),
}

const productTypologies: QuickCreateEntry = {
  titleKey: 'productTypologies.form.createTitle',
  descriptionKey: 'productTypologies.form.createSubtitle',
  permission: 'product-typologies.create',
  form: lazy(async () => {
    const { ProductTypologyForm } = await import('@/features/product-typologies/product-typology-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <ProductTypologyForm onSuccess={(productTypology) => onSuccess({ id: productTypology.id, name: productTypology.name })} onCancel={onCancel} />
      ),
    }
  }),
}

const purchaseRequestPriorities: QuickCreateEntry = {
  titleKey: 'purchaseRequestPriorities.form.createTitle',
  descriptionKey: 'purchaseRequestPriorities.form.createSubtitle',
  permission: 'purchase-request-priorities.create',
  form: lazy(async () => {
    const { PurchaseRequestPriorityForm } = await import('@/features/purchase-request-priorities/purchase-request-priority-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <PurchaseRequestPriorityForm onSuccess={(priority) => onSuccess({ id: priority.id, name: priority.name })} onCancel={onCancel} />
      ),
    }
  }),
}

const taskCategories: QuickCreateEntry = {
  titleKey: 'taskCategories.form.createTitle',
  descriptionKey: 'taskCategories.form.createSubtitle',
  permission: 'task-categories.create',
  form: lazy(async () => {
    const { TaskCategoryForm } = await import('@/features/task-categories/task-category-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <TaskCategoryForm onSuccess={(taskCategory) => onSuccess({ id: taskCategory.id, name: taskCategory.name })} onCancel={onCancel} />
      ),
    }
  }),
}

const taskImportances: QuickCreateEntry = {
  titleKey: 'taskImportances.form.createTitle',
  descriptionKey: 'taskImportances.form.createSubtitle',
  permission: 'task-importances.create',
  form: lazy(async () => {
    const { TaskImportanceForm } = await import('@/features/task-importances/task-importance-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <TaskImportanceForm onSuccess={(taskImportance) => onSuccess({ id: taskImportance.id, name: taskImportance.name })} onCancel={onCancel} />
      ),
    }
  }),
}

const taskPriorities: QuickCreateEntry = {
  titleKey: 'taskPriorities.form.createTitle',
  descriptionKey: 'taskPriorities.form.createSubtitle',
  permission: 'task-priorities.create',
  form: lazy(async () => {
    const { TaskPriorityForm } = await import('@/features/task-priorities/task-priority-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <TaskPriorityForm onSuccess={(taskPriority) => onSuccess({ id: taskPriority.id, name: taskPriority.name })} onCancel={onCancel} />
      ),
    }
  }),
}

const taskStatuses: QuickCreateEntry = {
  titleKey: 'taskStatuses.form.createTitle',
  descriptionKey: 'taskStatuses.form.createSubtitle',
  permission: 'task-statuses.create',
  form: lazy(async () => {
    const { TaskStatusForm } = await import('@/features/task-statuses/task-status-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <TaskStatusForm onSuccess={(taskStatus) => onSuccess({ id: taskStatus.id, name: taskStatus.name })} onCancel={onCancel} />
      ),
    }
  }),
}

const taskTypes: QuickCreateEntry = {
  titleKey: 'taskTypes.form.createTitle',
  descriptionKey: 'taskTypes.form.createSubtitle',
  permission: 'task-types.create',
  form: lazy(async () => {
    const { TaskTypeForm } = await import('@/features/task-types/task-type-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <TaskTypeForm onSuccess={(taskType) => onSuccess({ id: taskType.id, name: taskType.name })} onCancel={onCancel} />
      ),
    }
  }),
}

const taskTemplates: QuickCreateEntry = {
  titleKey: 'taskTemplates.form.createTitle',
  descriptionKey: 'taskTemplates.form.createSubtitle',
  permission: 'task-templates.create',
  form: lazy(async () => {
    const { TaskTemplateForm } = await import('@/features/task-templates/task-template-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <TaskTemplateForm onSuccess={(taskTemplate) => onSuccess({ id: taskTemplate.id, name: taskTemplate.name })} onCancel={onCancel} />
      ),
    }
  }),
}

const unitsOfMeasure: QuickCreateEntry = {
  titleKey: 'unitsOfMeasure.form.createTitle',
  descriptionKey: 'unitsOfMeasure.form.createSubtitle',
  permission: 'units-of-measure.create',
  form: lazy(async () => {
    const { UnitOfMeasureForm } = await import('@/features/units-of-measure/unit-of-measure-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <UnitOfMeasureForm onSuccess={(unitOfMeasure) => onSuccess({ id: unitOfMeasure.id, name: unitOfMeasure.name })} onCancel={onCancel} />
      ),
    }
  }),
}

const vatRates: QuickCreateEntry = {
  titleKey: 'vatRates.form.createTitle',
  descriptionKey: 'vatRates.form.createSubtitle',
  permission: 'vat-rates.create',
  form: lazy(async () => {
    const { VatRateForm } = await import('@/features/vat-rates/vat-rate-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <VatRateForm onSuccess={(vatRate) => onSuccess({ id: vatRate.id, name: vatRate.name })} onCancel={onCancel} />
      ),
    }
  }),
}

/** resource -> entry, for the lookup catalogs covered by this file. */
export const lookupEntries: Record<string, QuickCreateEntry> = {
  [COMPANY_SITES_FOR_SELECT_RESOURCE]: companySites,
  [CONTRACT_TYPES_FOR_SELECT_RESOURCE]: contractTypes,
  [DOCUMENT_LAYOUTS_FOR_SELECT_RESOURCE]: documentLayouts,
  [INVOICE_TAGS_FOR_SELECT_RESOURCE]: invoiceTags,
  [PAYMENT_METHODS_FOR_SELECT_RESOURCE]: paymentMethods,
  [PRODUCT_TYPOLOGIES_FOR_SELECT_RESOURCE]: productTypologies,
  [PURCHASE_REQUEST_PRIORITIES_FOR_SELECT_RESOURCE]: purchaseRequestPriorities,
  [TASK_CATEGORIES_FOR_SELECT_RESOURCE]: taskCategories,
  [TASK_IMPORTANCES_FOR_SELECT_RESOURCE]: taskImportances,
  [TASK_PRIORITIES_FOR_SELECT_RESOURCE]: taskPriorities,
  [TASK_STATUSES_FOR_SELECT_RESOURCE]: taskStatuses,
  [TASK_TYPES_FOR_SELECT_RESOURCE]: taskTypes,
  [TASK_TEMPLATES_FOR_SELECT_RESOURCE]: taskTemplates,
  [UNITS_OF_MEASURE_FOR_SELECT_RESOURCE]: unitsOfMeasure,
  [VAT_RATES_FOR_SELECT_RESOURCE]: vatRates,
}
