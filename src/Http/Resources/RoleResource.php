<?php

namespace BSPDX\Keystone\Http\Resources;

use BSPDX\Keystone\Http\Resources\Concerns\ListsNamesVisibleToCaller;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The management API's representation of a role.
 *
 * @mixin KeystoneRole
 */
class RoleResource extends JsonResource
{
    use ListsNamesVisibleToCaller;

    /**
     * @return array{id: int, name: string, guard_name: string, title: string|null, description: string|null, permissions: list<string>, created_at: string|null, users_count?: mixed}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'guard_name' => $this->guard_name,
            'title' => $this->title,
            'description' => $this->description,
            'permissions' => $this->namesVisibleToCaller($request, $this->permissions),
            'created_at' => $this->created_at?->toJSON(),
            'users_count' => $this->whenCounted('users'),
        ];
    }
}
