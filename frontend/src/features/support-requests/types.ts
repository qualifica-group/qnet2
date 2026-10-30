/** Support request types. Source of truth: spec 0334 frozen `data_contract`. */

/** An active problem type offered to every authenticated user (`GET /support-requests/topics`). */
export interface SupportRequestTopic {
  id: number
  name: string
  description: string | null
  /** Curated lucide name or null. */
  icon: string | null
}

/** Result of `POST /support-requests`: the created Task and whether the actor may open it. */
export interface SupportRequestResult {
  task_id: number
  can_view_task: boolean
}
