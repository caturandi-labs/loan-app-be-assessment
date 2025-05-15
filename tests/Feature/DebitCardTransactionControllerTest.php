<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\DebitCard;
use Laravel\Passport\Passport;
use App\Models\DebitCardTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DebitCardTransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected DebitCard $debitCard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->debitCard = DebitCard::factory()->active()->create([
            'user_id' => $this->user->id
        ]);

        Passport::actingAs($this->user);
    }

    public function testCustomerCanSeeAListOfDebitCardTransactions()
    {
        // get /debit-card-transactions
        $this->debitCard->debitCardTransactions()->saveMany(DebitCardTransaction::factory(10)->create([
            'debit_card_id' => $this->debitCard->id
        ]));
        $response = $this->getJson('/api/debit-card-transactions?debit_card_id=' . $this->debitCard->id);
        $response->assertStatus(200);
        $response->assertJsonCount(10);
        $response->assertHeader('Content-Type', 'application/json');
        $this->assertDatabaseCount('debit_card_transactions',10);
        $response->assertJsonStructure([
                '*' => [
                    'amount',
                    'currency_code',
                ],
        ]);

    }

    public function testCustomerCannotSeeAListOfDebitCardTransactionsOfOtherCustomerDebitCard()
    {
        // get /debit-card-transactions
        $user = User::factory()->create();
        $debitCard = DebitCard::factory()->active()->create([
            'user_id' => $user->id
        ]);
        $debitCard->debitCardTransactions()->saveMany(DebitCardTransaction::factory(10)->create([
            'debit_card_id' => $debitCard->id
        ]));
        $response = $this->getJson('/api/debit-card-transactions?debit_card_id=' . $debitCard->id);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertStatus(403);
    }

    public function testCustomerCanCreateADebitCardTransaction()
    {
        // post /debit-card-transactions

        $response = $this->postJson('/api/debit-card-transactions', [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 500,
            'currency_code' => DebitCardTransaction::CURRENCY_SGD,
        ]);
        $response->assertStatus(201);
        $response->assertHeader('Content-Type', 'application/json');
        $this->assertDatabaseCount('debit_card_transactions',1);
        $response->assertJson([
            'amount' => 500,
            'currency_code' => DebitCardTransaction::CURRENCY_SGD,
        ]);

    }

    public function testCustomerCannotCreateADebitCardTransactionToOtherCustomerDebitCard()
    {
        // post /debit-card-transactions
        $user = User::factory()->create();
        $debitCard = DebitCard::factory()->active()->create([
            'user_id' => $user->id
        ]);
        $response = $this->postJson('/api/debit-card-transactions', [
            'debit_card_id' => $debitCard->id,
            'amount' => 500,
            'currency_code' => DebitCardTransaction::CURRENCY_SGD,
        ]);
        $response->assertStatus(403);
        $response->assertHeader('Content-Type', 'application/json');
        $this->assertDatabaseCount('debit_card_transactions',0);
    }

    public function testCustomerCanSeeADebitCardTransaction()
    {
        // get /debit-card-transactions/{debitCardTransaction}
       $dbTx =  DebitCardTransaction::factory()->create([
            'debit_card_id' => $this->debitCard->id,
            'amount' => 500,
            'currency_code' => DebitCardTransaction::CURRENCY_SGD,
       ]);
        $response = $this->getJson('/api/debit-card-transactions/'.$dbTx->id);
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
        $this->assertDatabaseCount('debit_card_transactions',1);
        $response->assertJsonStructure([
            'amount',
            'currency_code',
        ]);
        $response->assertJson([
            'amount' => 500,
            'currency_code' => DebitCardTransaction::CURRENCY_SGD,
        ]);
    }

    public function testCustomerCannotSeeADebitCardTransactionAttachedToOtherCustomerDebitCard()
    {
        // get /debit-card-transactions/{debitCardTransaction}
        $otherUser = User::factory()->create();
        $debitCard = DebitCard::factory()->active()->create(['user_id' => $otherUser->id, 'type' => "Visa"]);
        $dbTx =  DebitCardTransaction::factory()->create([
            'debit_card_id' => $debitCard->id,
            'amount' => 500,
            'currency_code' => DebitCardTransaction::CURRENCY_SGD,
       ]);
        $response = $this->getJson('/api/debit-card-transactions/'.$dbTx->id);
        $response->assertStatus(403);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJsonStructure([
            'message',
        ]);
        $response->assertJson([
            'message' => 'This action is unauthorized.',
        ]);
        $this->assertDatabaseHas('debit_cards', [
            'id' => $debitCard->id,
            'user_id' => $otherUser->id,
        ]);
    }

    // Extra bonus for extra tests :)
}
