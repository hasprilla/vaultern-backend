<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Rewards;

use App\Models\ChildRewardBalance;
use App\Models\FamilyMember;
use App\Models\FamilyRewardItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\AuthenticatesUsers;
use Tests\TestCase;

class RewardsCatalogApiTest extends TestCase
{
    use AuthenticatesUsers;
    use RefreshDatabase;

    public function test_rewards_catalog_requires_authentication(): void
    {
        $this->getJson('/api/v1/rewards/items')->assertStatus(401);
        $this->postJson('/api/v1/rewards/redeem', [])->assertStatus(401);
    }

    public function test_parent_can_create_item_and_redeem(): void
    {
        ['user' => $parent, 'family' => $family, 'tokens' => $tokens] = $this->createUserWithFamily();
        $child = $this->createChild($family->id);

        $create = $this->postJson('/api/v1/rewards/items', [
            'title' => 'Helado',
            'cost_points' => 20,
        ], $this->authHeaders($tokens))
            ->assertCreated()
            ->assertJsonPath('data.title', 'Helado')
            ->assertJsonPath('data.cost_points', 20);

        $itemId = $create->json('data.id');

        ChildRewardBalance::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'child_user_id' => $child->id,
            'points' => 50,
            'allowance_balance' => 0,
            'currency' => 'COP',
        ]);

        $this->postJson('/api/v1/rewards/redeem', [
            'child_user_id' => $child->id,
            'item_id' => $itemId,
        ], $this->authHeaders($tokens))
            ->assertCreated()
            ->assertJsonPath('data.points', 30)
            ->assertJsonPath('data.cost_points', 20);

        $this->getJson('/api/v1/rewards/summary', $this->authHeaders($tokens))
            ->assertOk()
            ->assertJsonFragment(['id' => $itemId, 'title' => 'Helado', 'cost_points' => 20]);
    }

    public function test_redeem_fails_with_insufficient_points(): void
    {
        ['family' => $family, 'tokens' => $tokens] = $this->createUserWithFamily();
        $child = $this->createChild($family->id);

        $item = FamilyRewardItem::query()->create([
            'family_id' => $family->id,
            'title' => 'Consola',
            'cost_points' => 100,
            'active' => true,
        ]);

        ChildRewardBalance::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'child_user_id' => $child->id,
            'points' => 5,
            'allowance_balance' => 0,
            'currency' => 'COP',
        ]);

        $this->postJson('/api/v1/rewards/redeem', [
            'child_user_id' => $child->id,
            'item_id' => $item->id,
        ], $this->authHeaders($tokens))
            ->assertStatus(422);
    }

    private function createChild(string $familyId): User
    {
        $child = User::factory()->create([
            'family_id' => $familyId,
            'role' => 'hijo',
        ]);
        FamilyMember::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $familyId,
            'user_id' => $child->id,
            'role' => 'hijo',
            'status' => 'active',
        ]);

        return $child;
    }
}
