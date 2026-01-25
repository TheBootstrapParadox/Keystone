<?php

namespace BSPDX\Keystone\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class RegisterForm extends Component
{
    public function __construct(
        public ?string $action = null,
        public bool $showLoginLink = true,
        public array $requiredFields = ['name', 'email', 'password', 'password_confirmation'],
    ) {
        $this->action = $action ?? route('register');
    }

    public function render(): View
    {
        return view('keystone::components.register-form');
    }
}
