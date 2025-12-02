<?php

use App\Models\ProductAiJob;
use App\Models\ProductFeed;
use App\Models\Team;
use App\Models\TeamActivity;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('activity can be created', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();

    $activity = TeamActivity::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'type' => TeamActivity::TYPE_JOB_COMPLETED,
        'properties' => ['job_type' => 'template', 'product_title' => 'Test Product'],
    ]);

    $this->assertDatabaseHas('team_activities', [
        'id' => $activity->id,
        'team_id' => $team->id,
        'type' => TeamActivity::TYPE_JOB_COMPLETED,
    ]);
});

test('activity has team relationship', function () {
    $team = Team::factory()->create();
    $activity = TeamActivity::factory()->create(['team_id' => $team->id]);

    expect($activity->team)->toBeInstanceOf(Team::class);
    expect($activity->team->id)->toEqual($team->id);
});

test('activity has user relationship', function () {
    $user = User::factory()->create();
    $activity = TeamActivity::factory()->create(['user_id' => $user->id]);

    expect($activity->user)->toBeInstanceOf(User::class);
    expect($activity->user->id)->toEqual($user->id);
});

test('activity description for job completed', function () {
    $user = User::factory()->create();
    $activity = TeamActivity::factory()->jobCompleted()->create([
        'user_id' => $user->id,
        'properties' => ['job_type' => 'template', 'template_name' => 'FAQ', 'product_title' => 'Cool Gadget'],
    ]);

    $this->assertStringContainsString($user->name, $activity->description);
    $this->assertStringContainsString('generated FAQ', $activity->description);
    $this->assertStringContainsString('Cool Gadget', $activity->description);
});

test('activity description for job completed without user', function () {
    $activity = TeamActivity::factory()->jobCompleted()->create([
        'user_id' => null,
        'properties' => ['job_type' => 'template', 'template_name' => 'Summary', 'product_title' => 'Test Product'],
    ]);

    expect($activity->description)->toEqual('Summary generated for "Test Product"');
});

test('activity description for feed imported', function () {
    $user = User::factory()->create();
    $activity = TeamActivity::factory()->feedImported()->create([
        'user_id' => $user->id,
        'properties' => ['feed_name' => 'Test Feed', 'product_count' => 150],
    ]);

    $this->assertStringContainsString($user->name, $activity->description);
    $this->assertStringContainsString('Test Feed', $activity->description);
    $this->assertStringContainsString('150', $activity->description);
});

test('record job completed creates activity', function () {
    $user = User::factory()->create();
    $job = ProductAiJob::factory()->completed()->create([
        'user_id' => $user->id,
    ]);

    $activity = TeamActivity::recordJobCompleted($job);

    $this->assertDatabaseHas('team_activities', [
        'id' => $activity->id,
        'team_id' => $job->team_id,
        'user_id' => $user->id,
        'type' => TeamActivity::TYPE_JOB_COMPLETED,
        'subject_type' => ProductAiJob::class,
        'subject_id' => $job->id,
    ]);
});

test('record job failed creates activity', function () {
    $user = User::factory()->create();
    $job = ProductAiJob::factory()->failed()->create([
        'user_id' => $user->id,
        'last_error' => 'Test error message',
    ]);

    $activity = TeamActivity::recordJobFailed($job);

    $this->assertDatabaseHas('team_activities', [
        'id' => $activity->id,
        'team_id' => $job->team_id,
        'user_id' => $user->id,
        'type' => TeamActivity::TYPE_JOB_FAILED,
        'subject_type' => ProductAiJob::class,
        'subject_id' => $job->id,
    ]);

    expect($activity->properties['error'])->toEqual('Test error message');
});

test('record feed imported creates activity', function () {
    $user = User::factory()->create();
    $feed = ProductFeed::factory()->create();

    $activity = TeamActivity::recordFeedImported($feed, $user->id, 150);

    $this->assertDatabaseHas('team_activities', [
        'id' => $activity->id,
        'team_id' => $feed->team_id,
        'user_id' => $user->id,
        'type' => TeamActivity::TYPE_FEED_IMPORTED,
    ]);

    expect($activity->properties['product_count'])->toEqual(150);
});

test('activity icon attribute', function () {
    $jobActivity = TeamActivity::factory()->jobCompleted()->create();
    $feedActivity = TeamActivity::factory()->feedImported()->create();
    $memberActivity = TeamActivity::factory()->memberAdded()->create();

    expect($jobActivity->icon)->toEqual('cpu-chip');
    expect($feedActivity->icon)->toEqual('document-arrow-down');
    expect($memberActivity->icon)->toEqual('user-group');
});

test('activity color attribute', function () {
    $completedActivity = TeamActivity::factory()->jobCompleted()->create();
    $failedActivity = TeamActivity::factory()->jobFailed()->create();
    $feedActivity = TeamActivity::factory()->feedImported()->create();

    expect($completedActivity->color)->toEqual('green');
    expect($failedActivity->color)->toEqual('red');
    expect($feedActivity->color)->toEqual('blue');
});