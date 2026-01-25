@props(['roles', 'permissions'])

<div class="keystone-roles-permissions">
  @include('keystone::components.keystone-styles')
    <style>
        .keystone-roles-permissions {
            /* Base styles */
        }

        .keystone-subsection {
            margin-bottom: 1.5rem;
        }

        .keystone-subsection:last-child {
            margin-bottom: 0;
        }

        .keystone-subsection h3 {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--keystone-text-muted, #6b7280);
            margin-bottom: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .keystone-badge-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .keystone-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .keystone-badge-role {
            background: #dbeafe;
            color: #1e40af;
        }

        .keystone-badge-permission {
            background: #dcfce7;
            color: #166534;
        }

        .keystone-text-muted {
            color: var(--keystone-text-muted, #6b7280);
            font-size: 0.875rem;
            font-style: italic;
        }
    </style>

    <div class="keystone-subsection">
        <h3>Roles</h3>
        <div class="keystone-badge-list">
            @forelse($roles as $role)
              <span class="keystone-badge keystone-badge-role">{{ ucfirst($role) }}</span>
            @empty
              <span class="keystone-text-muted">No roles assigned</span>
            @endforelse
        </div>
    </div>

    <div class="keystone-subsection">
        <h3>Permissions</h3>
        <div class="keystone-badge-list">
            @forelse($permissions as $permission)
              <span class="keystone-badge keystone-badge-permission">{{ $permission }}</span>
            @empty
              <span class="keystone-text-muted">No permissions assigned</span>
            @endforelse
        </div>
    </div>
</div>
