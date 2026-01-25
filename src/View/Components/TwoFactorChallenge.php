<?php

namespace BSPDX\Keystone\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class TwoFactorChallenge extends Component
{
    public function __construct(
        public ?string $action = null,
        public bool $showRecoveryCodeOption = true,
    ) {
        $this->action = $action ?? route('two-factor.login');
    }

    public function render(): View
    {
        return view('keystone::components.two-factor-challenge');
    }
}
