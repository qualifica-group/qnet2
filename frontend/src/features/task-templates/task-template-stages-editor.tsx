import { useTranslation } from 'react-i18next'
import { Plus } from 'lucide-react'
import {
  DndContext,
  KeyboardSensor,
  PointerSensor,
  closestCenter,
  useSensor,
  useSensors,
  type DragEndEvent,
} from '@dnd-kit/core'
import { SortableContext, arrayMove, sortableKeyboardCoordinates, verticalListSortingStrategy } from '@dnd-kit/sortable'
import { Button } from '@/components/ui/button'
import {
  UNASSIGNED_STAGE_CONTAINER_ID,
  groupItemRowsByStage,
} from '@/features/task-templates/task-template-item-stage-grouping'
import { TaskTemplateStageCard } from '@/features/task-templates/task-template-stage-card'
import type { TaskTemplateStageMoveOption } from '@/features/task-templates/task-template-stage-item-row'
import type {
  TaskTemplateItemErrors,
  TaskTemplateItemFormRow,
  TaskTemplateItemRowPatch,
  TaskTemplateStageErrors,
  TaskTemplateStageFormRow,
} from '@/features/task-templates/types'

export interface TaskTemplateStagesEditorProps {
  itemRows: TaskTemplateItemFormRow[]
  stageRows: TaskTemplateStageFormRow[]
  itemErrors: TaskTemplateItemErrors
  stageErrors: TaskTemplateStageErrors
  stagedFilesByRow: Record<string, File[]>
  onAddStage: () => void
  onRenameStage: (id: string, name: string) => void
  onRemoveStage: (id: string) => void
  onReorderStages: (orderedIds: string[]) => void
  onAddItem: () => void
  onUpdateItem: (id: string, patch: TaskTemplateItemRowPatch) => void
  onRemoveItem: (id: string) => void
  /** `targetContainerId` is a stage row's own `id`, or the "Senza fase" sentinel. */
  onMoveItem: (rowId: string, targetContainerId: string, targetIndex: number) => void
  onAddStagedFiles: (rowId: string, files: File[]) => void
  onRemoveStagedFile: (rowId: string, index: number) => void
  disabled?: boolean
}

/**
 * The "Fasi" drag board (spec 0146 D-2/AC-031): stage cards reorder via drag
 * (mouse/touch AND keyboard — a single `<SortableContext>` of stage ids), and
 * item rows drag BETWEEN cards through a NESTED `<SortableContext>` per card,
 * all sharing this ONE `DndContext` — that sharing is what makes a
 * cross-card drop resolvable in `handleDragEnd` below. Keyboard users move an
 * item across fasi via its own "Fase" select instead (dnd-kit's default
 * keyboard coordinate getter only reorders within a single
 * `SortableContext`, never across containers — see
 * `TaskTemplateStageItemRow`).
 */
export function TaskTemplateStagesEditor({
  itemRows,
  stageRows,
  itemErrors,
  stageErrors,
  stagedFilesByRow,
  onAddStage,
  onRenameStage,
  onRemoveStage,
  onReorderStages,
  onAddItem,
  onUpdateItem,
  onRemoveItem,
  onMoveItem,
  onAddStagedFiles,
  onRemoveStagedFile,
  disabled = false,
}: TaskTemplateStagesEditorProps) {
  const { t } = useTranslation()
  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  )

  const groups = groupItemRowsByStage(itemRows, stageRows)
  const stageContainerIds = stageRows.map((row) => row.id)
  const moveOptions: TaskTemplateStageMoveOption[] = [
    ...stageRows.map((row) => ({
      containerId: row.id,
      label: row.name.trim() || t('taskTemplates.form.stages.namePlaceholder'),
    })),
    { containerId: UNASSIGNED_STAGE_CONTAINER_ID, label: t('taskTemplates.form.stages.noStage') },
  ]

  const moveItemToEndOfContainer = (rowId: string, targetContainerId: string) => {
    const targetGroup = groups.find((group) => group.containerId === targetContainerId)
    onMoveItem(rowId, targetContainerId, targetGroup ? targetGroup.rows.length : 0)
  }

  function handleDragEnd(event: DragEndEvent) {
    // Step 1: ignore no-op drags (dropped outside, or back onto itself).
    const { active, over } = event
    if (!over || active.id === over.id) {
      return
    }

    // Step 2a: a stage card dragged over another stage card reorders `stageRows`.
    if (active.data.current?.type === 'stage') {
      if (over.data.current?.type !== 'stage') {
        return
      }
      const oldIndex = stageContainerIds.indexOf(String(active.id))
      const newIndex = stageContainerIds.indexOf(String(over.id))
      if (oldIndex === -1 || newIndex === -1) {
        return
      }
      onReorderStages(arrayMove(stageContainerIds, oldIndex, newIndex))
      return
    }

    // Step 2b: an item row dragged over another row, or over an empty
    // container's drop zone, moves it there — within its own group (pure
    // reorder) or across groups (retags `stage_key`, see `onMoveItem`).
    if (active.data.current?.type === 'item') {
      const overData = over.data.current
      const targetContainerId = overData?.type === 'item' || overData?.type === 'container'
        ? (overData.containerId as string)
        : null
      if (targetContainerId === null) {
        return
      }
      const targetGroup = groups.find((group) => group.containerId === targetContainerId)
      const targetIndex =
        overData?.type === 'item' && targetGroup
          ? targetGroup.rows.findIndex((row) => row.id === String(over.id))
          : (targetGroup?.rows.length ?? 0)
      onMoveItem(String(active.id), targetContainerId, targetIndex === -1 ? 0 : targetIndex)
    }
  }

  return (
    <div className="flex flex-col gap-3">
      <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
        <SortableContext items={stageContainerIds} strategy={verticalListSortingStrategy}>
          <div className="flex flex-col gap-3">
            {groups.map((group) => (
              <TaskTemplateStageCard
                key={group.containerId}
                group={group}
                nameError={group.stage ? stageErrors[group.stage.id]?.name : undefined}
                errors={itemErrors}
                stagedFilesByRow={stagedFilesByRow}
                moveOptions={moveOptions}
                onRenameStage={onRenameStage}
                onRemoveStage={onRemoveStage}
                onUpdateRow={onUpdateItem}
                onRemoveRow={onRemoveItem}
                onAddStagedFiles={onAddStagedFiles}
                onRemoveStagedFile={onRemoveStagedFile}
                onMoveToStage={moveItemToEndOfContainer}
                disabled={disabled}
              />
            ))}
          </div>
        </SortableContext>
      </DndContext>

      <div className="flex flex-wrap gap-2">
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="border-dashed text-muted-foreground hover:text-foreground"
          disabled={disabled}
          onClick={onAddStage}
        >
          <Plus aria-hidden="true" />
          {t('taskTemplates.form.stages.add')}
        </Button>
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="border-dashed text-muted-foreground hover:text-foreground"
          disabled={disabled}
          onClick={onAddItem}
        >
          <Plus aria-hidden="true" />
          {t('taskTemplates.form.items.add')}
        </Button>
      </div>
    </div>
  )
}
