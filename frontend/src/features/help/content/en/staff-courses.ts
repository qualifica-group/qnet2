import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'staff-courses',
  title: 'Courses',
  summary: 'The catalogue of internal staff courses, with duration, validity and the job roles that require them.',
  sections: [
    {
      id: 'overview',
      title: 'What it is for',
      blocks: [
        {
          type: 'paragraph',
          text: 'It is under **Configuration**. The catalogue lists the internal training courses for staff; the trainings done by people are recorded on the **Training** page. It has nothing to do with the courses sold to customers.',
        },
      ],
    },
    {
      id: 'fields',
      title: 'Fields',
      blocks: [
        {
          type: 'table',
          headers: ['Field', 'What to enter'],
          rows: [
            ['Name', 'The name of the course, unique.'],
            ['Description', 'An optional note about the content.'],
            ['Duration', 'The hours of the course, decimals allowed (for example 7.5). Optional.'],
            ['Validity', 'For how many months (1 to 240) the course stays valid after completion. Empty = never expires.'],
            ['Active', 'Whether the course can be chosen when recording a training.'],
            ['Job roles requiring it', 'People with one of these job roles must hold the course; if it is missing they show as “Missing” in the Status view.'],
          ],
        },
      ],
    },
    {
      id: 'validity',
      title: 'Validity and expiry',
      blocks: [
        {
          type: 'paragraph',
          text: 'The expiry of a training is computed when it is saved, with the validity the course has at that moment. Changing the course validity does not change trainings already recorded.',
        },
      ],
    },
    {
      id: 'constraints',
      title: 'Constraints',
      blocks: [
        {
          type: 'warning',
          text: 'A course with recorded trainings cannot be deleted: deactivate it. Deactivated courses cannot be chosen in new records; trainings already done stay as they were.',
        },
      ],
    },
  ],
}

export default guide
