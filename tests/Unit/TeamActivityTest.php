<?php

namespace Tests\Unit;

use App\Models\ProductAiJob;
use App\Models\ProductFeed;
use App\Models\Team;
use App\Models\TeamActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_can_be_created(): void
    {
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
    }

    public function test_activity_has_team_relationship(): void
    {
        $team = Team::factory()->create();
        $activity = TeamActivity::factory()->create(['team_id' => $team->id]);

        $this->assertInstanceOf(Team::class, $activity->team);
        $this->assertEquals($team->id, $activity->team->id);
    }

    public function test_activity_has_user_relationship(): void
    {
        $user = User::factory()->create();
        $activity = TeamActivity::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $activity->user);
        $this->assertEquals($user->id, $activity->user->id);
    }

    public function test_activity_description_for_job_completed(): void
    {
        $activity = TeamActivity::factory()->jobCompleted()->create([
            'properties' => ['job_type' => 'template', 'product_title' => 'Cool Gadget'],
        ]);

        $this->assertStringContainsString('Template', $activity->description);
        $this->assertStringContainsString('Cool Gadget', $activity->description);
    }

    public function test_activity_description_for_feed_imported(): void
    {
        $user = User::factory()->create();
        $activity = TeamActivity::factory()->feedImported()->create([
            'user_id' => $user->id,
            'properties' => ['feed_name' => 'Test Feed', 'product_count' => 150],
        ]);

        $this->assertStringContainsString($user->name, $activity->description);
        $this->assertStringContainsString('Test Feed', $activity->description);
        $this->assertStringContainsString('150', $activity->description);
    }

    public function test_record_job_completed_creates_activity(): void
    {
        $job = ProductAiJob::factory()->completed()->create();

        $activity = TeamActivity::recordJobCompleted($job);

        $this->assertDatabaseHas('team_activities', [
            'id' => $activity->id,
            'team_id' => $job->team_id,
            'type' => TeamActivity::TYPE_JOB_COMPLETED,
            'subject_type' => ProductAiJob::class,
            'subject_id' => $job->id,
        ]);
    }

    public function test_record_job_failed_creates_activity(): void
    {
        $job = ProductAiJob::factory()->failed()->create([
            'last_error' => 'Test error message',
        ]);

        $activity = TeamActivity::recordJobFailed($job);

        $this->assertDatabaseHas('team_activities', [
            'id' => $activity->id,
            'team_id' => $job->team_id,
            'type' => TeamActivity::TYPE_JOB_FAILED,
            'subject_type' => ProductAiJob::class,
            'subject_id' => $job->id,
        ]);

        $this->assertEquals('Test error message', $activity->properties['error']);
    }

    public function test_record_feed_imported_creates_activity(): void
    {
        $user = User::factory()->create();
        $feed = ProductFeed::factory()->create();

        $activity = TeamActivity::recordFeedImported($feed, $user->id, 150);

        $this->assertDatabaseHas('team_activities', [
            'id' => $activity->id,
            'team_id' => $feed->team_id,
            'user_id' => $user->id,
            'type' => TeamActivity::TYPE_FEED_IMPORTED,
        ]);

        $this->assertEquals(150, $activity->properties['product_count']);
    }

    public function test_activity_icon_attribute(): void
    {
        $jobActivity = TeamActivity::factory()->jobCompleted()->create();
        $feedActivity = TeamActivity::factory()->feedImported()->create();
        $memberActivity = TeamActivity::factory()->memberAdded()->create();

        $this->assertEquals('cpu-chip', $jobActivity->icon);
        $this->assertEquals('document-arrow-down', $feedActivity->icon);
        $this->assertEquals('user-group', $memberActivity->icon);
    }

    public function test_activity_color_attribute(): void
    {
        $completedActivity = TeamActivity::factory()->jobCompleted()->create();
        $failedActivity = TeamActivity::factory()->jobFailed()->create();
        $feedActivity = TeamActivity::factory()->feedImported()->create();

        $this->assertEquals('green', $completedActivity->color);
        $this->assertEquals('red', $failedActivity->color);
        $this->assertEquals('blue', $feedActivity->color);
    }
}
