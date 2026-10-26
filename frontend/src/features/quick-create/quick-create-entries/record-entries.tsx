import { lazy } from 'react'
import type { QuickCreateEntry, QuickCreateFormProps } from '@/features/quick-create/types'
import { FINANCIAL_ACCOUNTS_FOR_SELECT_RESOURCE } from '@/features/financial-accounts/for-select-api'
import { LEADS_FOR_SELECT_RESOURCE } from '@/features/leads/for-select-api'
import { OPPORTUNITIES_FOR_SELECT_RESOURCE } from '@/features/opportunities/for-select-api'
import { TASKS_FOR_SELECT_RESOURCE, WORK_ORDERS_FOR_SELECT_RESOURCE } from '@/features/tasks/for-select-api'

/** Separator between a commessa's code and title, matching `WorkOrderForSelectResource::LABEL_SEPARATOR`. */
const WORK_ORDER_LABEL_SEPARATOR = ' — '

/**
 * Quick-create entries for the modules whose create form takes a `mode`, or
 * whose select label is not the record's plain `name` (each adapter mirrors
 * the module's `*ForSelectResource` label, spec 0028 D3).
 */

const financialAccounts: QuickCreateEntry = {
  titleKey: 'financialAccounts.form.newFinancialAccount',
  descriptionKey: 'financialAccounts.form.createSubtitle',
  permission: 'financial-accounts.create',
  form: lazy(async () => {
    const { FinancialAccountForm } = await import('@/features/financial-accounts/financial-account-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <FinancialAccountForm
          mode={{ type: 'create' }}
          onSuccess={(account) => onSuccess({ id: account.id, name: account.name })}
          onCancel={onCancel}
        />
      ),
    }
  }),
}

const leads: QuickCreateEntry = {
  titleKey: 'leads.form.createTitle',
  descriptionKey: 'leads.form.createSubtitle',
  permission: 'leads.create',
  form: lazy(async () => {
    const { LeadForm } = await import('@/features/leads/lead-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <LeadForm
          onSuccess={(lead) => onSuccess({ id: lead.id, name: lead.registry?.name ?? '' })}
          onCancel={onCancel}
        />
      ),
    }
  }),
}

const opportunities: QuickCreateEntry = {
  titleKey: 'opportunities.form.createTitle',
  descriptionKey: 'opportunities.form.createSubtitle',
  permission: 'opportunities.create',
  form: lazy(async () => {
    const { OpportunityForm } = await import('@/features/opportunities/opportunity-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <OpportunityForm
          mode={{ type: 'create' }}
          onSuccess={(opportunity) => onSuccess({ id: opportunity.id, name: opportunity.name })}
          onCancel={onCancel}
        />
      ),
    }
  }),
}

const tasks: QuickCreateEntry = {
  titleKey: 'tasks.form.createTitle',
  descriptionKey: 'tasks.form.createSubtitle',
  permission: 'tasks.create',
  form: lazy(async () => {
    const { TaskForm } = await import('@/features/tasks/task-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <TaskForm
          mode={{ type: 'create' }}
          onSuccess={(task) => onSuccess({ id: task.id, name: task.title })}
          onCancel={onCancel}
        />
      ),
    }
  }),
}

const workOrders: QuickCreateEntry = {
  titleKey: 'workOrders.form.createTitle',
  descriptionKey: 'workOrders.form.createSubtitle',
  permission: 'work-orders.create',
  form: lazy(async () => {
    const { WorkOrderForm } = await import('@/features/work-orders/work-order-form')
    return {
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <WorkOrderForm
          onSuccess={(workOrder) => {
            const title = workOrder.title.trim()
            const name = title === '' ? workOrder.code : `${workOrder.code}${WORK_ORDER_LABEL_SEPARATOR}${title}`
            onSuccess({ id: workOrder.id, name })
          }}
          onCancel={onCancel}
        />
      ),
    }
  }),
}

/** resource -> entry, for the modules covered by this file. */
export const recordEntries: Record<string, QuickCreateEntry> = {
  [FINANCIAL_ACCOUNTS_FOR_SELECT_RESOURCE]: financialAccounts,
  [LEADS_FOR_SELECT_RESOURCE]: leads,
  [OPPORTUNITIES_FOR_SELECT_RESOURCE]: opportunities,
  [TASKS_FOR_SELECT_RESOURCE]: tasks,
  [WORK_ORDERS_FOR_SELECT_RESOURCE]: workOrders,
}
