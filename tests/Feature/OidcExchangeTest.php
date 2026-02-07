<?php

use Illuminate\Support\Str;

describe('exchange', function () {
    it('validates code is required', function () {
        $this->postJson('/api/auth/exchange', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    });

    it('validates code must be uuid format', function () {
        $this->postJson('/api/auth/exchange', ['code' => 'not-a-uuid'])
            ->assertUnprocessable();
    });

    it('rejects non-existent exchange code', function () {
        $this->postJson('/api/auth/exchange', [
            'code' => Str::uuid()->toString(),
        ])->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid or expired exchange code',
            ]);
    });

    it('rejects expired exchange code', function () {
        $code = Str::uuid()->toString();

        $this->postJson('/api/auth/exchange', ['code' => $code])
            ->assertStatus(401);
    });
});
