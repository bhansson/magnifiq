<?php

use App\Livewire\ManageProductFeeds;
use App\Models\User;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
});

test('feed url whitespace is trimmed when fetching fields', function () {
    Livewire::actingAs($this->user)
        ->test(ManageProductFeeds::class)
        ->set('feedUrl', '  https://example.com/feed.xml  ')
        ->call('fetchFields')
        ->assertSet('feedUrl', 'https://example.com/feed.xml');
});

test('feed url whitespace is trimmed when importing feed', function () {
    Livewire::actingAs($this->user)
        ->test(ManageProductFeeds::class)
        ->set('feedUrl', '  https://example.com/feed.xml  ')
        ->call('importFeed')
        ->assertSet('feedUrl', 'https://example.com/feed.xml');
});
