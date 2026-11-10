import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'financial-accounts',
  title: 'Financial accounts',
  summary: 'Registry of company accounts: bank accounts, cards and cash accounts.',
  sections: [
    {
      id: 'overview',
      title: 'Overview',
      blocks: [
        { type: 'paragraph', text: 'The module is under **Accounting › Finance › Financial accounts**. It lists three kinds of account in one place: **Bank account**, **Card** and **Cash**. You can filter, sort and export the list; the **Company** column is hidden by default and can be turned on from the column picker.' },
      ],
    },
    {
      id: 'types',
      title: 'Account types and fields',
      blocks: [
        { type: 'paragraph', text: 'When you create an account, pick the **Type** first: the dialog then shows only that type’s fields. The type cannot be changed after creation.' },
        {
          type: 'table',
          headers: ['Type', 'Fields'],
          rows: [
            ['**Bank account**', '**Bank**, **IBAN**, **Account number**, Company, address (street, postal code, location), Notes. Bank, IBAN and Account number are required.'],
            ['**Card**', '**Card type** (Credit or Prepaid), **Bank**, **Circuit** (Visa, Mastercard, Amex), **Linked account**, **Card holder**, **Card number**, **Expiry** (MM/YYYY), Company, Notes.'],
            ['**Cash**', '**Name**, Company, address, Notes. Name is required.'],
          ],
        },
        { type: 'note', text: '**Linked account** lists bank accounts only and is required only for **Credit** cards; it is optional for **Prepaid** cards.' },
      ],
    },
    {
      id: 'iban-check',
      title: 'IBAN check',
      blocks: [
        { type: 'paragraph', text: 'The IBAN is checked formally (structure and check digits, any country) and stored in uppercase without spaces. The same IBAN cannot be used by two accounts.' },
      ],
    },
    {
      id: 'card-security',
      title: 'Card data security',
      blocks: [
        { type: 'warning', text: 'The card **CVV** and **PIN** are never asked for nor stored.' },
        { type: 'paragraph', text: 'The card number is stored encrypted and is always shown **masked** (for example **** 1234). Users with the dedicated permission see a **Show number** button on the record that reveals the full number: every reveal is recorded in the activity log.' },
        { type: 'tip', text: 'When editing, the stored card number is kept: type a new one only if you want to replace it.' },
      ],
    },
    {
      id: 'opening-balance',
      title: 'Opening balance and current balance',
      blocks: [
        { type: 'paragraph', text: 'Every account has an **Opening balance**: the amount the account starts from on the **Opening date**. If you do not set it the balance is 0 and the date is **01/01/2026**; if the account was opened later (for example in March) you can set its own date. Movements, collections and payments before the opening date do not count in the balance or in the **Account statements**.' },
        { type: 'steps', items: ['Open the account record with **View**.', 'In the **Opening balance** box press **Edit**.', 'Enter **Amount** and **Opening date** and press **Save**.'] },
        { type: 'list', items: ['Only whoever has the dedicated permission can change the opening balance; everyone else sees it read-only. Every change goes to the history.', 'You cannot move the opening date after a movement already recorded on the account.', 'The **Current balance** is shown only to people who can view treasury movements, with the **View statement** link that opens the Account statements for that account.'] },
        { type: 'note', text: 'Only accounts have an opening balance: customers and suppliers start from the residual of their installments.' },
      ],
    },
    {
      id: 'manage',
      title: 'Create, edit and delete',
      blocks: [
        { type: 'steps', items: ['Open **Accounting › Finance › Financial accounts** and press **New account**.', 'Pick the **Type** and fill in the fields.', 'Press **Save**.'] },
        { type: 'paragraph', text: 'List rows offer **View** and **Delete**, if your role allows it. To edit, open the record with **View** and press **Edit**.' },
        { type: 'warning', text: 'An account used by invoices, purchase invoices and their installments, treasury movements or bank reconciliations cannot be deleted, nor can a bank account with linked cards (delete or unlink the cards first). The message says what uses it.' },
      ],
    },
  ],
}

export default guide
