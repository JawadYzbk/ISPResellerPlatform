<?php

it('renders the Stoplight API documentation portal', function (): void {
    $response = $this->get('/docs/api');

    $response->assertOk()
        ->assertSee('elements-api', false)
        ->assertSee('apiDescriptionUrl', false)
        ->assertSee('/docs/api/openapi.yaml', false);
});

it('serves the versioned OpenAPI contract from its download endpoint', function (): void {
    $response = $this->get('/docs/api/openapi.yaml');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/yaml; charset=UTF-8');

    expect(file_get_contents(base_path('openapi/isp-platform-v1.yaml')))
        ->toContain('openapi: 3.1.0')
        ->toContain('/auth/customer/otp/request');
});
