import { useMutation, useQueryClient, type QueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import type { Editor } from '@tiptap/react'
import { useAuth } from '@/features/auth/use-auth'
import { createTask } from '@/features/tasks/api'
import type { TaskDetail } from '@/features/tasks/types'
import { createPagePoll, retractPagePollVote, votePagePoll } from '@/features/work-order-page/api'
import type { TaskCreateFormValues } from '@/features/work-order-page/blocks/task-create-dialog'
import { workOrderPageKeys } from '@/features/work-order-page/query-keys'
import type { CreatePollPayload, Poll, TaskRef, WorkOrderPagePayload } from '@/features/work-order-page/types'

function patchPage(queryClient: QueryClient, workOrderId: number, patch: (page: WorkOrderPagePayload) => WorkOrderPagePayload) {
  queryClient.setQueryData<WorkOrderPagePayload>(workOrderPageKeys.page(workOrderId), (page) => (page ? patch(page) : page))
}

function mergePoll(queryClient: QueryClient, workOrderId: number, poll: Poll) {
  patchPage(queryClient, workOrderId, (page) => ({ ...page, polls: { ...page.polls, [String(poll.id)]: poll } }))
}

/** Summary of a just created task, so its block renders at once instead of waiting for the page reload. */
export function toTaskRef(task: TaskDetail): TaskRef {
  return {
    id: task.id,
    available: true,
    title: task.title,
    status: { id: task.task_status.id, name: task.task_status.name, color: task.task_status.color },
    end_date: task.end_date,
    completed: task.completion_date !== null,
    assignees: task.assignees.map((assignee) => ({ id: assignee.id, name: assignee.name })),
  }
}

interface UsePageBlockActionsArgs {
  workOrderId: number
  editor: Editor | null
  /** Autosave flush: resolves once every pending/in-flight save is on the server. */
  flush: () => Promise<void>
}

/**
 * Task and poll blocks of the page: creation + insertion, voting.
 *
 * Poll insertion order matters (step comments): the server deletes polls the
 * saved content does not cite, so a save in flight while the poll is created
 * would delete it.
 */
export function usePageBlockActions({ workOrderId, editor, flush }: UsePageBlockActionsArgs) {
  const { t } = useTranslation()
  const { user } = useAuth()
  const queryClient = useQueryClient()

  const voteMutation = useMutation<Poll, unknown, { pollId: number; optionKey: string | null }>({
    mutationFn: ({ pollId, optionKey }) =>
      optionKey === null ? retractPagePollVote(workOrderId, pollId) : votePagePoll(workOrderId, pollId, optionKey),
    onSuccess: (poll) => mergePoll(queryClient, workOrderId, poll),
    onError: () => toast.error(t('workOrderPage.poll.voteError')),
  })

  const insertPollMutation = useMutation<void, unknown, CreatePollPayload>({
    mutationFn: async (payload) => {
      // Step 1: let any save in flight finish, so it cannot prune the new poll
      await flush()
      // Step 2: create the poll
      const poll = await createPagePoll(workOrderId, payload)
      mergePoll(queryClient, workOrderId, poll)
      // Step 3: cite it in the content
      editor?.chain().focus().insertPoll(poll.id).run()
      // Step 4: save right away (no debounce) so the page cites the poll
      await flush()
    },
  })

  const insertTaskMutation = useMutation<void, unknown, TaskCreateFormValues>({
    mutationFn: async (values) => {
      if (!user) {
        throw new Error('No authenticated user')
      }
      const task = await createTask({
        title: values.title.trim(),
        end_date: values.end_date,
        requester_id: user.id,
        assignee_ids: [user.id],
        work_order_id: workOrderId,
      })
      patchPage(queryClient, workOrderId, (page) => ({ ...page, tasks: { ...page.tasks, [String(task.id)]: toTaskRef(task) } }))
      editor?.chain().focus().insertTaskRef(task.id).run()
      await flush()
    },
  })

  return {
    insertPoll: insertPollMutation.mutateAsync,
    insertTask: insertTaskMutation.mutateAsync,
    onVote: (pollId: number, optionKey: string) => voteMutation.mutate({ pollId, optionKey }),
    onRetractVote: (pollId: number) => voteMutation.mutate({ pollId, optionKey: null }),
    pendingPollId: voteMutation.isPending ? (voteMutation.variables?.pollId ?? null) : null,
  }
}
