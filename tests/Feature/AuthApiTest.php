<?php

use App\Models\User;

it('registers a user and returns a bearer token', function () {
    $this->postJson('/api/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.token_type', 'bearer');
    $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
});

it('validates registration inputs', function () {
    $this->postJson('/api/auth/register', [])->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

it('logs in and rejects invalid credentials', function () {
    $user = User::factory()->create(['password' => bcrypt('password123')]);
    $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password123'])
        ->assertOk()->assertJsonPath('success', true);
    $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertUnauthorized();
});

it('protects order endpoints', function () {
    $this->getJson('/api/orders')->assertUnauthorized();
});
