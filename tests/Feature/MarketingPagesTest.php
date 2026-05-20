<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MarketingPagesTest extends TestCase
{
    public function test_services_page_renders(): void
    {
        $this
            ->get(route('marketing.services'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Services')
            );
    }

    public function test_about_page_renders(): void
    {
        $this
            ->get(route('marketing.about'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('About')
            );
    }

    public function test_eu_projects_page_renders(): void
    {
        $this
            ->get(route('marketing.eu-projects'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('EuProjects')
            );
    }
}
