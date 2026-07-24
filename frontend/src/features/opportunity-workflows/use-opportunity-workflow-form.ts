import { useMemo, useRef, useState } from 'react'
import { useFieldArray, useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import type { TFunction } from 'i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  createOpportunityWorkflow,
  fetchCriterionFields,
  updateOpportunityWorkflow,
} from '@/features/opportunity-workflows/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/opportunity-workflows/opportunity-workflow-form-payload'
import {
  buildCreateOpportunityWorkflowSchema,
  buildUpdateOpportunityWorkflowSchema,
  type CreateOpportunityWorkflowFormValues,
} from '@/features/opportunity-workflows/opportunity-workflow-schema'
import {
  isTailWorkflowSystemKey,
  type OpportunityWorkflowDetail,
  type OpportunityWorkflowFormMode,
  type WorkflowStatusFormRow,
  type WorkflowStatusRowPatch,
} from '@/features/opportunity-workflows/types'

/** How long the (static reference data) allow-listed criterion fields stay fresh. */
const CRITERION_FIELDS_STALE_TIME_MS = 5 * 60 * 1000

/** Server-side field names mapped onto the form for 422 handling. `statuses` is never an RHF path (edited as local state) — its server errors fall back to `statusesError`/`serverError`. */
const SERVER_ERROR_FIELDS = ['name', 'criteria'] as const

export type OpportunityWorkflowFormValues = CreateOpportunityWorkflowFormValues

/** A fresh, still-empty criteria row (mirrors `extra-fields-editor`'s append shape). */
const EMPTY_CRITERION_ROW = { field: null, value_id: null }

/**
 * The four pinned system rows before a workflow exists (AC-004): editable
 * from the start, pre-filled with the default open / validated / closed-won /
 * closed-lost labels, in pinned order (open first, then the validated ->
 * closed-won -> closed-lost tail). The user may rename them up front; they are
 * sent in the create payload (`buildCreatePayload`) so the backend seeds the
 * auto-created rows with these names. Non-deletable/non-reorderable (enforced
 * by the editor).
 */
function initialSystemStatusRows(t: TFunction): WorkflowStatusFormRow[] {
  return [
    {
      id: 'system-open',
      name: t('opportunityWorkflows.form.statuses.defaultOpenName'),
      description: null,
      color: null,
      group: 'open',
      system_key: 'open',
      requires_note: false,
    },
    {
      id: 'system-validated',
      name: t('opportunityWorkflows.form.statuses.defaultValidatedName'),
      description: null,
      color: null,
      group: 'validated',
      system_key: 'validated',
      requires_note: false,
    },
    {
      id: 'system-closed-won',
      name: t('opportunityWorkflows.form.statuses.defaultClosedWonName'),
      description: null,
      color: null,
      group: 'closed_won',
      system_key: 'closed_won',
      requires_note: false,
    },
    {
      id: 'system-closed-lost',
      name: t('opportunityWorkflows.form.statuses.defaultClosedLostName'),
      description: null,
      color: null,
      group: 'closed_lost',
      system_key: 'closed_lost',
      requires_note: false,
    },
  ]
}

/** Hydrates the editable status rows from a persisted workflow (already ordered by `sort_order`). */
function statusRowsFromDetail(opportunityWorkflow: OpportunityWorkflowDetail): WorkflowStatusFormRow[] {
  return opportunityWorkflow.statuses.map((status) => ({
    id: String(status.id),
    statusId: status.id,
    name: status.name,
    description: status.description,
    color: status.color,
    group: status.group,
    system_key: status.system_key,
    requires_note: status.requires_note,
  }))
}

interface UseOpportunityWorkflowFormArgs {
  mode: OpportunityWorkflowFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (opportunityWorkflow: OpportunityWorkflowDetail) => void
}

/**
 * Owns every non-render concern of `OpportunityWorkflowForm` (spec 0047 Lane
 * C): RHF/Zod wiring for `name`/`is_active`/`criteria` (a real field array),
 * the allow-listed criterion fields query, the `statuses` local editor state
 * (SortableList-driven, not an RHF field — mirrors `useStatusReorder`), and
 * the create/update submit (both send the full authoritative
 * criteria+statuses shape in one request).
 */
export function useOpportunityWorkflowForm({ mode, onSuccess }: UseOpportunityWorkflowFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)
  const [statusesError, setStatusesError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const criterionFieldsQuery = useQuery({
    queryKey: ['opportunity-workflows', 'criterion-fields'],
    queryFn: fetchCriterionFields,
    staleTime: CRITERION_FIELDS_STALE_TIME_MS,
  })

  const schema = useMemo(
    () => (isEdit ? buildUpdateOpportunityWorkflowSchema(t) : buildCreateOpportunityWorkflowSchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<OpportunityWorkflowFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.opportunityWorkflow.name,
        is_active: mode.opportunityWorkflow.is_active,
        criteria: mode.opportunityWorkflow.criteria.map((criterion) => ({
          field: criterion.field,
          value_id: criterion.value_id,
        })),
      }
    }
    return { name: '', is_active: true, criteria: [{ ...EMPTY_CRITERION_ROW }] }
  }, [mode])

  const form = useForm<OpportunityWorkflowFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const criteria = useFieldArray({ control: form.control, name: 'criteria' })

  const [statusRows, setStatusRows] = useState<WorkflowStatusFormRow[]>(() =>
    mode.type === 'edit' ? statusRowsFromDetail(mode.opportunityWorkflow) : initialSystemStatusRows(t),
  )

  const nextCustomRowId = useRef(0)

  const addCustomStatus = () => {
    nextCustomRowId.current += 1
    const newRow: WorkflowStatusFormRow = {
      id: `custom-${nextCustomRowId.current}`,
      name: '',
      description: null,
      color: null,
      group: 'pending',
      system_key: null,
      requires_note: false,
    }
    setStatusRows((rows) => {
      const tailIndex = rows.findIndex((row) => isTailWorkflowSystemKey(row.system_key))
      const insertAt = tailIndex === -1 ? rows.length : tailIndex
      return [...rows.slice(0, insertAt), newRow, ...rows.slice(insertAt)]
    })
  }

  const removeCustomStatus = (id: string) => {
    setStatusRows((rows) => rows.filter((row) => row.id !== id))
  }

  const updateStatusRow = (id: string, patch: WorkflowStatusRowPatch) => {
    setStatusRows((rows) => rows.map((row) => (row.id === id ? { ...row, ...patch } : row)))
  }

  const reorderStatusRows = (orderedIds: string[]) => {
    setStatusRows((rows) => {
      const byId = new Map(rows.map((row) => [row.id, row]))
      return orderedIds
        .map((id) => byId.get(id))
        .filter((row): row is WorkflowStatusFormRow => row !== undefined)
    })
  }

  /** Every row (custom AND the now-editable pinned rows) needs a non-empty name (server `required`). */
  const validateStatusRows = (): boolean => {
    const hasEmptyName = statusRows.some((row) => row.name.trim() === '')
    if (hasEmptyName) {
      setStatusesError(t('opportunityWorkflows.form.statuses.nameRequired'))
      return false
    }
    setStatusesError(null)
    return true
  }

  const onSubmit = async (values: OpportunityWorkflowFormValues) => {
    setServerError(null)
    if (!validateStatusRows()) {
      return
    }
    const errorFields: Path<OpportunityWorkflowFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        const saved = await updateOpportunityWorkflow(
          mode.opportunityWorkflow.id,
          buildUpdatePayload(values, statusRows),
        )
        queryClient.setQueryData(['opportunity-workflows', 'detail', mode.opportunityWorkflow.id], saved)
        toast.success(t('opportunityWorkflows.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createOpportunityWorkflow(buildCreatePayload(values, statusRows))
      toast.success(t('opportunityWorkflows.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('opportunityWorkflows.form.genericError'))
      }
    }
  }

  return {
    form,
    isEdit,
    serverError,
    statusesError,
    onSubmit,
    criterionFields: criterionFieldsQuery.data ?? [],
    criteria,
    statusRows,
    addCustomStatus,
    removeCustomStatus,
    updateStatusRow,
    reorderStatusRows,
  }
}
