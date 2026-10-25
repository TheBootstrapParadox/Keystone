<?php

namespace BSPDX\Keystone\Http\Resources;

use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The management API's representation of a permission.
 *
 * @mixin KeystonePermission
 */
class PermissionResource extends JsonResource
{
    /**
     * @return array{id: int, name: string, guard_name: string, title: string|null, description: string|null, created_at: string|null, roles?: mixed}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'guard_name' => $this->guard_name,
            'title' => $this->title,
            'description' => $this->description,
            'created_at' => $this->created_at?->toJSON(),
            'roles' => $this->whenLoaded(
                'roles',
                fn () => $this->roles->map(fn (KeystoneRole $role) => $role->name)->values()->all()
            ),
        ];
    }
}
