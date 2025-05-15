<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\DebitCard;
use Laravel\Passport\Passport;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DebitCardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Passport::actingAs($this->user);
    }

    public function testCustomerCanSeeAListOfDebitCards()
    {
        // get /debit-cards
        DebitCard::factory()->count(10)->create(['user_id' => $this->user->id, 'disabled_at' => null]);
        $response = $this->getJson('/api/debit-cards');
        $response->assertStatus(200);
        $response->assertJsonCount(10);
        $response->assertHeader('Content-Type', 'application/json');
        $this->assertDatabaseCount('debit_cards',10);
        $response->assertJsonStructure([
                '*' => [
                    'id',
                    'number',
                    'type',
                    'expiration_date',
                    'user_id',
                ],
        ]);
    }

    public function testCustomerCannotSeeAListOfDebitCardsOfOtherCustomers()
    {
         DebitCard::factory()->count(10)->create(['user_id' => $this->user->id, 'disabled_at' => null]);

        // get /debit-cards
        $otherUser = User::factory()->create();
        DebitCard::factory()->count(5)->create(['user_id' => $otherUser->id]);

        $response = $this->getJson('/api/debit-cards');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJsonCount(10);
        $this->assertDatabaseCount('debit_cards',15);
        $response->assertJsonStructure([
            '*' => [
                'id',
                'number',
                'type',
                'expiration_date',
                'user_id',
            ],
        ]);

    }

    public function testCustomerCanCreateADebitCard()
    {
        // post /debit-cards
        $response = $this->postJson('/api/debit-cards', [
            'type' => 'Visa',
        ]);
        $response->assertStatus(201);
        $response->assertHeader('Content-Type', 'application/json');
        $this->assertDatabaseCount('debit_cards',1);
        $response->assertJson([
            'type' => 'Visa',
        ]);

    }

    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id, 'type' => "Visa"]);
        $response = $this->getJson('/api/debit-cards/'.$debitCard->id);
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
        $this->assertDatabaseCount('debit_cards',1);
        $response->assertJsonStructure([
            'id',
            'user_id',
            'number',
            'type',
            'expiration_date',
        ]);
        $response->assertJson([
            'type' => 'Visa',
        ]);
    }

    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}
        $otherUser = User::factory()->create();
        $debitCard = DebitCard::factory()->create(['user_id' => $otherUser->id, 'type' => "Visa"]);
        $response = $this->getJson('/api/debit-cards/'.$debitCard->id);
        // $response->dump();
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

    public function testCustomerCanActivateADebitCard()
    {
        // put api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id, 'disabled_at' => null]);
        $response = $this->putJson('/api/debit-cards/'.$debitCard->id, [
            'is_active' => false,
        ]);
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
        $this->assertDatabaseCount('debit_cards',1);
        $response->assertJsonStructure([
            'id',
            'user_id',
            'number',
            'type',
            'expiration_date',
        ]);
        $response->assertJson([
            'is_active' => false,
        ]);
       $debitInDb =  DB::table('debit_cards')->where('id', $debitCard->id)->first();
        $this->assertDatabaseHas('debit_cards', [
            'id' => $debitInDb->id,
            'disabled_at' => $debitInDb->disabled_at,
        ]);
    }

    public function testCustomerCanDeactivateADebitCard()
    {
        // put api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id, 'disabled_at' => now()]);
        $response = $this->putJson('/api/debit-cards/'.$debitCard->id, [
            'is_active' => true,
        ]);
        // $response->assertStatus(200)->dump();
        $response->assertHeader('Content-Type', 'application/json');
        $this->assertDatabaseCount('debit_cards',1);
        $response->assertJson([
            'is_active' => true,
        ]);
        $response->assertJsonStructure([
            'id',
            'user_id',
            'number',
            'type',
            'expiration_date',
        ]);
        $this->assertDatabaseHas('debit_cards', [
            'disabled_at' => null,
        ]);
    }

    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        // put api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id, 'disabled_at' => now()]);
        $response = $this->putJson('/api/debit-cards/'.$debitCard->id, [
            'is_active' => '',
        ]);
        $response->assertStatus(422);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJsonStructure([
            'message',
            'errors' => [
                'is_active',
            ],
        ]);
        $response->assertJsonValidationErrors(['is_active' => 'The is active field is required.']);

    }

    public function testCustomerCanDeleteADebitCard()
    {
        // delete api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id, 'disabled_at' => now()]);
        $response = $this->deleteJson("/api/debit-cards/{$debitCard->id}");
        $response->assertStatus(204);
        $this->assertSoftDeleted($debitCard);
    }

    public function testCustomerCannotDeleteADebitCardWithTransaction()
    {
        // delete api/debit-cards/{debitCard}
        $user = User::factory()->create();
        $debitCard = DebitCard::factory()->create(['user_id' => $user->id, 'disabled_at' => now()]);
        $response = $this->deleteJson('/api/debit-cards/'.$debitCard->id);
        $response->assertStatus(403);

    }

    // Extra bonus for extra tests :)
}
