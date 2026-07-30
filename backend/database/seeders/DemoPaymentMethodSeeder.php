<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

/**
 * Seed the payment method lookup (spec 0068) with a realistic catalogue.
 * Standalone anagraphic: no dependency on anything else, no consumer module
 * references it yet (out of scope). Idempotent: `updateOrCreate` keyed by
 * `code` (immutable identity, D-3), so re-running never duplicates rows and
 * refreshes name/description/payment_instructions/payment_days/sort_order/
 * is_active in place.
 */
class DemoPaymentMethodSeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, code: string, description: string, payment_instructions: string, payment_days: int|null, sort_order: int, is_active: bool}>
     */
    private const array METHODS = [
        ['name' => 'Bonifico bancario', 'code' => 'bank_transfer', 'description' => 'Pagamento tramite bonifico bancario ordinario.', 'payment_instructions' => 'IBAN da comunicare in fattura, causale con numero ordine.', 'payment_days' => 30, 'sort_order' => 10, 'is_active' => true],
        ['name' => 'Carta di credito', 'code' => 'credit_card', 'description' => 'Pagamento con carta di credito tramite POS o link di pagamento.', 'payment_instructions' => 'Addebito immediato al momento della conferma.', 'payment_days' => 0, 'sort_order' => 20, 'is_active' => true],
        ['name' => 'Contanti', 'code' => 'cash', 'description' => 'Pagamento in contanti alla consegna o presso la sede.', 'payment_instructions' => 'Rilasciare ricevuta fiscale al momento del pagamento.', 'payment_days' => 0, 'sort_order' => 30, 'is_active' => true],
        ['name' => 'Assegno', 'code' => 'check', 'description' => 'Pagamento tramite assegno bancario o circolare.', 'payment_instructions' => 'Assegno non trasferibile intestato alla ragione sociale.', 'payment_days' => 15, 'sort_order' => 40, 'is_active' => true],
        ['name' => 'Addebito diretto', 'code' => 'direct_debit', 'description' => 'Addebito diretto SEPA (SDD) sul conto corrente del cliente.', 'payment_instructions' => 'Richiede mandato SDD firmato dal cliente.', 'payment_days' => 30, 'sort_order' => 50, 'is_active' => true],
        ['name' => 'Pagamento rateale', 'code' => 'installments', 'description' => 'Pagamento dilazionato in rate mensili.', 'payment_instructions' => 'Piano rate concordato in fase di offerta.', 'payment_days' => 90, 'sort_order' => 60, 'is_active' => false],
    ];

    public function run(): void
    {
        foreach (self::METHODS as $method) {
            PaymentMethod::query()->updateOrCreate(
                ['code' => $method['code']],
                [
                    'name' => $method['name'],
                    'description' => $method['description'],
                    'payment_instructions' => $method['payment_instructions'],
                    'payment_days' => $method['payment_days'],
                    'sort_order' => $method['sort_order'],
                    'is_active' => $method['is_active'],
                ],
            );
        }
    }
}
