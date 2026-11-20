import { useCallback, useEffect, useRef, useState } from 'react'
import { EditorContent, useEditor, type Editor } from '@tiptap/react'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import { RICH_TEXT_PLACEHOLDER_CLASS } from '@/components/rich-text/rich-text-prose'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import { TASKS_DOMAIN } from '@/features/tasks/api'
import { PageBlocksContext } from '@/features/work-order-page/blocks/page-blocks-context'
import { PollCreateDialog } from '@/features/work-order-page/blocks/poll-create-dialog'
import { TaskCreateDialog } from '@/features/work-order-page/blocks/task-create-dialog'
import { usePageBlockActions } from '@/features/work-order-page/blocks/use-page-block-actions'
import { isCollapseToggle } from '@/features/work-order-page/editor/collapse-state'
import { createPageExtensions } from '@/features/work-order-page/editor/create-page-extensions'
import { todayIso } from '@/features/work-order-page/editor/page-date'
import { PAGE_PROSE_CLASS } from '@/features/work-order-page/editor/page-prose'
import { PageEditorMenus } from '@/features/work-order-page/menus/page-editor-menus'
import { PageConflictBanner, PageSaveIndicator } from '@/features/work-order-page/page-save-status'
import { workOrderPageKeys } from '@/features/work-order-page/query-keys'
import type { WorkOrderPagePayload } from '@/features/work-order-page/types'
import { usePageAutosave } from '@/features/work-order-page/use-page-autosave'
import { usePageImagePaste } from '@/features/work-order-page/use-page-image-paste'

interface WorkOrderPageEditorProps {
  workOrderId: number
  /** Live page query data: only `tasks`/`polls`/`can_edit` follow it; the text is seeded once. */
  page: WorkOrderPagePayload
  /** `permissions.actions.edit_page` of the work order. */
  canEditAction: boolean
}

/**
 * The page itself: Tiptap editor (editable only with `edit_page` and an open
 * work order), autosave, conflict banner, and the task/poll blocks. The UI
 * menus mount in `PageEditorMenus`.
 */
export function WorkOrderPageEditor({ workOrderId, page, canEditAction }: WorkOrderPageEditorProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [seed] = useState(page)
  const [extensions] = useState(() =>
    createPageExtensions({
      placeholder: t('workOrderPage.placeholder'),
      collapseLabels: { expand: t('workOrderPage.collapse.expand'), collapse: t('workOrderPage.collapse.collapse') },
    }),
  )
  const editorRef = useRef<Editor | null>(null)

  const autosave = usePageAutosave({
    workOrderId,
    initialVersion: seed.version,
    applyServerContent: (content) => editorRef.current?.commands.setContent(content ?? '', { emitUpdate: false }),
  })
  const editable = canEditAction && page.can_edit && !autosave.isClosed

  const editor = useEditor({
    extensions,
    content: seed.content ?? '',
    editable,
    editorProps: {
      attributes: {
        role: 'textbox',
        'aria-multiline': 'true',
        'aria-label': t('workOrderPage.editorLabel'),
        class: 'min-h-64 outline-none',
      },
    },
    // An emptied page is saved as null, like the other rich text fields.
    onUpdate: ({ editor: current, transaction }) => {
      if (!isCollapseToggle(transaction)) {
        autosave.schedule(current.isEmpty ? null : current.getHTML())
      }
    },
  })

  useEffect(() => {
    editorRef.current = editor
  }, [editor])

  useEffect(() => {
    // `false`: switching editability must not emit an update (it would trigger a save).
    editor?.setEditable(editable, false)
  }, [editor, editable])

  const { insertFiles } = usePageImagePaste(editor, editable)
  const actions = usePageBlockActions({ workOrderId, editor, flush: autosave.flush })

  const [taskDialogOpen, setTaskDialogOpen] = useState(false)
  const [pollDialogOpen, setPollDialogOpen] = useState(false)

  const { openView, sheet } = useModuleOpener(TASKS_DOMAIN, {
    forceMode: OPEN_MODE_MODAL,
    onSaved: () => void queryClient.invalidateQueries({ queryKey: workOrderPageKeys.page(workOrderId) }),
  })
  const openTask = useCallback((taskId: number) => openView({ id: taskId, actions: [] }), [openView])

  return (
    <div className="flex flex-col gap-2 p-3">
      <div className="flex min-h-6 items-center justify-between gap-2">
        {editable ? null : (
          <p role="status" className="text-xs text-muted-foreground">
            {t('workOrderPage.readOnly')}
          </p>
        )}
        <div className="ml-auto">
          <PageSaveIndicator status={autosave.status} onRetry={() => void autosave.retry()} />
        </div>
      </div>
      {autosave.conflict ? <PageConflictBanner conflict={autosave.conflict} onReload={autosave.reload} /> : null}
      <PageBlocksContext.Provider
        value={{
          tasks: page.tasks,
          polls: page.polls,
          canVote: editable,
          pendingPollId: actions.pendingPollId,
          onOpenTask: openTask,
          onVote: actions.onVote,
          onRetractVote: actions.onRetractVote,
        }}
      >
        <EditorContent
          editor={editor}
          // `relative` + left padding: the drag handle sits in the gutter, left of the text.
          className={cn(
            'relative min-w-0 rounded-lg bg-surface py-3 pr-4 text-sm',
            editable ? 'pl-8' : 'pl-4',
            PAGE_PROSE_CLASS,
            RICH_TEXT_PLACEHOLDER_CLASS,
          )}
        />
      </PageBlocksContext.Provider>
      {editable && editor ? (
        <PageEditorMenus
          editor={editor}
          onInsertTask={() => setTaskDialogOpen(true)}
          onInsertPoll={() => setPollDialogOpen(true)}
          onPickImages={(files) => void insertFiles(files)}
        />
      ) : null}
      <TaskCreateDialog
        open={taskDialogOpen}
        onOpenChange={setTaskDialogOpen}
        defaultEndDate={todayIso()}
        onSubmit={actions.insertTask}
      />
      <PollCreateDialog open={pollDialogOpen} onOpenChange={setPollDialogOpen} onSubmit={actions.insertPoll} />
      {sheet}
    </div>
  )
}
