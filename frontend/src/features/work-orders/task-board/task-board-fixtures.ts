/**
 * Shared fixtures for the Task board's tests: one builder per shape of the
 * frozen `data_contract` (spec 0146), so a contract change is edited in ONE
 * place instead of drifting across test files. Mirrors `tasks/task-fixtures.ts`.
 */

import { FULL_ACCESS_PERMISSIONS } from '@/features/tasks/task-fixtures'
import type { BoardTask, TaskBoardPayload, WorkOrderStage } from '@/features/work-orders/task-board/types'

export function workOrderStage(overrides: Partial<WorkOrderStage> = {}): WorkOrderStage {
  return {
    id: 1,
    name: 'Sopralluogo',
    sort_order: 0,
    closed_at: null,
    closed_by: null,
    logged_minutes: 0,
    ...overrides,
  }
}

export function boardTask(overrides: Partial<BoardTask> = {}): BoardTask {
  return {
    id: 100,
    title: 'Verificare impianto',
    description_excerpt: null,
    parent_task_id: null,
    work_order_stage_id: 1,
    stage_position: 0,
    task_status: {
      id: 3,
      name: 'In lavorazione',
      color: 'blue',
      icon: null,
      system_key: 'open',
      group: 'open',
      completion_percentage: 25,
    },
    task_type: null,
    task_priority: null,
    task_importance: null,
    requester: { id: 21, name: 'Bruno Bianchi' },
    assignees: [{ id: 31, name: 'Dario Dini' }],
    watchers: [],
    start_date: null,
    end_date: '2026-09-25',
    estimated_minutes: 60,
    actual_minutes: 0,
    is_blocked: false,
    attachments_count: 0,
    permissions: FULL_ACCESS_PERMISSIONS,
    ...overrides,
  }
}

export function taskBoardPayload(overrides: Partial<TaskBoardPayload> = {}): TaskBoardPayload {
  return {
    stages: [workOrderStage()],
    tasks: [boardTask()],
    is_read_only: false,
    unstaged_logged_minutes: 0,
    ...overrides,
  }
}
