@props(['user'])

<div class="keystone-account-info">
  @include('keystone::components.keystone-styles')
    <style>
        .keystone-account-info {
            /* Base styles */
        }

        .keystone-info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--keystone-border, #e5e7eb);
        }

        .keystone-info-row:last-child {
            border-bottom: none;
        }

        .keystone-info-label {
            font-weight: 500;
            color: var(--keystone-text-muted, #6b7280);
        }

        .keystone-info-value {
            color: var(--keystone-text, #1f2937);
        }
    </style>

    <div class="keystone-info-row">
      <span class="keystone-info-label">Name</span>
      <span class="keystone-info-value">{{ $user->name }}</span>
    </div>
    <div class="keystone-info-row">
      <span class="keystone-info-label">Email</span>
      <span class="keystone-info-value">{{ $user->email }}</span>
    </div>
    @if($user->email_verified_at)
      <div class="keystone-info-row">
        <span class="keystone-info-label">Email Verified</span>
        <span class="keystone-info-value">{{ $user->email_verified_at->format('M j, Y') }}</span>
      </div>
    @endif
</div>
