/**
 * "New opportunity + quote" procedure of the Quotes module (spec 0396).
 * Sibling of `en-quotes.ts` (already over the size limit): merged into
 * `quotes.withOpportunity`.
 */

export const quotesWithOpportunity = {
  chooser: {
    title: 'New quote',
    description: 'Choose how to start: the quote can go on an existing opportunity or be created together with a new one.',
    existing: {
      title: 'From an existing opportunity',
      description: 'Pick the opportunity to create the quote on.',
    },
    new: {
      title: 'New opportunity + quote',
      description: 'Fill in the opportunity and the quote in two steps and save everything at once.',
    },
  },
  tabsLabel: 'Procedure steps',
  tabs: {
    opportunity: 'Opportunity',
    quote: 'Quote',
  },
  hint: {
    opportunity: 'Step 1 of 2: fill in the opportunity. Nothing is saved until the end of step 2.',
    quote: 'Step 2 of 2: fill in the quote. Saving creates both the opportunity and the quote.',
  },
  back: 'Back',
  next: 'Next',
  checking: 'Checking',
  newOpportunity: 'New opportunity (created on save)',
  fixErrors: 'Fill in the required fields of step 1 to continue.',
  existingOpen: 'The registry already has an open opportunity: {{name}}.',
  offerOnExisting: 'Create the quote on this opportunity',
  created: 'Opportunity and quote created.',
  genericError: 'Unable to complete the operation. Please try again.',
}
