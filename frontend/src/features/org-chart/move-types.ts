/** Which tree a row belongs to: decides what "moving" a person means (spec 0273 D-4). */
export type OrgView = 'functions' | 'people'

/** A move, already shaped for one of the three endpoints. */
export type OrgMove =
  | { type: 'manager'; userId: number; fromId: number | null; toId: number | null }
  | { type: 'parent'; functionId: number; toId: number | null }
  | { type: 'function'; userId: number; fromId: number | null; toId: number }

/** A valid place to move a node to. `root` has no id: the node becomes a root. */
export interface MoveDestination {
  kind: 'person' | 'function' | 'root'
  id: number | null
  name: string
  depth: number
}

/** A move waiting for the user's confirmation, with the names the summary needs. */
export interface PendingMove {
  move: OrgMove
  subjectName: string
  /** `null` when the subject sits at the root. */
  fromName: string | null
  /** `null` when the destination is the root. */
  toName: string | null
}
