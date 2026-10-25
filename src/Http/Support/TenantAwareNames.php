<?php

namespace BSPDX\Keystone\Http\Support;

use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Support\DefaultGuard;
use BSPDX\Keystone\Support\MultiTenancy;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Role or permission names in a request, resolved in one tenant on one guard
 * (the default auth guard when null).
 *
 * The validation rule from rule() and resolve() share one lookup per name.
 *
 * @template TModel of KeystoneRole|KeystonePermission
 *
 * @internal
 */
final class TenantAwareNames
{
    /**
     * Names already looked up: name => model, or null when it did not resolve.
     *
     * @var array<string, TModel|null>
     */
    private array $resolved = [];

    /**
     * @param  class-string<TModel>  $model
     */
    public function __construct(private string $model, private ?string $tenantId, private ?string $guard = null) {}

    /**
     * @return self<KeystoneRole>
     */
    public static function roles(?string $tenantId, ?string $guard = null): self
    {
        return new self(KeystoneRole::class, $tenantId, $guard);
    }

    /**
     * @return self<KeystonePermission>
     */
    public static function permissions(?string $tenantId, ?string $guard = null): self
    {
        return new self(KeystonePermission::class, $tenantId, $guard);
    }

    /**
     * Unique name among rows with the same guard in the given tenant (global
     * rows for a null tenant; any row in single-tenant mode).
     */
    public static function uniqueRule(string $table, ?string $guard, ?string $tenantId): Unique
    {
        $guard = DefaultGuard::resolve($guard);

        return Rule::unique($table, 'name')->where(function ($query) use ($guard, $tenantId) {
            $query->where('guard_name', $guard);

            if (MultiTenancy::enabled()) {
                $tenantId === null ? $query->whereNull('tenant_id') : $query->where('tenant_id', $tenantId);
            }
        });
    }

    /**
     * Validation rule: the name must resolve in this tenant.
     */
    public function rule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (is_string($value) && $this->find($value) === null) {
                $fail('The selected :attribute is invalid.');
            }
        };
    }

    /**
     * Resolve validated names to models.
     *
     * @param  array<int, string>  $names
     * @return Collection<int, TModel>
     *
     * @throws ModelNotFoundException
     */
    public function resolve(array $names): Collection
    {
        return collect($names)
            ->map(fn (string $name) => $this->find($name) ?? throw (new ModelNotFoundException)->setModel($this->model, [$name]))
            ->values();
    }

    /**
     * @return TModel|null
     */
    private function find(string $name): KeystoneRole|KeystonePermission|null
    {
        if (! array_key_exists($name, $this->resolved)) {
            try {
                $this->resolved[$name] = $this->model::findByNameForTenant($name, $this->tenantId, $this->guard);
            } catch (ModelNotFoundException) {
                $this->resolved[$name] = null;
            }
        }

        return $this->resolved[$name];
    }
}
