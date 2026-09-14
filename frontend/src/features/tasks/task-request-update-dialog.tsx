import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { toast } from 'sonner'
import axios from 'axios'
import type { TFunction } from 'i18next'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Checkbox } from '@/components/ui/checkbox'
import { Textarea } from '@/components/ui/textarea'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { actionErrorMessage } from '@/features/tasks/task-action-error-message'
import { useRequestTaskUpdate } from '@/features/tasks/use-task-mutations'
import type { RequestTaskUpdatePayload, TaskDetailWithPermissions, TaskNamedRef } from '@/features/tasks/types'

/** Backend `message` column limit (spec 0118 data_contract: `max:2000`). */
const MESSAGE_MAX_LENGTH = 2000

function buildRequestUpdateSchema(t: TFunction) {
  return z.object({
    recipient_ids: z.array(z.number()).min(1, t('tasks.actions.requestUpdate.recipientsRequired')),
    message: z.string(),
  })
}

type RequestUpdateFormValues = z.infer<ReturnType<typeof buildRequestUpdateSchema>>

function requestUpdateDefaultValues(ids: number[]): RequestUpdateFormValues {
  return { recipient_ids: ids, message: '' }
}

/**
 * Omits `message` when blank (D-12): the server's own default copy takes
 * over. `recipient_ids` is deduped defensively (AC-014): the picker already
 * builds from unique candidates, so this only guards against a future bug.
 */
function buildRequestUpdatePayload(values: RequestUpdateFormValues): RequestTaskUpdatePayload {
  const message = values.message.trim()
  return {
    recipient_ids: Array.from(new Set(values.recipient_ids)),
    ...(message !== '' ? { message } : {}),
  }
}

function toggleRecipient(current: number[], id: number, checked: boolean): number[] {
  if (checked) {
    return current.includes(id) ? current : [...current, id]
  }
  return current.filter((existing) => existing !== id)
}

type RecipientRole = 'assignee' | 'watcher'

interface RecipientCandidate extends TaskNamedRef {
  roles: RecipientRole[]
}

/**
 * Merges assignees and watchers into one candidate per user (spec 0126 D-7):
 * a user who is both keeps a single row, carrying both role tags.
 */
function mergeRecipientCandidates(assignees: TaskNamedRef[], watchers: TaskNamedRef[]): RecipientCandidate[] {
  const byId = new Map<number, RecipientCandidate>()
  assignees.forEach((candidate) => byId.set(candidate.id, { ...candidate, roles: ['assignee'] }))
  watchers.forEach((candidate) => {
    const existing = byId.get(candidate.id)
    if (existing) {
      existing.roles.push('watcher')
    } else {
      byId.set(candidate.id, { ...candidate, roles: ['watcher'] })
    }
  })
  return Array.from(byId.values())
}

function candidateIds(candidates: RecipientCandidate[]): number[] {
  return candidates.map((candidate) => candidate.id)
}

/** Tri-state of the "select all" header checkbox (mirrors `permission-selection.ts`'s `triState`). */
function recipientsSelectionState(candidates: RecipientCandidate[], selected: number[]): boolean | 'indeterminate' {
  if (candidates.length === 0) {
    return false
  }
  const set = new Set(selected)
  const selectedCount = candidates.filter((candidate) => set.has(candidate.id)).length
  if (selectedCount === 0) {
    return false
  }
  return selectedCount === candidates.length ? true : 'indeterminate'
}

function roleLabels(roles: RecipientRole[], t: TFunction): string {
  return roles
    .map((role) => (role === 'assignee' ? t('tasks.form.assignees') : t('tasks.form.watchers')))
    .join(', ')
}

interface RecipientCheckboxProps {
  candidate: RecipientCandidate
  roleLabel: string
  selected: number[]
  onToggle: (id: number, checked: boolean) => void
}

/** One selectable row of the picker (module-level: never defined inside the dialog, frontend.md §10). */
function RecipientCheckbox({ candidate, roleLabel, selected, onToggle }: RecipientCheckboxProps) {
  const checked = selected.includes(candidate.id)
  return (
    <label className="flex items-center gap-2 py-1 text-sm">
      <Checkbox checked={checked} onCheckedChange={(next) => onToggle(candidate.id, next === true)} />
      <span className="truncate">{candidate.name}</span>
      <span className="text-xs text-muted-foreground">{roleLabel}</span>
    </label>
  )
}

interface SelectAllRecipientsCheckboxProps {
  state: boolean | 'indeterminate'
  label: string
  onToggleAll: (checked: boolean) => void
}

/** Header row (D-7): selects/deselects every candidate, indeterminate on a partial selection. */
function SelectAllRecipientsCheckbox({ state, label, onToggleAll }: SelectAllRecipientsCheckboxProps) {
  return (
    <label className="flex items-center gap-2 border-b border-field-border py-1 text-sm font-medium">
      <Checkbox checked={state} onCheckedChange={(next) => onToggleAll(next === true)} aria-label={label} />
      <span>{label}</span>
    </label>
  )
}

interface TaskRequestUpdateDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  task: TaskDetailWithPermissions
}

/**
 * "Richiedi aggiornamento" (spec 0118 D-10..D-14): the recipient picker is
 * built from THIS task's own `assignees`/`watchers`, already in the loaded
 * detail — no user-search endpoint exists for it, deliberately (D-11).
 * Creator/requester are never candidates unless they also happen to be an
 * assignee or a watcher.
 */
export function TaskRequestUpdateDialog({ open, onOpenChange, task }: TaskRequestUpdateDialogProps) {
  const { t } = useTranslation()
  const schema = buildRequestUpdateSchema(t)
  const candidates = mergeRecipientCandidates(task.assignees, task.watchers)
  const hasCandidates = candidates.length > 0

  const form = useForm<RequestUpdateFormValues>({
    resolver: zodResolver(schema),
    defaultValues: requestUpdateDefaultValues(candidateIds(candidates)),
  })

  const requestUpdateMutation = useRequestTaskUpdate({
    taskId: task.id,
    onSuccess: () => {
      toast.success(t('tasks.actions.requestUpdate.success'))
      onOpenChange(false)
      form.reset(requestUpdateDefaultValues(candidateIds(candidates)))
    },
  })

  const onSubmit = async (values: RequestUpdateFormValues) => {
    try {
      await requestUpdateMutation.mutateAsync(buildRequestUpdatePayload(values))
    } catch (error) {
      // A field-scoped 422 (unknown recipient AC-050, message too long
      // AC-054) is wired inline; any other failure (409 bloccato, 422 fase
      // sbagliata) falls back to the shared toast split (AC-064).
      if (axios.isAxiosError(error) && error.response?.status === 422) {
        const errors = error.response.data?.errors
        if (errors?.recipient_ids || errors?.message) {
          applyServerValidationErrors(error, form.setError, ['recipient_ids', 'message'])
          return
        }
      }
      toast.error(actionErrorMessage(t, error))
    }
  }

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) {
          form.reset(requestUpdateDefaultValues(candidateIds(candidates)))
        }
        onOpenChange(next)
      }}
    >
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('tasks.actions.requestUpdate.title')}</DialogTitle>
          <DialogDescription>{t('tasks.actions.requestUpdate.description')}</DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form
            id="task-request-update-form"
            className="flex flex-col gap-4"
            onSubmit={(event) => void form.handleSubmit(onSubmit)(event)}
          >
            <FormField
              control={form.control}
              name="recipient_ids"
              render={({ field }) => (
                <FormItem>
                  <FormLabel required>{t('tasks.actions.requestUpdate.recipients')}</FormLabel>
                  {hasCandidates ? (
                    <FormControl>
                      <div className="flex max-h-48 flex-col gap-1 overflow-auto rounded-md border border-field-border p-2">
                        <SelectAllRecipientsCheckbox
                          state={recipientsSelectionState(candidates, field.value)}
                          label={t('tasks.actions.requestUpdate.selectAll')}
                          onToggleAll={(checked) => field.onChange(checked ? candidateIds(candidates) : [])}
                        />
                        {candidates.map((candidate) => (
                          <RecipientCheckbox
                            key={`candidate-${candidate.id}`}
                            candidate={candidate}
                            roleLabel={roleLabels(candidate.roles, t)}
                            selected={field.value}
                            onToggle={(id, checked) => field.onChange(toggleRecipient(field.value, id, checked))}
                          />
                        ))}
                      </div>
                    </FormControl>
                  ) : (
                    <p className="text-sm text-muted-foreground">
                      {t('tasks.actions.requestUpdate.recipientsEmpty')}
                    </p>
                  )}
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="message"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('tasks.actions.requestUpdate.message')}</FormLabel>
                  <FormControl>
                    <Textarea
                      rows={3}
                      maxLength={MESSAGE_MAX_LENGTH}
                      placeholder={t('tasks.actions.requestUpdate.messagePlaceholder')}
                      value={field.value}
                      onChange={field.onChange}
                      onBlur={field.onBlur}
                      name={field.name}
                      ref={field.ref}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          </form>
        </Form>

        <DialogFooter>
          <Button type="button" variant="outline" className="bg-card" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button
            type="submit"
            form="task-request-update-form"
            disabled={requestUpdateMutation.isPending || !hasCandidates}
          >
            {t('tasks.actions.requestUpdate.submit')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
