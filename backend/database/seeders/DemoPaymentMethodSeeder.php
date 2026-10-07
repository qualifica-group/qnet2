<?php

namespace Database\Seeders;

use App\Enums\VatAllocation;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

/**
 * Seed the payment method lookup (spec 0068) with a realistic catalogue,
 * including multi-installment terms (spec 0194, D-10: 30/60, 30/60/90, end of
 * month, VAT allocations) that exercise the invoice schedules. Idempotent:
 * `updateOrCreate` keyed by `code` (immutable identity, D-3), so re-running
 * never duplicates rows and refreshes every other field in place.
 */
class DemoPaymentMethodSeeder extends Seeder
{
    /** Schedule of a row that omits the installment keys: one due date, VAT split. */
    private const array SINGLE_INSTALLMENT = [
        'installments_count' => 1,
        'days_between_installments' => 0,
        'end_of_month' => false,
        'end_of_month_extra_days' => null,
        'vat_allocation' => VatAllocation::Split,
    ];

    private const string BANK_TRANSFER_INSTRUCTIONS = 'IBAN da comunicare in fattura, causale con numero fattura e rata.';

    private const string RIBA_INSTRUCTIONS = 'Appoggio bancario del cliente obbligatorio.';

    /**
     * @var array<int, array<string, mixed>>
     */
    private const array METHODS = [
        ['name' => 'Bonifico bancario', 'code' => 'bank_transfer', 'payment_method_code' => 'MP05', 'description' => 'Pagamento tramite bonifico bancario ordinario.', 'payment_instructions' => 'IBAN da comunicare in fattura, causale con numero ordine.', 'payment_days' => 30, 'sort_order' => 10, 'is_active' => true],
        ['name' => 'Carta di credito', 'code' => 'credit_card', 'payment_method_code' => 'MP08', 'description' => 'Pagamento con carta di credito tramite POS o link di pagamento.', 'payment_instructions' => 'Addebito immediato al momento della conferma.', 'payment_days' => 0, 'sort_order' => 20, 'is_active' => true],
        ['name' => 'Contanti', 'code' => 'cash', 'payment_method_code' => 'MP01', 'description' => 'Pagamento in contanti alla consegna o presso la sede.', 'payment_instructions' => 'Rilasciare ricevuta fiscale al momento del pagamento.', 'payment_days' => 0, 'sort_order' => 30, 'is_active' => true],
        ['name' => 'Assegno', 'code' => 'check', 'payment_method_code' => 'MP02', 'description' => 'Pagamento tramite assegno bancario o circolare.', 'payment_instructions' => 'Assegno non trasferibile intestato alla ragione sociale.', 'payment_days' => 15, 'sort_order' => 40, 'is_active' => true],
        ['name' => 'Addebito diretto', 'code' => 'direct_debit', 'payment_method_code' => 'MP19', 'description' => 'Addebito diretto SEPA (SDD) sul conto corrente del cliente.', 'payment_instructions' => 'Richiede mandato SDD firmato dal cliente.', 'payment_days' => 30, 'sort_order' => 50, 'is_active' => true],
        ['name' => 'Pagamento rateale', 'code' => 'installments', 'payment_method_code' => 'MP05', 'description' => 'Pagamento dilazionato in rate mensili.', 'payment_instructions' => 'Piano rate concordato in fase di offerta.', 'payment_days' => 90, 'sort_order' => 60, 'is_active' => false],
        ['name' => 'RiBa 30/60 gg DF', 'code' => 'riba_30_60', 'payment_method_code' => 'MP12', 'description' => 'Ricevuta bancaria in due rate a 30 e 60 giorni data fattura.', 'payment_instructions' => self::RIBA_INSTRUCTIONS, 'payment_days' => 30, 'installments_count' => 2, 'days_between_installments' => 30, 'sort_order' => 70, 'is_active' => true],
        ['name' => 'RiBa 30/60/90 gg DF', 'code' => 'riba_30_60_90', 'payment_method_code' => 'MP12', 'description' => 'Ricevuta bancaria in tre rate a 30, 60 e 90 giorni data fattura.', 'payment_instructions' => self::RIBA_INSTRUCTIONS, 'payment_days' => 30, 'installments_count' => 3, 'days_between_installments' => 30, 'sort_order' => 80, 'is_active' => true],
        ['name' => 'RiBa 30/60/90 gg DFFM', 'code' => 'riba_30_60_90_eom', 'payment_method_code' => 'MP12', 'description' => 'Ricevuta bancaria in tre rate a 30, 60 e 90 giorni data fattura fine mese.', 'payment_instructions' => self::RIBA_INSTRUCTIONS, 'payment_days' => 30, 'installments_count' => 3, 'days_between_installments' => 30, 'end_of_month' => true, 'sort_order' => 90, 'is_active' => true],
        ['name' => 'Bonifico 30/60/90/120 gg DFFM +10', 'code' => 'bank_transfer_30_120_eom_10', 'payment_method_code' => 'MP05', 'description' => 'Bonifico in quattro rate a fine mese, posticipate di 10 giorni.', 'payment_instructions' => self::BANK_TRANSFER_INSTRUCTIONS, 'payment_days' => 30, 'installments_count' => 4, 'days_between_installments' => 30, 'end_of_month' => true, 'end_of_month_extra_days' => 10, 'sort_order' => 100, 'is_active' => true],
        ['name' => 'Bonifico 60/90/120 gg DF', 'code' => 'bank_transfer_60_90_120', 'payment_method_code' => 'MP05', 'description' => 'Bonifico in tre rate a 60, 90 e 120 giorni data fattura.', 'payment_instructions' => self::BANK_TRANSFER_INSTRUCTIONS, 'payment_days' => 60, 'installments_count' => 3, 'days_between_installments' => 30, 'sort_order' => 110, 'is_active' => true],
        ['name' => 'Bonifico 30/60/90 gg DF - IVA alla prima rata', 'code' => 'bank_transfer_30_60_90_vat_first', 'payment_method_code' => 'MP05', 'description' => "Tre rate di imponibile, con l'intera IVA sommata alla prima.", 'payment_instructions' => self::BANK_TRANSFER_INSTRUCTIONS, 'payment_days' => 30, 'installments_count' => 3, 'days_between_installments' => 30, 'vat_allocation' => VatAllocation::First, 'sort_order' => 120, 'is_active' => true],
        ['name' => "Bonifico 30/60/90 gg DF - IVA all'ultima rata", 'code' => 'bank_transfer_30_60_90_vat_last', 'payment_method_code' => 'MP05', 'description' => "Tre rate di imponibile, con l'intera IVA sommata all'ultima.", 'payment_instructions' => self::BANK_TRANSFER_INSTRUCTIONS, 'payment_days' => 30, 'installments_count' => 3, 'days_between_installments' => 30, 'vat_allocation' => VatAllocation::Last, 'sort_order' => 130, 'is_active' => true],
        ['name' => 'IVA a vista + 30/60/90 gg DF', 'code' => 'vat_upfront_30_60_90', 'payment_method_code' => 'MP05', 'description' => 'Prima rata di sola IVA a vista, imponibile in tre rate a 30, 60 e 90 giorni.', 'payment_instructions' => self::BANK_TRANSFER_INSTRUCTIONS, 'payment_days' => 0, 'installments_count' => 4, 'days_between_installments' => 30, 'vat_allocation' => VatAllocation::VatFirst, 'sort_order' => 140, 'is_active' => true],
        ['name' => 'Addebito SDD 12 rate mensili FM', 'code' => 'direct_debit_12_monthly', 'payment_method_code' => 'MP19', 'description' => 'Dodici rate mensili addebitate a fine mese.', 'payment_instructions' => 'Richiede mandato SDD firmato dal cliente.', 'payment_days' => 30, 'installments_count' => 12, 'days_between_installments' => 30, 'end_of_month' => true, 'sort_order' => 150, 'is_active' => true],
    ];

    public function run(): void
    {
        foreach (self::METHODS as $method) {
            PaymentMethod::query()->updateOrCreate(
                ['code' => $method['code']],
                array_diff_key($method + self::SINGLE_INSTALLMENT, ['code' => true]),
            );
        }
    }
}
