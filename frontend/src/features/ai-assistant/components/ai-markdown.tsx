import type { ComponentProps, CSSProperties, ElementType } from 'react'
import ReactMarkdown, { type Components } from 'react-markdown'
import remarkGfm from 'remark-gfm'
import { cn } from '@/lib/utils'

interface HastNode {
  type: string
  tagName?: string
  value?: string
  properties?: Record<string, unknown>
  children?: HastNode[]
}

const NUMERIC_CELL = /^[+\-\u2212]?\s*(?:[€$]\s*)?\d[\d.,\u00a0 ]*(?:\s*(?:€|%|EUR))?$/
const CELL_TAGS = new Set(['td', 'th'])

function textOf(node: HastNode): string {
  return node.value ?? (node.children ?? []).map(textOf).join('')
}

function rowsOf(table: HastNode): HastNode[][] {
  const rows: HastNode[][] = []
  const walk = (node: HastNode) => {
    if (node.tagName === 'tr') rows.push((node.children ?? []).filter((cell) => CELL_TAGS.has(cell.tagName ?? '')))
    else (node.children ?? []).forEach(walk)
  }
  walk(table)
  return rows
}

/** Marks as right aligned the columns made only of numbers or amounts, unless GFM already aligned them. */
function rehypeNumericColumns() {
  return (tree: HastNode) => {
    const visit = (node: HastNode) => {
      if (node.tagName === 'table') alignNumericColumns(node)
      else (node.children ?? []).forEach(visit)
    }
    visit(tree)
  }
}

function alignNumericColumns(table: HastNode) {
  const [header, ...body] = rowsOf(table)
  header?.forEach((headerCell, column) => {
    const texts = body.map((row) => (row[column] ? textOf(row[column]).trim() : '')).filter((text) => text !== '')
    if (texts.length === 0 || !texts.every((text) => NUMERIC_CELL.test(text))) return
    for (const cell of [headerCell, ...body.map((row) => row[column])]) {
      if (cell && !cell.properties?.style) cell.properties = { ...cell.properties, style: 'text-align: right' }
    }
  })
}

// GFM column alignment reaches the components as a text-align style.
const alignClass = (style?: CSSProperties) =>
  style?.textAlign === 'right' ? 'text-right whitespace-nowrap' : style?.textAlign === 'center' ? 'text-center' : 'text-left'

type WithNode<T> = T & { node?: unknown }

/** Component for a tag with fixed classes; drops the hast `node` prop so it never reaches the DOM. */
function styled<Tag extends 'p' | 'ul' | 'ol' | 'blockquote' | 'pre' | 'code' | 'thead' | 'tr'>(Tag: Tag, className: string) {
  return function Styled(props: WithNode<ComponentProps<Tag>>) {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { node, className: extra, ...rest } = props as WithNode<{ className?: string }>
    const Element: ElementType = Tag
    return <Element {...rest} className={cn(className, extra)} />
  }
}

const components: Components = {
  p: styled('p', 'whitespace-pre-line'),
  h1: styled('p', 'font-semibold'),
  h2: styled('p', 'font-semibold'),
  h3: styled('p', 'font-semibold'),
  h4: styled('p', 'font-semibold'),
  h5: styled('p', 'font-semibold'),
  h6: styled('p', 'font-semibold'),
  ul: styled('ul', 'list-disc pl-5'),
  ol: styled('ol', 'list-decimal pl-5'),
  blockquote: styled('blockquote', 'border-l-2 border-border pl-3 text-muted-foreground'),
  pre: styled('pre', 'overflow-x-auto rounded-md bg-muted p-2 font-mono text-xs [&>code]:bg-transparent [&>code]:p-0'),
  code: styled('code', 'rounded bg-muted px-1 py-0.5 font-mono text-[0.85em]'),
  // Links and images are never rendered as elements: no navigation, no request to a remote source (AC-025).
  a: ({ children }) => <>{children}</>,
  img: ({ alt }) => <>{alt}</>,
  table: ({ children }) => (
    <div className="max-w-full overflow-x-auto rounded-md border border-border [-webkit-overflow-scrolling:touch]">
      <table className="w-full text-xs">{children}</table>
    </div>
  ),
  thead: styled('thead', 'bg-muted/40'),
  tr: styled('tr', 'border-t border-border first:border-t-0 even:bg-muted/20'),
  th: ({ children, style }) => (
    <th scope="col" className={cn('whitespace-nowrap px-2 py-1 font-medium', alignClass(style))}>
      {children}
    </th>
  ),
  td: ({ children, style }) => <td className={cn('px-2 py-1 align-top', alignClass(style))}>{children}</td>,
}

const REMARK_PLUGINS = [remarkGfm]
const REHYPE_PLUGINS = [rehypeNumericColumns]

/** Renders assistant text as React elements; raw HTML stays plain text and links and images never become elements (AC-025). */
export function AiMarkdown({ content }: { content: string }) {
  return (
    <div className="flex flex-col gap-2 break-words [overflow-wrap:anywhere]">
      <ReactMarkdown remarkPlugins={REMARK_PLUGINS} rehypePlugins={REHYPE_PLUGINS} components={components}>
        {content}
      </ReactMarkdown>
    </div>
  )
}
