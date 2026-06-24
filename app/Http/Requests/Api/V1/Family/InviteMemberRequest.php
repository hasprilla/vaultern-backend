<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Family;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Domains\Family\Entities\FamilyRole;

class InviteMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only parents can invite members (canInviteMembers)
        $user = $this->user();
        if (!$user) return false;

        $role = FamilyRole::tryFrom($user->role);
        return $role !== null && $role->canInviteMembers();
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role'  => ['required', 'string', Rule::in(array_column(FamilyRole::cases(), 'value'))],
        ];
    }
}
