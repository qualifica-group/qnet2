/**
 * Notes domain (spec 0052): agnostic collaborative-notes feature with
 * mentions, mounted by any host module via `NotesSection`. Sibling file so
 * `en.ts` stays within the engineering size limits (see
 * `.claude/rules/engineering.md` §6).
 */

export const notes = {
  scope: {
    all: 'All notes',
    general: 'General notes',
    filterLabel: 'Filter notes by quote',
    targetLabel: 'Note destination',
    generalBadge: 'General',
  },
  section: {
    title: 'Notes',
    description: 'Discuss the record with colleagues: use @ to mention them.',
    loadError: "Couldn't load the notes.",
    empty: 'No notes yet. Write the first one to start the discussion.',
  },
  list: {
    loadMore: 'Load more',
    replyCount_one: '{{count}} reply',
    replyCount_other: '{{count}} replies',
  },
  item: {
    edited: '(edited)',
    replyAction: 'Reply',
    editAction: 'Edit note',
    deleteAction: 'Delete note',
    deleteConfirm: 'The note will disappear from the list. Any replies stay hidden along with it.',
  },
  composer: {
    placeholder: 'Write a note, use @ to mention a colleague…',
    bodyRequired: 'Write something before sending.',
    bodyTooLong: 'The note can contain at most {{count}} characters.',
    genericError: "Couldn't send. Try again.",
    send: 'Send',
    save: 'Save',
    hint: 'Type @ to mention a colleague',
    removeMention: "Remove {{name}}'s mention",
    charactersLeft_one: '{{count}} character left',
    charactersLeft_other: '{{count}} characters left',
  },
  mentionPicker: {
    label: 'Mentionable users',
    title: 'Mention a colleague',
    hint: 'Tab or Enter to insert',
    empty: 'No matching users',
  },
}
