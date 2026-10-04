<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class RecordActionComponentTest extends TestCase
{
    public function test_semantic_variants_share_geometry_and_manage_matches_edit(): void
    {
        $classes = [];
        foreach (['view', 'edit', 'manage', 'add', 'activate', 'deactivate', 'relocate', 'back', 'security', 'document'] as $variant) {
            $html = Blade::render('<x-record-action href="/record" :variant="$variant">Action</x-record-action>', ['variant' => $variant]);
            $document = new \DOMDocument;
            @$document->loadHTML($html);
            $classes[$variant] = $document->getElementsByTagName('a')->item(0)->getAttribute('class');
            foreach (['rounded-md', 'min-h-10', 'px-4', 'py-2', 'text-sm', 'font-medium', 'focus:ring-2'] as $class) {
                $this->assertStringContainsString($class, $classes[$variant]);
            }
            $this->assertStringNotContainsString('rounded-full', $html);
        }
        $this->assertSame($classes['edit'], $classes['manage']);
        $this->assertNotSame($classes['edit'], $classes['add']);
        $this->assertNotSame($classes['activate'], $classes['deactivate']);
        $this->assertNotSame($classes['add'], $classes['relocate']);
    }

    public function test_button_supports_native_form_submission_and_links_preserve_attributes(): void
    {
        $button = Blade::render('<x-record-action type="submit" variant="deactivate">Deactivate</x-record-action>');
        $this->assertStringContainsString('<button', $button);
        $this->assertStringContainsString('type="submit"', $button);
        $this->assertStringNotContainsString('onclick', $button);
        $link = Blade::render('<x-record-action href="/print" variant="document" target="_blank" rel="noopener">Print</x-record-action>');
        $this->assertStringContainsString('href="/print"', $link);
        $this->assertStringContainsString('target="_blank"', $link);
        $this->assertStringContainsString('rel="noopener"', $link);
    }
}
