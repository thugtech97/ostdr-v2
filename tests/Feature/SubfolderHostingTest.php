<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SubfolderHostingTest extends TestCase
{
    public function test_the_logo_urls_include_the_subfolder_the_app_is_hosted_in(): void
    {
        $root = 'http://example.test/pmc-ostdr-v2/public';
        URL::forceRootUrl($root);

        // An absolute URL, so only the generated links use the forced root and not this request.
        $this->get('http://localhost/')
            ->assertOk()
            ->assertSee('href="'.$root.'/images/logo.svg"', false)
            ->assertInertia(fn ($page) => $page->where('logoUrl', $root.'/images/logo.svg'));
    }
}
