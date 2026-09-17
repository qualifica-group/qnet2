<?php

namespace Database\Seeders;

use App\Enums\PersonalDataTypeEnum;
use App\Models\Contact;
use App\Models\PersonalData;
use Database\Seeders\Concerns\SeedsDevelopmentUsers;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;

class DemoUserContactSeeder extends Seeder
{
    use SeedsDevelopmentUsers;

    public function run(): void
    {
        $faker = FakerFactory::create('it_IT');
        $faker->seed(20260617);

        $this->seededUsersQuery()
            ->with('personalData')
            ->whereHas('personalData')
            ->orderBy('email')
            ->get()
            ->each(fn ($user) => $this->seedContacts($faker, $user->personalData, $user->email));
    }

    private function seedContacts(Generator $faker, PersonalData $card, string $loginEmail): void
    {
        $card->contacts()->delete();

        Contact::factory()->email()->primary()->for($card, 'contactable')->create([
            'value' => $loginEmail,
            'label' => $card->type === PersonalDataTypeEnum::Company ? 'General email' : 'Personal email',
        ]);

        Contact::factory()->phone()->primary()->for($card, 'contactable')->create();

        if ($card->type === PersonalDataTypeEnum::Company) {
            Contact::factory()->phone()->for($card, 'contactable')->create([
                'label' => 'Switchboard',
            ]);
            Contact::factory()->pec()->for($card, 'contactable')->create([
                'label' => 'Certified email',
                'value' => $this->pecForCompany($card, $loginEmail),
            ]);
            Contact::factory()->website()->for($card, 'contactable')->create([
                'label' => 'Website',
                'value' => $this->websiteForCompany($card),
            ]);

            return;
        }

        Contact::factory()->phone()->for($card, 'contactable')->create([
            'label' => 'Home',
        ]);

        if ($faker->boolean(45)) {
            Contact::factory()->email()->for($card, 'contactable')->create([
                'label' => 'Backup email',
            ]);
        }
    }

    private function pecForCompany(PersonalData $card, string $loginEmail): string
    {
        $company = $card->company_name ?? $card->full_name;
        $host = preg_replace('/[^a-z0-9]+/', '-', strtolower($company));
        $host = trim((string) $host, '-');

        if ($host === '') {
            return 'info@pec.example.test';
        }

        return "info@{$host}.pec.it";
    }

    private function websiteForCompany(PersonalData $card): string
    {
        $company = $card->company_name ?? $card->full_name;
        $host = preg_replace('/[^a-z0-9]+/', '-', strtolower($company));
        $host = trim((string) $host, '-');

        return $host === '' ? 'https://www.example.test' : "https://www.{$host}.it";
    }
}
