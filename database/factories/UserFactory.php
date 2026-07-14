<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    private const MALE_NAMES = [
        'Rodel', 'Junrey', 'Nestor', 'Vicente', 'Danilo', 'Joel', 'Ramil', 'Crisanto',
    ];

    private const FEMALE_NAMES = [
        'Rosalina', 'Nenita', 'Maricel', 'Analyn', 'Jocelyn', 'Merlita', 'Vilma', 'Gemma',
    ];

    private const LAST_NAMES = [
        'Caballes', 'Lepiten', 'Bantilan', 'Labrador', 'Polinar', 'Maboloc', 'Asoy', 'Lumain',
    ];

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
        $sex = fake()->randomElement(['Male', 'Female']);
        $firstNamePool = $sex === 'Female' ? self::FEMALE_NAMES : self::MALE_NAMES;
        $firstName = fake()->randomElement($firstNamePool);
        $lastName = fake()->randomElement(self::LAST_NAMES);
        $emailHandle = Str::slug($firstName.'.'.$lastName.'.'.fake()->unique()->numberBetween(100, 999));

        return [
            'name' => $firstName.' '.$lastName,
            'email' => $emailHandle.'@healthlink.test',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'approval_status' => User::APPROVAL_APPROVED,
            'registered_via' => 'admin',
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
}
