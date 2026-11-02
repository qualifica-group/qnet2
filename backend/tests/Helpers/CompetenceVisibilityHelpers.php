<?php

use App\Models\BusinessFunction;
use App\Models\EmploymentProfile;
use App\Models\ProductCategory;
use App\Models\User;
use Spatie\Permission\Models\Permission;

// Shared by the `viewCompetence` visibility tests of spec 0340 (one file per module).

if (! function_exists('competenceVisibilityActor')) {
    /**
     * An actor holding `{resource}.{ability}` for every given ability
     * (default: `view` + `viewCompetence`) and no competence yet.
     *
     * @param  array<int, string>  $abilities
     */
    function competenceVisibilityActor(string $resource, array $abilities = ['viewAny', 'view', 'viewCompetence']): User
    {
        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("{$resource}.{$ability}", 'web'));
        }

        return $user;
    }
}

if (! function_exists('competenceVisibilityCategory')) {
    /**
     * A selectable category owning its business function, so a (function,
     * category) competence row on it matches (spec 0111 D-2).
     *
     * @return array{0: BusinessFunction, 1: ProductCategory}
     */
    function competenceVisibilityCategory(?BusinessFunction $function = null): array
    {
        $function ??= BusinessFunction::factory()->create();

        return [$function, ProductCategory::factory()->create(['business_function_id' => $function->id])];
    }
}

if (! function_exists('grantCompetence')) {
    /**
     * Gives $user (creating their employment profile when missing) one
     * competence row per category, all on $function.
     */
    function grantCompetence(User $user, BusinessFunction $function, ProductCategory ...$categories): EmploymentProfile
    {
        $profile = competenceVisibilityProfile($user);

        foreach ($categories as $category) {
            $profile->productLines()->firstOrCreate([
                'business_function_id' => $function->id,
                'product_category_id' => $category->id,
            ]);
        }

        return $profile;
    }
}

if (! function_exists('grantCompetenceOnEveryCategoryOf')) {
    /**
     * A (function, null) row (spec 0129 D-3): every category of $function.
     */
    function grantCompetenceOnEveryCategoryOf(User $user, BusinessFunction $function): EmploymentProfile
    {
        $profile = competenceVisibilityProfile($user);
        $profile->productLines()->firstOrCreate(['business_function_id' => $function->id, 'product_category_id' => null]);

        return $profile;
    }
}

if (! function_exists('grantCompetenceOnAllCategories')) {
    /**
     * The profile-wide wildcard flag (spec 0129 D-1).
     */
    function grantCompetenceOnAllCategories(User $user): EmploymentProfile
    {
        $profile = competenceVisibilityProfile($user);
        $profile->forceFill(['covers_all_product_categories' => true])->save();

        return $profile;
    }
}

if (! function_exists('competenceVisibilityProfile')) {
    function competenceVisibilityProfile(User $user): EmploymentProfile
    {
        return EmploymentProfile::query()->where('user_id', $user->id)->first()
            ?? EmploymentProfile::factory()->create(['user_id' => $user->id]);
    }
}
