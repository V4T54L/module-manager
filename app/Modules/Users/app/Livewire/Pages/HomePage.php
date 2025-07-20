<?php

namespace Modules\Users\Livewire\Pages;

use Livewire\Component;

class HomePage extends Component
{
    public function render()
    {
        return <<<'blade'
            <div>
                <h3>The <code>HomePage</code> livewire component is loaded from the <code>Users</code> module.</h3>
                <flux:button href='/users/about' wire:navigate
                    style="padding: 10px 20px; background-color: #6366f1; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Go to About
                </flux:button>
            </div>
        blade;
    }
}
