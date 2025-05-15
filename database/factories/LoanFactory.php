<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Loan::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            // TODO: Complete factory
            'user_id' => User::factory()->create()->id,
            'terms' => 3,
            'amount' => 5000,
            'outstanding_amount' => 5000 ,
            'currency_code' => Loan::CURRENCY_VND,
            'processed_at' => '2020-01-20',
            'status' => Loan::STATUS_DUE,
        ];
    }
}
