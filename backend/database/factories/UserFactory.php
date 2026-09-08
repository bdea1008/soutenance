<?php

namespace Database\Factories;

use App\Enums\KycStatus;
use App\Enums\PromoterType;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => "$firstName $lastName",
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+2217'.fake()->numerify('#######'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Investor->value,
            'kyc_status' => KycStatus::None->value,
            'country' => 'Sénégal',
            'city' => fake()->randomElement(['Dakar', 'Thiès', 'Saint-Louis', 'Touba', 'Ziguinchor']),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Compte promoteur. Le sous-type commande les pièces exigées et les paliers
     * d'abonnement ouverts : un promoteur sans sous-type n'existe pas côté
     * inscription, il ne doit pas exister davantage dans les seeds.
     */
    public function promoter(PromoterType $type = PromoterType::Company): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Promoter->value,
            'promoter_type' => $type->value,
            ...$type === PromoterType::Company ? [
                'company_name' => fake()->company().' Immobilier',
                'legal_form' => fake()->randomElement(['SARL', 'SA', 'SUARL']),
                'registration_number' => 'SN-DKR-'.fake()->numberBetween(2015, 2024).'-B-'.fake()->numberBetween(1000, 9999),
                'tax_number' => (string) fake()->numberBetween(1000000, 9999999),
                'signatory_role' => fake()->randomElement(['Gérant', 'Directeur général', 'Président']),
            ] : [],
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Admin->value]);
    }

    public function legal(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Legal->value]);
    }

    public function kycVerified(): static
    {
        return $this->state(fn (array $attributes) => ['kyc_status' => KycStatus::Verified->value]);
    }
}
