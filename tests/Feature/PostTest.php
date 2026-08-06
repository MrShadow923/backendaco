<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_post(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/posts', [
            'title' => 'Test Post',
            'body' => 'This is the body of the test post.',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'user_id', 'title', 'body', 'created_at', 'updated_at'],
            ])
            ->assertJsonPath('data.title', 'Test Post')
            ->assertJsonPath('data.user_id', $user->id);

        $this->assertDatabaseHas('posts', [
            'title' => 'Test Post',
            'user_id' => $user->id,
        ]);
    }

    public function test_create_post_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/posts', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'body']);
    }

    public function test_user_can_list_own_posts(): void
    {
        $user = User::factory()->create();
        Post::factory(3)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/posts');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'title', 'body', 'user_id', 'created_at', 'updated_at']],
                'links',
                'meta',
            ]);
    }

    public function test_user_cannot_see_other_users_posts(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Post::factory(2)->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/posts');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_user_can_view_own_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson("/api/v1/posts/{$post->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $post->id);
    }

    public function test_user_cannot_view_other_users_post(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->getJson("/api/v1/posts/{$post->id}");

        $response->assertStatus(403);
    }

    public function test_user_can_update_own_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->putJson("/api/v1/posts/{$post->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Updated Title');

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_user_cannot_update_other_users_post(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->putJson("/api/v1/posts/{$post->id}", [
            'title' => 'Hacked Title',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_delete_own_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/posts/{$post->id}");

        $response->assertOk()
            ->assertJson(['message' => 'Post deleted.']);

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_user_cannot_delete_other_users_post(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/posts/{$post->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('posts', ['id' => $post->id]);
    }

    public function test_unauthenticated_user_cannot_access_posts(): void
    {
        $this->getJson('/api/v1/posts')->assertStatus(401);
        $this->postJson('/api/v1/posts', [])->assertStatus(401);
        $this->getJson('/api/v1/posts/1')->assertStatus(401);
        $this->putJson('/api/v1/posts/1', [])->assertStatus(401);
        $this->deleteJson('/api/v1/posts/1')->assertStatus(401);
    }

    public function test_posts_are_paginated(): void
    {
        $user = User::factory()->create();
        Post::factory(20)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/posts');

        $response->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2);
    }
}
