/** Contract of the work order workspace page (spec 0420 data_contract). */

export interface PageUserRef {
  id: number
  name: string
}

export interface TaskRefStatus {
  id: number
  name: string
  /** A `BADGE_COLOR_TOKENS` token, never a hex. */
  color: string
}

/** Summary of a task cited in the page. `available: false` = not visible/deleted/other work order: everything else is empty. */
export interface TaskRef {
  id: number
  available: boolean
  title: string | null
  status: TaskRefStatus | null
  /** `YYYY-MM-DD`. */
  end_date: string | null
  completed: boolean
  assignees: PageUserRef[]
}

export interface PollOption {
  key: string
  label: string
  votes: number
  voters: PageUserRef[]
}

export interface Poll {
  id: number
  question: string
  options: PollOption[]
  my_vote: string | null
  total_votes: number
}

export interface WorkOrderPagePayload {
  /** HTML (page profile) or `null` when the page does not exist / is empty. */
  content: string | null
  /** 0 while the page does not exist yet. */
  version: number
  updated_at: string | null
  updated_by: PageUserRef | null
  can_edit: boolean
  tasks: Record<string, TaskRef>
  polls: Record<string, Poll>
}

export interface SavePagePayload {
  content: string | null
  version: number
}

export interface CreatePollPayload {
  question: string
  options: string[]
}

/** `code` of the 409 answered when the saved `version` is stale. */
export const PAGE_VERSION_CONFLICT_CODE = 'page_version_conflict'
