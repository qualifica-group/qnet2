/**
 * The `<form>` wiring shared by the create sheet, edit sheet and page screen
 * (spec 0122 MT-F2): calls `useTimeEntryForm`, hydrates the editor from
 * `mode`, and renders the scrollable editor plus the action bar. Kept out of
 * the three call sites so none of them repeats the same destructuring.
 */

import { useEffect } from 'react'
import { Form } from '@/components/ui/form'
import { TimeEntryEditor } from '@/features/time-entries/form/time-entry-editor'
import { TimeEntryFormActions } from '@/features/time-entries/form/time-entry-form-actions'
import { workOrderRefOf } from '@/features/time-entries/form/time-entry-context-fields'
import { useTimeEntryForm, type TimeEntryFormMode } from '@/features/time-entries/form/use-time-entry-form'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { TimeEntry } from '@/features/time-entries/types'
import { cn } from '@/lib/utils'

interface TimeEntryFormBodyProps {
  mode: TimeEntryFormMode
  onSuccess: (entry: TimeEntry) => void
  onCancel: () => void
  onDeleted?: () => void
  /** Reports `formState.isDirty` changes so a Sheet wrapper can guard its close (AC "unsaved changes"). */
  onDirtyChange?: (dirty: boolean) => void
  showTitle?: boolean
  showContext?: boolean
  headerTitle: string
  headerDescription?: string
  /** Extra classes for the sticky footer (e.g. `sticky bottom-0` inside a Sheet). */
  footerClassName?: string
}

function refOf(value: { id: number; name: string } | null | undefined): RelationFieldRef | null {
  return value ? { id: value.id, name: value.name } : null
}

export function TimeEntryFormBody({
  mode,
  onSuccess,
  onCancel,
  onDeleted,
  onDirtyChange,
  showTitle = true,
  showContext = true,
  headerTitle,
  headerDescription,
  footerClassName,
}: TimeEntryFormBodyProps) {
  const {
    form,
    isTaskLinked,
    taskQuery,
    serverError,
    isSubmitting,
    onSubmit,
    handleRegistryChange,
    handleOpportunityItemChange,
    handleWorkOrderItemChange,
    handleStartTimeChange,
    handleEndTimeChange,
    resetToDefaults,
  } = useTimeEntryForm({ mode, onSuccess })

  const isDirty = form.formState.isDirty
  useEffect(() => {
    onDirtyChange?.(isDirty)
  }, [isDirty, onDirtyChange])

  const entry = mode.type === 'edit' ? mode.entry : null

  return (
    <Form {...form}>
      <form onSubmit={onSubmit} className="flex flex-1 flex-col overflow-hidden" noValidate>
        <div className="flex-1 overflow-y-auto p-4">
          <TimeEntryEditor
            control={form.control}
            disabled={isSubmitting}
            showTitle={showTitle}
            showContext={showContext}
            headerTitle={headerTitle}
            headerDescription={headerDescription}
            isTaskLinked={isTaskLinked}
            taskDetail={taskQuery.data}
            registry={refOf(entry?.registry)}
            opportunity={refOf(entry?.opportunity)}
            workOrder={workOrderRefOf(entry?.work_order)}
            task={entry?.task ? { id: entry.task.id, name: entry.task.title } : null}
            onRegistryChange={handleRegistryChange}
            onOpportunityItemChange={handleOpportunityItemChange}
            onWorkOrderItemChange={handleWorkOrderItemChange}
            onStartTimeChange={handleStartTimeChange}
            onEndTimeChange={handleEndTimeChange}
            serverError={serverError}
          />
        </div>
        <div className={cn('border-t p-4', footerClassName)}>
          <TimeEntryFormActions
            mode={mode.type}
            isSubmitting={isSubmitting}
            onCancel={onCancel}
            onReset={mode.type === 'create' ? resetToDefaults : undefined}
            entryId={mode.type === 'edit' ? mode.entry.id : undefined}
            onDeleted={onDeleted}
          />
        </div>
      </form>
    </Form>
  )
}
