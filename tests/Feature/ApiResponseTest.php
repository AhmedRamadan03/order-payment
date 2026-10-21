<?php

it('returns the same response envelope for successful API requests', function () {
    $this->postJson('/api/auth/register', [
        'name' => 'Response Test',
        'email' => 'response@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('type', 'success')
        ->assertJsonPath('message', 'User registered successfully.')
        ->assertJsonPath('errors', [])
        ->assertJsonPath('code', null)
        ->assertJsonStructure(['success', 'type', 'message', 'data', 'errors', 'code']);
});

it('returns the same response envelope for validation errors', function () {
    $this->postJson('/api/auth/register', [])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('type', 'error')
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['success', 'type', 'message', 'data', 'errors', 'code'])
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

it('returns the same response envelope for unauthenticated requests', function () {
    $this->getJson('/api/orders')
        ->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('type', 'error')
        ->assertJsonPath('code', 'UNAUTHENTICATED')
        ->assertJsonStructure(['success', 'type', 'message', 'data', 'errors', 'code']);
});

it('returns the same response envelope for invalid credentials', function () {
    $this->postJson('/api/auth/login', [
        'email' => 'missing@example.com',
        'password' => 'wrong-password',
    ])->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('type', 'error')
        ->assertJsonPath('code', 'INVALID_CREDENTIALS')
        ->assertJsonStructure(['success', 'type', 'message', 'data', 'errors', 'code']);
});
