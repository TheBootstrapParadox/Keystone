<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>BSPDX Keystone - Complete Laravel Authentication</title>
    <style>
        :root {
            --primary: #1f43aa;
            --accent: #cdb334;
            --secondary: #aa1f1f;
            --surface: #050615;
            --surface-soft: #0b1128;
            --panel: #f8fafc;
            --panel-soft: #f3f5ff;
            --text-primary: #0f172a;
            --text-muted: #94a3b8;
            --border: #1f2b57;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Poppins', system-ui, -apple-system, sans-serif;
            background-color: var(--surface);
            color: var(--panel);
            line-height: 1.6;
            min-height: 100vh;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 2.5rem 1.5rem 3rem;
        }

        .hero {
            background: var(--surface-soft);
            border-radius: 1.5rem;
            padding: 3rem;
            border: 1px solid rgba(255, 255, 255, 0.08);
            /* box-shadow: 0 30px 60px rgba(5, 6, 21, 0.65); */
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.75rem;
        }

        .logo-icon {
            width: 64px;
            height: 64px;
            border-radius: 18px;
            background: var(--accent);
            color: var(--surface);
            font-weight: 700;
            font-size: 1.35rem;
            /* letter-spacing: 0.25em; */
            display: flex;
            align-items: center;
            justify-content: center;
        }

        h1 {
            font-size: 2.8rem;
            color: #f8fafc;
            margin-bottom: 0.35rem;
        }

        .subtitle {
            font-size: 1.125rem;
            color: var(--text-muted);
        }

        .hero-copy {
            font-size: 1.125rem;
            margin-bottom: 2rem;
            color: #e2e6ff;
            line-height: 1.75;
        }

        .badges {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: 1.75rem;
        }

        .badge {
            padding: 0.45rem 1rem;
            border-radius: 999px;
            font-size: 0.85rem;
            background: var(--primary);
            border: 1px solid var(--primary);
            color: var(--panel);
            /* box-shadow: 0 12px 30px rgba(5, 6, 21, 0.45); */
        }

        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .feature {
            background: #0a1121;
            border-radius: 1rem;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: transform 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .feature:hover {
            transform: translateY(-4px);
            border-color: rgba(255, 255, 255, 0.2);
            /* box-shadow: 0 20px 40px rgba(5, 6, 21, 0.55); */
        }

        .feature-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 0.75rem;
            letter-spacing: 0.15em;
            font-weight: 700;
            margin-bottom: 0.65rem;
        }

        .feature h3 {
            color: #f8fafc;
            margin-bottom: 0.45rem;
            font-size: 1.15rem;
        }

        .feature p {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin: 0;
        }

        .section {
            background: var(--panel);
            border-radius: 1.3rem;
            padding: 2.25rem;
            margin-bottom: 1.75rem;
            border: 1px solid rgba(15, 23, 42, 0.1);
            /* box-shadow: 0 20px 40px rgba(15, 23, 42, 0.18); */
        }

        .section h2 {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--text-primary);
            border-bottom: 4px solid var(--primary);
            padding-bottom: 0.55rem;
            width: fit-content;
        }

        .section h3 {
            font-size: 1.25rem;
            margin: 1.4rem 0 0.75rem;
            color: #1f2937;
        }

        .section p {
            color: #475569;
            line-height: 1.7;
        }

        .section-description {
            margin-bottom: 1rem;
        }

        .requirements {
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
        }

        .requirement {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            color: #1f2937;
        }

        .requirement::before {
            content: '•';
            color: var(--accent);
            font-size: 1.1rem;
            line-height: 1;
        }

        .code-block {
            background: #0d1227;
            color: #f8fafc;
            padding: 1.25rem;
            border-radius: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.08);
            font-family: 'JetBrains Mono', 'SFMono-Regular', Consolas, monospace;
            font-size: 0.95rem;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        .installation-steps {
            counter-reset: step;
        }

        .step {
            margin-bottom: 2rem;
            padding-left: 3.2rem;
            position: relative;
        }

        .step::before {
            counter-increment: step;
            content: counter(step);
            position: absolute;
            left: 0;
            top: 0;
            width: 2rem;
            height: 2rem;
            border-radius: 0.45rem;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            /* box-shadow: 0 12px 20px rgba(15, 23, 42, 0.5); */
        }

        .step h3 {
            margin-bottom: 0.6rem;
            color: #111827;
        }

        .note {
            color: #475569;
            margin-top: 0.5rem;
            line-height: 1.6;
        }

        .links {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1.5rem;
        }

        .cta {
            background: var(--primary);
            color: #fff;
            padding: 0.95rem 1.8rem;
            border-radius: 0.7rem;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .cta:hover {
            transform: translateY(-2px);
            /* box-shadow: 0 15px 35px rgba(31, 67, 170, 0.35); */
        }

        .link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s ease;
        }

        .link:hover {
            color: var(--accent);
        }

        .demo-users {
            background: rgba(205, 179, 52, 0.12);
            border: 1px solid rgba(205, 179, 52, 0.8);
            border-left-width: 4px;
            border-radius: 0.9rem;
            padding: 1rem 1.25rem;
            margin-top: 1rem;
        }

        .demo-users h4 {
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 1rem;
            font-weight: 700;
        }

        .demo-users ul {
            list-style: none;
            padding-left: 0;
            margin: 0;
        }

        .demo-users li {
            color: #1f2937;
            padding: 0.2rem 0;
        }

        .demo-users code {
            background: #fde68a;
            padding: 0.125rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
        }

        .demo-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .demo-card {
            background: #fefefe;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            padding: 1.5rem;
            /* box-shadow: 0 12px 24px rgba(15, 23, 42, 0.1); */
            display: flex;
            flex-direction: column;
            gap: 1rem;
            min-height: 100%;
        }

        .demo-card h3 {
            margin-bottom: 0.25rem;
            color: #111827;
            font-size: 1.25rem;
        }

        .demo-card code {
            background: #e0e7ff;
            padding: 0.15rem 0.4rem;
            border-radius: 0.25rem;
            font-size: 0.85rem;
        }

        .demo-note {
            font-size: 0.9rem;
            color: #4b5563;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Hero Section -->
        <div class="hero">
            <div class="logo">
                {{-- <div class="logo-icon">AK</div> --}}
                <div>
                    <h1>BSPDX Keystone</h1>
                    <p class="subtitle">Complete Authentication Package for Laravel 12</p>
                </div>
            </div>

            <div class="badges">
                <span class="badge">PHP 8.2+</span>
                <span class="badge">Laravel 12.0+</span>
                <span class="badge">MIT License</span>
                <span class="badge">Production Ready</span>
            </div>

                <p class="hero-copy">
                    A comprehensive, production-ready authentication package combining Laravel Fortify, Sanctum, Spatie
                    Laravel Permission, and Spatie Laravel Passkeys to deliver a full-featured auth system.
                </p>

            <div class="features">
                <div class="feature">
                    <div class="feature-icon">AUTH</div>
                    <h3>Standard Authentication</h3>
                    <p>Powered by Laravel Fortify with email verification and password resets</p>
                </div>
                <div class="feature">
                    <div class="feature-icon">RBAC</div>
                    <h3>RBAC System</h3>
                    <p>Complete Role-Based Access Control using Spatie Laravel Permission</p>
                </div>
                <div class="feature">
                    <div class="feature-icon">TOTP</div>
                    <h3>TOTP 2FA</h3>
                    <p>Two-Factor Authentication with Google Authenticator, Authy, etc.</p>
                </div>
                <div class="feature">
                    <div class="feature-icon">PASS</div>
                    <h3>Passkey Authentication</h3>
                    <p>Modern WebAuthn/FIDO2 login for passwordless authentication</p>
                </div>
                <div class="feature">
                    <div class="feature-icon">2FA</div>
                    <h3>Passkey as 2FA</h3>
                    <p>Use passkeys as a second factor for enhanced security</p>
                </div>
                <div class="feature">
                    <div class="feature-icon">API</div>
                    <h3>API Support</h3>
                    <p>Full Sanctum integration for API authentication</p>
                </div>
            </div>

            <div class="links">
                <a href="https://github.com/TheBootstrapParadox/Keystone" class="cta" target="_blank">View on GitHub</a>
                <a href="https://packagist.org/packages/bspdx/keystone" class="link" target="_blank">Packagist →</a>
                <a href="https://github.com/TheBootstrapParadox/Keystone/wiki" class="link" target="_blank">Documentation
                  →</a>
                <a href="https://github.com/TheBootstrapParadox/Keystone/issues" class="link" target="_blank">Issues
                  →</a>
                </div>
                </div>

                <!-- Requirements -->
                <div class="section">
                  <h2>Requirements</h2>
                  <div class="requirements">
                    <div class="requirement">PHP 8.2 or higher</div>
                    <div class="requirement">Laravel 12.0 or higher</div>
                    <div class="requirement">MySQL 5.7+ / PostgreSQL 9.6+ / SQLite 3.8.8+</div>
                    <div class="requirement">HTTPS (required for Passkeys)</div>
                  </div>
                </div>

                <!-- Installation -->
                <div class="section">
                  <h2>Installation</h2>
                  <div class="installation-steps">
                    <div class="step">
                      <h3>Install via Composer</h3>
                      <div class="code-block">composer require bspdx/keystone</div>
                    </div>

                    <div class="step">
                      <h3>Publish Configuration & Assets</h3>
                      <div class="code-block"># Publish configuration
                        php artisan vendor:publish --tag=keystone-config

                        # Publish migrations
                        php artisan vendor:publish --tag=keystone-migrations

                        # Publish example routes
                        php artisan vendor:publish --tag=keystone-routes

                        # Publish database seeders
                        php artisan vendor:publish --tag=keystone-seeders</div>
                    </div>

                    <div class="step">
                      <h3>Run Migrations</h3>
                      <div class="code-block">php artisan migrate</div>
                      <p class="note">
                        Creates tables for two-factor authentication, roles, permissions, passkeys, and personal access
                        tokens.
                      </p>
                    </div>

                    <div class="step">
                      <h3>Seed Demo Data (Optional)</h3>
                      <div class="code-block">php artisan db:seed --class=KeystoneSeeder</div>

                      <div class="demo-users">
                        <h4>Demo Users Seeded</h4>
                        <ul>
                          <li><code>superadmin@example.com</code> - Super Admin</li>
                          <li><code>admin@example.com</code> - Admin</li>
                          <li><code>editor@example.com</code> - Editor</li>
                          <li><code>user@example.com</code> - Regular User</li>
                        </ul>
                        <p class="demo-note"><strong>All passwords:</strong> <code>password</code></p>
                      </div>
                    </div>

                    <div class="step">
                      <h3>Update User Model</h3>
                      <div class="code-block">use BSPDX\Keystone\Traits\HasKeystone;

                        class User extends Authenticatable
                        {
                        use HasKeystone;

                        // ... rest of your model
                        }</div>
                    </div>
                  </div>
                </div>

                <!-- Configuration -->
                <div class="section">
                  <h2>Configuration</h2>
                  <p class="section-description">
                    The package configuration is located at <code class="inline-code">config/keystone.php</code>.
                  </p>

                  <h3>Enable/Disable Features</h3>
                  <div class="code-block">'features' => [
                    'registration' => true,
                    'email_verification' => true,
                    'two_factor' => true,
                    'passkeys' => true,
                    'passkey_2fa' => true,
                    'api_tokens' => true,
                    ]</div>

                  <h3>RBAC Settings</h3>
                  <div class="code-block">'rbac' => [
                    'default_role' => 'user',
                    'super_admin_role' => 'super-admin',
                    ]</div>

                  <h3>Passkey Settings</h3>
                  <div class="code-block">'passkey' => [
                    'rp_name' => env('APP_NAME', 'Laravel'),
                    'rp_id' => env('PASSKEY_RP_ID', 'localhost'),
                    'allow_multiple' => true,
                    ]</div>
                </div>

                <!-- Middleware -->
                <div class="section">
                  <h2>Middleware</h2>
                  <p class="section-description">Keystone provides three powerful middleware aliases:</p>

                  <h3>Role Middleware</h3>
                  <div class="code-block">// Single role
                    Route::middleware(['auth', 'role:admin'])->group(function () {
                    // Only admins
                    });

                    // Multiple roles (OR logic)
                    Route::middleware(['auth', 'role:admin,editor'])->group(function () {
                    // Admins OR editors
                    });</div>

                  <h3>Permission Middleware</h3>
                  <div class="code-block">Route::middleware(['auth', 'permission:edit-posts'])->group(function () {
                    // Only users with 'edit-posts' permission
                    });</div>

                  <h3>2FA Enforcement Middleware</h3>
                  <div class="code-block">Route::middleware(['auth', '2fa'])->group(function () {
                    // Ensures users have 2FA enabled if required for their role
                    });</div>
                </div>

                <!-- Blade Components -->
                <div class="section">
                  <h2>Blade Components</h2>
                  <p class="section-description">Drop-in, framework-agnostic Blade components:</p>

                  <h3 style="font-size: 1.25rem; margin: 1.5rem 0 0.75rem; color: #374151;">Login Form</h3>
                  <div class="code-block">&lt;x-keystone-login-form
                    :show-passkey-option="true"
                    :show-remember-me="true"
                    :show-register-link="true"
                    /&gt;</div>

                  <h3 style="font-size: 1.25rem; margin: 1.5rem 0 0.75rem; color: #374151;">Register Form</h3>
                  <div class="code-block">&lt;x-keystone-register-form
                    :show-login-link="true"
                    /&gt;</div>

                  <h3 style="font-size: 1.25rem; margin: 1.5rem 0 0.75rem; color: #374151;">Two-Factor Challenge</h3>
                  <div class="code-block">&lt;x-keystone-two-factor-challenge
                    :show-recovery-code-option="true"
                    /&gt;</div>

                  <h3 style="font-size: 1.25rem; margin: 1.5rem 0 0.75rem; color: #374151;">Passkey Components</h3>
                  <div class="code-block">&lt;x-keystone-passkey-register /&gt;
                    &lt;x-keystone-passkey-login /&gt;</div>
                </div>

                <div class="section">
                  <h2>Live Component Preview</h2>
                  <p style="margin-bottom: 1rem;">Interact with the same Blade components right here—seed the demo data, log
                    in, or
                    keep clicking to explore.</p>
                  @php
                    $demoUser = (object) [
                      'name' => 'Demo User',
                      'email' => 'demo@example.com',
                      'email_verified_at' => now(),
                      'require_password' => true,
                      'allow_passkey_login' => true,
                      'allow_totp_login' => true,
                    ];

                    $demoRoles = ['super-admin', 'editor'];
                    $demoPermissions = ['manage users', 'publish posts', 'view analytics'];

                    $demoPasskeys = collect([
                      (object) [
                        'id' => 1,
                        'name' => 'MacBook Pro',
                        'created_at' => now()->subDays(5),
                        'last_used_at' => now()->subDay(),
                      ],
                      (object) [
                        'id' => 2,
                        'name' => 'YubiKey 5C',
                        'created_at' => now()->subDays(14),
                        'last_used_at' => null,
                      ],
                    ]);

                    $demoHasTwoFactor = true;
                    $demoHasPasskeys = true;
                  @endphp
                  <div class="demo-grid">
                    <div class="demo-card">
                      <h3>Login Form</h3>
                      <p class="demo-note">Use <code>superadmin@example.com</code> or <code>admin@example.com</code> with
                        <code>password</code> after running <code>php artisan db:seed --class=KeystoneSeeder</code>.
                      </p>
                      <x-keystone-login-form :show-passkey-option="true" :show-remember-me="true" :show-register-link="false"
                        :show-forgot-password="true" />
                    </div>
                    <div class="demo-card">
                      <h3>Register Form</h3>
                      <p class="demo-note">Adjust <code>requiredFields</code> in <code>config/keystone.php</code>—this card
                        mirrors
                        those defaults.</p>
                      <x-keystone-register-form :show-login-link="true" />
                    </div>
                    <div class="demo-card">
                      <h3>Two-Factor Challenge</h3>
                      <p class="demo-note">Enter a TOTP code or recovery token after enabling 2FA for any seeded account.
                      </p>
                      <x-keystone-two-factor-challenge :show-recovery-code-option="true" />
                    </div>
                    <div class="demo-card">
                      <h3>Passkey Flows</h3>
                      <p class="demo-note">Passkeys require HTTPS; the buttons invoke the <code>passkeys.*</code> routes.
                      </p>
                      <div style="display: grid; gap: 1rem;">
                        <x-keystone-passkey-register status-id="passkey-register-status-demo" />
                        <x-keystone-passkey-login status-id="passkey-login-status-demo" />
                      </div>
                    </div>
                  </div>

                  <h3 style="margin: 2rem 0 1rem; color: #111827;">Profile Components</h3>
                  <div class="demo-grid">
                    <div class="demo-card">
                      <h3>Account Info</h3>
                      <p class="demo-note">Displays a user’s basic profile fields.</p>
                      @include('keystone::components.profile.account-info', ['user' => $demoUser])
                    </div>
                    <div class="demo-card">
                      <h3>Roles & Permissions</h3>
                      <p class="demo-note">Renders assigned roles and permissions as badges.</p>
                      @include('keystone::components.profile.roles-permissions', ['roles' => $demoRoles, 'permissions' => $demoPermissions])
                    </div>
                    <div class="demo-card">
                      <h3>Login Preferences</h3>
                      <p class="demo-note">Toggle password, passkey, or authenticator-based login.</p>
                      @include('keystone::components.profile.auth-preferences', [
  'user' => $demoUser,
  'hasTwoFactor' => $demoHasTwoFactor,
  'hasPasskeys' => $demoHasPasskeys,
])
                </di  v>
                    <div class="demo-card">
                          <h3>Passkey Management</h3>
                            <p class="demo-note">Shows registered passkeys with delete and add flows.</p>
                                    @include('keystone::components.profile.passkey-management', ['passkeys' => $demoPasskeys])
                        </div>
                    </div>
             </di v>

                          <!-- Quick Start -->
           <div    class="section">
               <h2> Quick Start</h2>
                <p class="section-description">Get up and running in 5 minutes:</p>

                  <div   class="code-block"># 1. Install package
                    composer require bspdx/keystone
                                php artisan vendor:publish --tag=keystone-config
                    php artisan vendor:publish --tag=keystone-migrations
                php artisan migrate
                php artisan db:seed --class=KeystoneSeeder

                # 2. Start server
                ./vendor/bin/sail up

                # 3. Visit https://localhost/login
                # Use: admin@example.com / password</div>

            <p class="panel-note">
                <strong>Pro Tip:</strong> Passkeys require HTTPS! Check out our
                <a href="https://github.com/TheBootstrapParadox/Keystone/blob/main/docs/https-setup.md" class="link"
                    target="_blank">HTTPS Setup Guide</a>
                for local development.
            </p>
        </div>

        <!-- Support -->
        <div class="section">
            <h2>Support & Resources</h2>
            <div class="support-grid">
                <div class="support-card">
                    <h3>Documentation</h3>
                    <a href="https://github.com/TheBootstrapParadox/Keystone/wiki" class="link" target="_blank">Full
                        Documentation →</a>
                </div>
                <div class="support-card">
                    <h3>Issues</h3>
                    <a href="https://github.com/TheBootstrapParadox/Keystone/issues" class="link" target="_blank">Report
                        a Bug →</a>
                </div>
                <div class="support-card">
                    <h3>Discussions</h3>
                    <a href="https://github.com/TheBootstrapParadox/Keystone/discussions" class="link"
                        target="_blank">Join the Community →</a>
                </div>
                <div class="support-card">
                    <h3>Security</h3>
                    <a href="mailto:info@bspdx.com" class="link">info@bspdx.com</a>
                </div>
            </div>
        </div>

        <!-- Credits -->
        <div class="section">
            <h2>Built With</h2>
            <p class="section-description">Keystone stands on the shoulders of giants:</p>
            <div class="built-with-grid">
                <a href="https://github.com/laravel/fortify" class="badge built-link" target="_blank">Laravel
                    Fortify</a>
                <a href="https://github.com/laravel/sanctum" class="badge built-link" target="_blank">Laravel
                    Sanctum</a>
                <a href="https://github.com/spatie/laravel-permission" class="badge built-link" target="_blank">Spatie
                    Laravel Permission</a>
                <a href="https://github.com/spatie/laravel-passkeys" class="badge built-link" target="_blank">Spatie
                    Laravel Passkeys</a>
            </div>
        </div>
    </div>

    <footer>
        <p>BSPDX Keystone - MIT License - Designed for the Laravel community.</p>
        <p class="footer-meta">© {{ date('Y') }} BSPDX. All rights reserved.</p>
    </footer>
</body>

</html>
