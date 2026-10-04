<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ModalComponentsTest extends TestCase
{
    public function test_generic_modal_uses_shared_interaction_and_existing_close_events(): void
    {
        $html = Blade::render('<x-modal name="confirm-user-deletion" focusable><h2>Delete account</h2><button>Cancel</button></x-modal>');

        $this->assertStringContainsString('x-modal-layer="show"', $html);
        $this->assertStringContainsString('x-on:close.stop="show = false"', $html);
        $this->assertStringContainsString('x-on:keydown.escape.stop="show = false"', $html);
        $this->assertStringContainsString('x-on:click.self="show = false"', $html);
        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('data-modal-panel', $html);
        $this->assertStringNotContainsString('overflow-y-hidden', $html);
    }

    public function test_confirmation_keeps_submission_guard_and_escape_policy(): void
    {
        $html = Blade::render('<x-action-confirmation-modal />');

        $this->assertStringContainsString('x-modal-layer="open"', $html);
        $this->assertStringContainsString('@click.self="if (!isSubmitting) { cancel() }"', $html);
        $this->assertStringContainsString('@keydown.escape.window="if (open) { $event.preventDefault() }"', $html);
        $this->assertStringContainsString('@click="confirm()"', $html);
        $this->assertStringContainsString('aria-labelledby="action-confirmation-title"', $html);
    }
}
