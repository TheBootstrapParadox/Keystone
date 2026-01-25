<?php

namespace BSPDX\Keystone\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class LoginForm extends Component
{
    public function __construct(
        public ?string $action = null,
        public bool $showPasskeyOption = true,
        public bool $showRememberMe = true,
        public bool $showRegisterLink = true,
        public bool $showForgotPassword = true,
    ) {
        $this->action = $action ?? route('login');
    }

    public function render(): View
    {
        return view('keystone::components.login-form');
    }
}
