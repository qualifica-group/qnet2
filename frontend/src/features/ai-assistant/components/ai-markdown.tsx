import type { ReactNode } from 'react'
import { parseMarkdown, type BlockNode, type InlineNode } from '@/features/ai-assistant/markdown-parse'

function renderInline(nodes: InlineNode[]): ReactNode[] {
  return nodes.map((node, index) => {
    switch (node.type) {
      case 'text':
        return node.value
      case 'break':
        return <br key={index} />
      case 'bold':
        return <strong key={index}>{renderInline(node.children)}</strong>
      case 'italic':
        return <em key={index}>{renderInline(node.children)}</em>
      case 'code':
        return (
          <code key={index} className="rounded bg-muted px-1 py-0.5 font-mono text-[0.85em]">
            {node.value}
          </code>
        )
    }
  })
}

function renderBlock(block: BlockNode, index: number): ReactNode {
  switch (block.type) {
    case 'paragraph':
      return <p key={index}>{renderInline(block.children)}</p>
    case 'heading':
      return (
        <p key={index} className="font-semibold">
          {renderInline(block.children)}
        </p>
      )
    case 'quote':
      return (
        <blockquote key={index} className="border-l-2 border-border pl-3 text-muted-foreground">
          {renderInline(block.children)}
        </blockquote>
      )
    case 'code':
      return (
        <pre key={index} className="overflow-x-auto rounded-md bg-muted p-2 font-mono text-xs">
          <code>{block.value}</code>
        </pre>
      )
    case 'list': {
      const Tag = block.ordered ? 'ol' : 'ul'
      return (
        <Tag key={index} className={block.ordered ? 'list-decimal pl-5' : 'list-disc pl-5'}>
          {block.items.map((item, itemIndex) => (
            <li key={itemIndex}>{renderInline(item)}</li>
          ))}
        </Tag>
      )
    }
  }
}

/** Renders assistant text as React elements; the content is never interpreted as HTML (AC-025). */
export function AiMarkdown({ content }: { content: string }) {
  return <div className="flex flex-col gap-2 break-words [overflow-wrap:anywhere]">{parseMarkdown(content).map(renderBlock)}</div>
}
