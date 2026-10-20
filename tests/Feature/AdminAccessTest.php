<?php

use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;

it('redirects guests away from the admin panel', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('forbids members from the admin panel', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertForbidden();
});

it('lets admins into the admin panel', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertOk();
});

it('restricts Horizon to admins', function () {
    $this->get('/horizon')->assertForbidden();
    $this->actingAs(User::factory()->create())->get('/horizon')->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get('/horizon')->assertOk();
});

it('does not allow role to be mass assigned', function () {
    expect(fn () => new User(['name' => 'x', 'email' => 'x@example.com', 'password' => 'secret', 'role' => 'admin']))
        ->toThrow(MassAssignmentException::class);

    expect((new User)->isAdmin())->toBeFalse();
});
