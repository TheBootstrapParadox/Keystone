<?php

namespace BSPDX\Keystone\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class PasskeyRegister extends Component
{
    public function __construct(
        public ?string $registerOptionsUrl = null,
        public ?string $registerUrl = null,
        public string $statusId = 'passkey-status',
    ) {
        $this->registerOptionsUrl = $registerOptionsUrl ?? route('passkeys.register.options');
        $this->registerUrl = $registerUrl ?? route('passkeys.register');
    }

    public function render(): View
    {
        return view('keystone::components.passkey-register');
    }
}
