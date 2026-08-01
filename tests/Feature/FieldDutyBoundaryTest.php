<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldDutyBoundaryTest extends TestCase
{
    use RefreshDatabase;
    public function test_phase_k_exposes_no_location_tracking_or_google_calendar_routes(): void
    {
        $routes = collect(app('router')->getRoutes())->filter(fn ($route) => str_starts_with($route->uri(), 'attendance'))->map(fn ($route) => $route->uri().' '.implode(' ', $route->methods()))->implode("\n");
        $this->assertStringNotContainsString('location', strtolower($routes)); $this->assertStringNotContainsString('google', strtolower($routes)); $this->assertStringNotContainsString('meet', strtolower($routes));
    }
}
