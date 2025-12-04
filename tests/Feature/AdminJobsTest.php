<?php

use App\Livewire\AdminJobs;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('superadmin can access jobs list page', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);

    $response = $this->actingAs($superadmin)->get('/admin/jobs');

    $response->assertStatus(200);
    $response->assertSee('Queue Jobs');
});

test('regular user cannot access admin jobs page', function () {
    $user = User::factory()->withPersonalTeam()->create(['role' => 'user']);

    $response = $this->actingAs($user)->get('/admin/jobs');

    $response->assertStatus(403);
});

test('admin user cannot access admin jobs page', function () {
    $admin = User::factory()->withPersonalTeam()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get('/admin/jobs');

    $response->assertStatus(403);
});

test('admin jobs page shows pending jobs tab by default', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);

    Livewire::actingAs($superadmin)
        ->test(AdminJobs::class)
        ->assertStatus(200)
        ->assertSee('Pending Jobs')
        ->assertSee('Failed Jobs');
});

test('admin jobs page can switch to failed jobs tab', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);

    Livewire::actingAs($superadmin)
        ->test(AdminJobs::class)
        ->set('tab', 'failed')
        ->assertStatus(200)
        ->assertSee('Failed Jobs');
});

test('pending jobs are displayed correctly', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);

    // Insert a test job
    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => json_encode([
            'displayName' => 'App\\Jobs\\TestJob',
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'maxTries' => 3,
            'timeout' => 60,
        ]),
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->timestamp,
        'created_at' => now()->timestamp,
    ]);

    Livewire::actingAs($superadmin)
        ->test(AdminJobs::class)
        ->assertStatus(200)
        ->assertSee('TestJob')
        ->assertSee('default');
});

test('failed jobs are displayed correctly', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);

    // Insert a test failed job
    DB::table('failed_jobs')->insert([
        'uuid' => 'test-uuid-123',
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode([
            'displayName' => 'App\\Jobs\\FailedTestJob',
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
        ]),
        'exception' => 'Test exception message',
        'failed_at' => now(),
    ]);

    Livewire::actingAs($superadmin)
        ->test(AdminJobs::class)
        ->set('tab', 'failed')
        ->assertStatus(200)
        ->assertSee('FailedTestJob')
        ->assertSee('Test exception message');
});

test('admin jobs can filter by queue', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);

    // Insert jobs in different queues
    DB::table('jobs')->insert([
        'queue' => 'ai',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\AiJob']),
        'attempts' => 0,
        'available_at' => now()->timestamp,
        'created_at' => now()->timestamp,
    ]);

    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\DefaultJob']),
        'attempts' => 0,
        'available_at' => now()->timestamp,
        'created_at' => now()->timestamp,
    ]);

    Livewire::actingAs($superadmin)
        ->test(AdminJobs::class)
        ->set('queueFilter', 'ai')
        ->assertSee('AiJob')
        ->assertDontSee('DefaultJob');
});

test('admin jobs can search in payload', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);

    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\SearchableJob']),
        'attempts' => 0,
        'available_at' => now()->timestamp,
        'created_at' => now()->timestamp,
    ]);

    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\HiddenJob']),
        'attempts' => 0,
        'available_at' => now()->timestamp,
        'created_at' => now()->timestamp,
    ]);

    Livewire::actingAs($superadmin)
        ->test(AdminJobs::class)
        ->set('search', 'Searchable')
        ->assertSee('SearchableJob')
        ->assertDontSee('HiddenJob');
});

test('admin can retry failed job', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);

    DB::table('failed_jobs')->insert([
        'uuid' => 'retry-test-uuid',
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\RetryableJob']),
        'exception' => 'Test exception',
        'failed_at' => now(),
    ]);

    Livewire::actingAs($superadmin)
        ->test(AdminJobs::class)
        ->set('tab', 'failed')
        ->call('retryJob', 'retry-test-uuid')
        ->assertHasNoErrors();
});

test('admin can delete failed job', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);

    $jobId = DB::table('failed_jobs')->insertGetId([
        'uuid' => 'delete-test-uuid',
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\DeletableJob']),
        'exception' => 'Test exception',
        'failed_at' => now(),
    ]);

    Livewire::actingAs($superadmin)
        ->test(AdminJobs::class)
        ->set('tab', 'failed')
        ->call('deleteFailedJob', $jobId)
        ->assertHasNoErrors();

    expect(DB::table('failed_jobs')->where('id', $jobId)->exists())->toBeFalse();
});

test('admin jobs page shows statistics', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);

    // Insert some jobs
    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\TestJob']),
        'attempts' => 0,
        'available_at' => now()->timestamp,
        'created_at' => now()->timestamp,
    ]);

    DB::table('failed_jobs')->insert([
        'uuid' => 'stats-test-uuid',
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\FailedJob']),
        'exception' => 'Test exception',
        'failed_at' => now(),
    ]);

    Livewire::actingAs($superadmin)
        ->test(AdminJobs::class)
        ->assertSee('Pending Jobs')
        ->assertSee('Failed Jobs');
});

test('admin jobs page has back link to dashboard', function () {
    $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);

    $response = $this->actingAs($superadmin)->get('/admin/jobs');

    $response->assertStatus(200);
    $response->assertSee('Back to Dashboard');
});

test('parseJobClass extracts class name correctly', function () {
    $payload = json_encode([
        'displayName' => 'App\\Jobs\\GeneratePhotoStudioImage',
        'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
    ]);

    $className = AdminJobs::parseJobClass($payload);

    expect($className)->toBe('GeneratePhotoStudioImage');
});

test('parseJobClass handles missing displayName', function () {
    $payload = json_encode([
        'job' => 'App\\Jobs\\SomeJob',
    ]);

    $className = AdminJobs::parseJobClass($payload);

    expect($className)->toBe('SomeJob');
});

test('parseJobClass handles invalid JSON', function () {
    $payload = 'invalid json';

    $className = AdminJobs::parseJobClass($payload);

    expect($className)->toBe('Unknown');
});

// Authorization tests - verify Livewire actions are protected
test('regular user cannot call retryJob action directly via Livewire', function () {
    $user = User::factory()->withPersonalTeam()->create(['role' => 'user']);

    DB::table('failed_jobs')->insert([
        'uuid' => 'auth-test-uuid',
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\TestJob']),
        'exception' => 'Test exception',
        'failed_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test(AdminJobs::class)
        ->assertStatus(403);
});

test('admin user cannot call retryJob action directly via Livewire', function () {
    $admin = User::factory()->withPersonalTeam()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test(AdminJobs::class)
        ->assertStatus(403);
});

test('regular user cannot call deleteFailedJob action via Livewire', function () {
    $user = User::factory()->withPersonalTeam()->create(['role' => 'user']);

    Livewire::actingAs($user)
        ->test(AdminJobs::class)
        ->assertStatus(403);
});

test('regular user cannot call flushFailedJobs action via Livewire', function () {
    $user = User::factory()->withPersonalTeam()->create(['role' => 'user']);

    Livewire::actingAs($user)
        ->test(AdminJobs::class)
        ->assertStatus(403);
});

test('regular user cannot call retryAllFailedJobs action via Livewire', function () {
    $user = User::factory()->withPersonalTeam()->create(['role' => 'user']);

    Livewire::actingAs($user)
        ->test(AdminJobs::class)
        ->assertStatus(403);
});

test('unauthenticated user cannot access AdminJobs component', function () {
    Livewire::test(AdminJobs::class)
        ->assertStatus(403);
});
