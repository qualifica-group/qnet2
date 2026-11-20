import { Extension } from '@tiptap/core'
import { Plugin, PluginKey, type EditorState, type Transaction } from '@tiptap/pm/state'
import { Decoration, DecorationSet, type EditorView } from '@tiptap/pm/view'
import type { Node as ProseMirrorNode } from '@tiptap/pm/model'
import { NODE_NAME } from '@/features/work-order-page/editor/constants'

/** CSS hooks styled by `page-prose.ts`. */
export const COLLAPSED_CLASS = 'is-collapsed'
export const HIDDEN_CLASS = 'page-collapsed-hidden'
export const TOGGLE_CLASS = 'page-collapse-toggle'

export interface CollapseLabels {
  expand: string
  collapse: string
}

export interface CollapseStateOptions {
  labels: CollapseLabels
}

interface CollapseState {
  /** Start positions of the closed collapsible headings/blocks. Local only, never part of the document (D-8). */
  closed: ReadonlySet<number>
  decorations: DecorationSet
}

interface CollapseMeta {
  toggle: number
}

export const collapseStateKey = new PluginKey<CollapseState>('pageCollapseState')

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    collapseState: {
      /** Opens/closes the collapsible heading or block starting at `pos`; changes no document content. */
      toggleCollapsed: (pos: number) => ReturnType
    }
  }
}

/** Whether the collapsible heading/block starting at `pos` is currently closed. */
export function isCollapsedAt(state: EditorState, pos: number): boolean {
  return collapseStateKey.getState(state)?.closed.has(pos) ?? false
}

/**
 * True for the editor `update` caused by a collapse toggle only: the trailing
 * paragraph the editor may append on any transaction is not a user edit, so it
 * must not start an autosave (D-8).
 */
export function isCollapseToggle(tr: Transaction): boolean {
  return !tr.docChanged && tr.getMeta(collapseStateKey) !== undefined
}

function isCollapsibleHeading(node: ProseMirrorNode): boolean {
  return node.type.name === NODE_NAME.heading && node.attrs.collapsible === true
}

function createToggleButton(label: string, expanded: boolean, onToggle: () => void): HTMLElement {
  const button = document.createElement('button')
  button.type = 'button'
  button.className = TOGGLE_CLASS
  button.contentEditable = 'false'
  button.setAttribute('aria-label', label)
  button.setAttribute('aria-expanded', String(expanded))
  // Keep the caret where it is: the toggle is a view concern, not an edit.
  button.addEventListener('mousedown', (event) => event.preventDefault())
  button.addEventListener('click', onToggle)
  return button
}

function toggleWidget(pos: number, closed: boolean, labels: CollapseLabels, widgetPos: number): Decoration {
  return Decoration.widget(
    widgetPos,
    (view: EditorView) =>
      createToggleButton(closed ? labels.expand : labels.collapse, !closed, () => {
        view.dispatch(view.state.tr.setMeta(collapseStateKey, { toggle: pos } satisfies CollapseMeta))
      }),
    { side: -1, key: `toggle-${pos}-${closed}` },
  )
}

/** Hides a heading's section: every following sibling until a heading of the same or higher level. */
function scanChildren(
  parent: ProseMirrorNode,
  contentStart: number,
  closed: ReadonlySet<number>,
  labels: CollapseLabels,
  decorations: Decoration[],
): void {
  let hiddenBelowLevel: number | null = null

  parent.forEach((child, offset) => {
    const pos = contentStart + offset
    const isHeading = child.type.name === NODE_NAME.heading
    const level = isHeading ? (child.attrs.level as number) : 0

    if (hiddenBelowLevel !== null && isHeading && level <= hiddenBelowLevel) {
      hiddenBelowLevel = null
    }
    if (hiddenBelowLevel !== null) {
      decorations.push(Decoration.node(pos, pos + child.nodeSize, { class: HIDDEN_CLASS }))
      return
    }

    if (isCollapsibleHeading(child)) {
      const isClosed = closed.has(pos)
      decorations.push(toggleWidget(pos, isClosed, labels, pos + 1))
      if (isClosed) {
        decorations.push(Decoration.node(pos, pos + child.nodeSize, { class: COLLAPSED_CLASS }))
        hiddenBelowLevel = level
      }
      return
    }

    if (child.type.name === NODE_NAME.collapsible) {
      decorateCollapsibleBlock(child, pos, closed, labels, decorations)
    }
    if (!child.isTextblock && !child.isAtom && child.childCount > 0) {
      scanChildren(child, pos + 1, closed, labels, decorations)
    }
  })
}

function decorateCollapsibleBlock(
  block: ProseMirrorNode,
  pos: number,
  closed: ReadonlySet<number>,
  labels: CollapseLabels,
  decorations: Decoration[],
): void {
  const summary = block.firstChild
  const content = block.lastChild
  if (!summary || !content || block.childCount !== 2) {
    return
  }
  const isClosed = closed.has(pos)
  const summaryPos = pos + 1
  const contentPos = summaryPos + summary.nodeSize

  decorations.push(toggleWidget(pos, isClosed, labels, summaryPos + 1))
  if (isClosed) {
    decorations.push(Decoration.node(summaryPos, summaryPos + summary.nodeSize, { class: COLLAPSED_CLASS }))
    decorations.push(Decoration.node(contentPos, contentPos + content.nodeSize, { class: HIDDEN_CLASS }))
  }
}

function buildDecorations(doc: ProseMirrorNode, closed: ReadonlySet<number>, labels: CollapseLabels): DecorationSet {
  const decorations: Decoration[] = []
  scanChildren(doc, 0, closed, labels, decorations)
  return DecorationSet.create(doc, decorations)
}

/**
 * Local open/closed state of collapsible headings and blocks (spec 0420 D-8).
 * It lives in plugin state and is applied through decorations: toggling it is
 * a transaction that never changes the document, so Tiptap emits no `update`
 * and the autosave never fires.
 */
export const CollapseStateExtension = Extension.create<CollapseStateOptions>({
  name: 'collapseState',
  addOptions() {
    return { labels: { expand: 'Expand', collapse: 'Collapse' } }
  },
  addCommands() {
    return {
      toggleCollapsed:
        (pos) =>
        ({ tr, dispatch }) => {
          if (dispatch) {
            tr.setMeta(collapseStateKey, { toggle: pos } satisfies CollapseMeta)
          }
          return true
        },
    }
  },
  addProseMirrorPlugins() {
    const { labels } = this.options

    return [
      new Plugin<CollapseState>({
        key: collapseStateKey,
        state: {
          init: (_config, state) => ({ closed: new Set<number>(), decorations: buildDecorations(state.doc, new Set(), labels) }),
          apply(tr, value, _oldState, newState) {
            const meta = tr.getMeta(collapseStateKey) as CollapseMeta | undefined
            if (!tr.docChanged && !meta) {
              return value
            }

            const closed = new Set<number>()
            value.closed.forEach((pos) => {
              const mapped = tr.mapping.mapResult(pos)
              if (!mapped.deleted) {
                closed.add(mapped.pos)
              }
            })
            if (meta) {
              if (closed.has(meta.toggle)) {
                closed.delete(meta.toggle)
              } else {
                closed.add(meta.toggle)
              }
            }

            return { closed, decorations: buildDecorations(newState.doc, closed, labels) }
          },
        },
        props: {
          decorations: (state) => collapseStateKey.getState(state)?.decorations ?? null,
        },
      }),
    ]
  },
})
