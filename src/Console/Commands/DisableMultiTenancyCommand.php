<?php

namespace BSPDX\Keystone\Console\Commands;

use BSPDX\Keystone\Console\Commands\Concerns\InteractsWithKeystone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DisableMultiTenancyCommand extends Command
{
    use InteractsWithKeystone;

    protected $signature = 'keystone:disable-multi-tenancy
        {--force : Run without asking for confirmation}';

    protected $description = 'Return roles, permissions, and assignments to single-tenant form (every tenant_id NULL)';

    /**
     * Assignment pivots and the column naming what is assigned.
     *
     * @var array<string, string>
     */
    private const PIVOTS = ['model_has_roles' => 'role_id', 'model_has_permissions' => 'permission_id'];

    public function handle(): int
    {
        $conflicts = [...$this->nameConflicts('roles', 'Role'), ...$this->nameConflicts('permissions', 'Permission')];

        if ($conflicts !== []) {
            $this->error('These names are used by more than one tenant (or by a tenant and a global row), so they would become duplicates:');
            foreach ($conflicts as $conflict) {
                $this->line("  - {$conflict}");
            }
            $this->line('Rename or merge them, then run this command again. Nothing was changed.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(
            'Every role, permission, and assignment will lose its tenant. Continue?'
        )) {
            return self::FAILURE;
        }

        try {
            $changes = DB::transaction(fn () => $this->clearTenants());
        } catch (\Exception $e) {
            $this->error("Failed to clear tenants: {$e->getMessage()}. Nothing was changed.");

            return self::FAILURE;
        }

        $this->clearPermissionCache();

        foreach ($changes as $table => $count) {
            $this->line("  - {$table}: {$count} row(s) changed");
        }
        $this->info('Keystone data is single-tenant. Set KEYSTONE_MULTI_TENANT=false to switch multi-tenancy off.');

        return self::SUCCESS;
    }

    /**
     * Names (with guard) that more than one row of the table uses.
     *
     * @return list<string>
     */
    private function nameConflicts(string $table, string $label): array
    {
        return DB::table($table)
            ->select('name', 'guard_name')
            ->groupBy('name', 'guard_name')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('name')
            ->get()
            ->map(fn (object $row) => "{$label} '{$row->name}' (guard '{$row->guard_name}')")
            ->all();
    }

    /**
     * Set every Keystone tenant_id to NULL, keeping one row of any
     * assignments that would become identical. Only UPDATE and DELETE
     * statements, so the caller's transaction holds on every database.
     *
     * @return array<string, int> rows changed per table
     */
    private function clearTenants(): array
    {
        $changes = [];

        foreach (['roles', 'permissions'] as $table) {
            $changes[$table] = DB::table($table)->whereNotNull('tenant_id')->update(['tenant_id' => null]);
        }

        foreach (self::PIVOTS as $table => $relatedKey) {
            $removed = $this->removeDuplicateAssignments($table, $relatedKey);
            $changes[$table] = $removed + DB::table($table)->whereNotNull('tenant_id')->update(['tenant_id' => null]);
        }

        return $changes;
    }

    /**
     * Delete all but the oldest row of each assignment held in more than one
     * tenant context.
     */
    private function removeDuplicateAssignments(string $table, string $relatedKey): int
    {
        $groups = DB::table($table)
            ->select($relatedKey, 'model_type', 'model_id', DB::raw('MIN(id) as keep_id'))
            ->groupBy($relatedKey, 'model_type', 'model_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        return $groups->sum(fn (object $group) => DB::table($table)
            ->where($relatedKey, $group->{$relatedKey})
            ->where('model_type', $group->model_type)
            ->where('model_id', $group->model_id)
            ->where('id', '!=', $group->keep_id)
            ->delete());
    }
}
