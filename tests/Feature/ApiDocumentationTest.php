<?php

it('serves the versioned OpenAPI contract from the documented endpoint', function (): void {
    $response = $this->get('/docs/api');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/yaml; charset=UTF-8');

    expect(file_get_contents(base_path('openapi/isp-platform-v1.yaml')))
        ->toContain('openapi: 3.1.0')
        ->toContain('/auth/customer/otp/request');
});
