@once
  <style>
      :root {
          --keystone-primary: var(--primary, #113199);
          --keystone-primary-hover: var(--primary-strong, #0067a6);
          --keystone-secondary: var(--secondary, #10b981);
          --keystone-danger: var(--danger, #ef4444);
          --keystone-text: var(--text-primary, #e2e8f0);
          --keystone-text-muted: var(--text-muted, #94a3b8);
          --keystone-border: var(--border, rgba(15, 23, 42, 0.12));
          --keystone-bg: var(--surface, #0b1021);
          --keystone-bg-secondary: var(--panel, rgba(15, 23, 42, 0.55));
          --keystone-radius: 0.85rem;
          --keystone-shadow: 0 24px 40px rgba(5, 6, 21, 0.35);
      }

      .keystone-form-container {
          width: 100%;
          max-width: 420px;
          margin: 0 auto;
      }

      .keystone-form {
          background: var(--panel);
          border: 1px solid rgba(15, 23, 42, 0.12);
          border-radius: 1rem;
          padding: 2rem;
          color: var(--keystone-text-primary);
      }

      .keystone-form-group {
          margin-bottom: 1.25rem;
      }

      .keystone-label {
          display: block;
          margin-bottom: 0.35rem;
          font-weight: 600;
          color: var(--text-primary);
      }

      .keystone-input {
          width: 100%;
          padding: 0.75rem 0.9rem;
          border: 1px solid rgba(15, 23, 42, 0.15);
          border-radius: 0.75rem;
          background: rgba(5, 6, 21, 0.28);
          color: var(--panel);
          font-size: 1rem;
          transition: border-color 0.15s ease, color 0.15s ease, background-color 0.15s ease;
      }

      .keystone-input:focus {
          outline: none;
          background-color: var(--panel);
          color: var(--text-primary);
          border-color: var(--primary);
      }

      .keystone-checkbox {
          display: flex;
          align-items: center;
          gap: 0.5rem;
          font-weight: 500;

          color: var(--keystone-text);
      }

      .keystone-button {
          width: 100%;
          padding: 0.9rem 1rem;
          border-radius: 0.85rem;
          border: none;
          background: var(--primary);
          color: var(--panel);
          font-size: 1rem;
          font-weight: 700;
          cursor: pointer;
          transition: transform 0.2s ease;
      }

      .keystone-button:hover {
          transform: translateY(-1px);
      }

      .keystone-button-secondary {
          background: var(--panel);
          color: var(--text-primary);
          border: 1px solid rgba(248, 250, 252, 0.3);
          margin-top: 0.75rem;
      }

      .keystone-error {
          color: var(--keystone-danger);
          font-size: 0.85rem;
          margin-top: 0.25rem;
      }

      .keystone-links {
          display: flex;
          justify-content: space-between;
          margin-top: 1.5rem;
          font-size: 0.875rem;
      }

      .keystone-link {
          color: var(--primary);
          text-decoration: none;
      }

      .keystone-link:hover {
          text-decoration: underline;
      }

      .keystone-divider {
          display: flex;
          align-items: center;
          font-size: 0.75rem;
          letter-spacing: 0.2em;
          text-transform: uppercase;
          color: var(--text-muted);
          margin: 1.5rem 0;
      }

      .keystone-divider::before,
      .keystone-divider::after {
          content: '';
          flex: 1;
          height: 1px;
          background: rgba(15, 23, 42, 0.12);
      }

      .keystone-divider::before {
          margin-right: 0.65rem;
      }

      .keystone-divider::after {
          margin-left: 0.65rem;
      }

      /* Utility buttons + badges */
      .keystone-btn {
          padding: 0.65rem 1rem;
          border-radius: var(--keystone-radius);
          font-size: 0.9rem;
          font-weight: 600;
          cursor: pointer;
          border: 1px solid transparent;
          transition: transform 0.15s ease, background-color 0.15s ease, border-color 0.15s ease;
      }

      .keystone-btn:hover {
          transform: translateY(-1px);
      }

      .keystone-btn:disabled {
          opacity: 0.6;
          cursor: not-allowed;
          transform: none;
      }

      .keystone-btn-primary {
          background: var(--keystone-primary);
          color: var(--surface, #fff);
      }

      .keystone-btn-primary:hover {
          background: var(--keystone-primary-hover);
      }

      .keystone-btn-secondary {
          background: var(--keystone-bg-secondary);
          color: var(--keystone-text);
          border-color: var(--keystone-border);
      }

      .keystone-btn-danger {
          background: var(--keystone-danger);
          color: #fff;
      }

      .keystone-btn-sm {
          padding: 0.4rem 0.65rem;
          font-size: 0.8rem;
      }

      .keystone-text-muted {
          color: var(--keystone-text-muted);
      }

      .keystone-text-success {
          color: #059669;
      }

      .keystone-text-error {
          color: var(--keystone-danger);
      }

      .keystone-badge {
          display: inline-block;
          padding: 0.25rem 0.75rem;
          border-radius: 9999px;
          font-size: 0.75rem;
          font-weight: 600;
      }

      .keystone-badge-role {
          background: #dbeafe;
          color: #1e40af;
      }

      .keystone-badge-permission {
          background: #dcfce7;
          color: #166534;
      }
  </style>
@endonce
