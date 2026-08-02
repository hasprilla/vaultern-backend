<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\ChildSupportAgreement;
use App\Models\User;
use App\Services\ChildGuardianService;
use App\Support\SubscriptionPlanCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\AuthenticatesUsers;
use Tests\TestCase;

class AttachmentsAndChildSupportTest extends TestCase
{
    use AuthenticatesUsers;
    use RefreshDatabase;

    public function test_can_create_transaction_with_attachment(): void
    {
        Storage::fake('public');
        ['tokens' => $tokens, 'family' => $family, 'user' => $user] = $this->createUserWithFamily();
        $child = $this->createChild($family, $user);

        $file = UploadedFile::fake()->image('recibo.jpg');

        $this->post('/api/v1/transactions', [
            'amount' => 50000,
            'type' => 'expense',
            'currency' => 'COP',
            'transaction_date' => now()->toDateString(),
            'child_id' => $child->id,
            'attachments' => [$file],
        ], $this->authHeaders($tokens))
            ->assertCreated()
            ->assertJsonPath('data.attachments.0.kind', 'receipt')
            ->assertJsonStructure(['data' => ['attachments' => [['id', 'url', 'mime_type']]]]);
    }

    public function test_can_create_child_support_agreement_and_payment(): void
    {
        Storage::fake('public');
        SubscriptionPlanCatalog::ensureSeeded();
        ['tokens' => $tokens, 'family' => $family, 'user' => $payer] = $this->createUserWithFamily();
        $beneficiary = User::query()->create([
            'name' => 'Mama',
            'email' => 'mama'.Str::random(4).'@example.com',
            'password' => bcrypt('password'),
            'role' => 'madre',
            'family_id' => $family->id,
            'email_verified_at' => now(),
        ]);
        $child = $this->createChild($family, $payer);

        $this->post('/api/v1/finance/child-support', [
            'child_id' => $child->id,
            'payer_user_id' => $payer->id,
            'beneficiary_user_id' => $beneficiary->id,
            'initial_amount' => 1000000,
            'currency' => 'COP',
            'default_annual_increase_pct' => 5,
            'starts_on' => now()->toDateString(),
            'attachments' => [UploadedFile::fake()->create('acuerdo.pdf', 100, 'application/pdf')],
        ], $this->authHeaders($tokens))
            ->assertCreated()
            ->assertJsonPath('data.current_amount', 1000000);

        $agreementId = ChildSupportAgreement::query()->firstOrFail()->id;

        $this->postJson("/api/v1/finance/child-support/{$agreementId}/adjustments", [
            'increase_pct' => 10,
            'effective_on' => now()->toDateString(),
        ], $this->authHeaders($tokens))
            ->assertOk()
            ->assertJsonPath('data.current_amount', 1100000);

        $this->post("/api/v1/finance/child-support/{$agreementId}/payments", [
            'amount' => 1100000,
            'period_month' => now()->startOfMonth()->toDateString(),
            'paid_on' => now()->toDateString(),
            'attachments' => [UploadedFile::fake()->image('consignacion.jpg')],
        ], $this->authHeaders($tokens))
            ->assertCreated()
            ->assertJsonPath('data.amount', 1100000);
    }

    private function createChild($family, User $parent): User
    {
        $child = User::query()->create([
            'name' => 'Hijo Test',
            'email' => 'hijo'.Str::random(6).'@example.com',
            'password' => bcrypt('password'),
            'role' => 'hijo',
            'family_id' => $family->id,
            'email_verified_at' => now(),
        ]);

        app(ChildGuardianService::class)->syncForChild($child, [(int) $parent->id]);

        return $child;
    }
}
