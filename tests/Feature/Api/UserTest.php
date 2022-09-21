<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */

    use RefreshDatabase;

    // register
    public function test_user_no_auth_can_not_register() {
        $this->withoutExceptionHandling();

        $user = User::factory()->create();
    
        $this->actingAs($user);

        $response = $this->attemptToRegister();

        $response->assertStatus(401);
        $this->assertAuthenticatedAs(User::first());
        $this->assertCount(1, User::all());
    }

    public function test_no_teacher_can_not_register()
    {
        $this->withoutExceptionHandling();

        Sanctum::actingAs(
            $authUser = User::factory()->create([
                   'isAdmin' => false
            ])
        ); 

        $response = $this->attemptToRegister();

        $response->assertStatus(401);
        $this->assertAuthenticatedAs(User::first());
        $this->assertCount(1, User::all());
    }

    public function test_teacher_can_register()
    {
        $this->withoutExceptionHandling();

        Sanctum::actingAs(
            $authUser = User::factory()->create([
                   'isAdmin' => true
            ])
        ); 

        $response = $this->attemptToRegister();

        $response->assertStatus(200);
        $this->assertAuthenticatedAs(User::first());
        $this->assertCount(2, User::all());
    }


    protected function attemptToRegister(array $params = [])
    {
        return $this->post(route('register'), array_merge([
            'name' => 'John',
            'email' => 'john@gmail.com',
            'password' => 'password',
            'password_confirmation' => 'password'
        ], $params));
    }

    // log in 
    public function test_users_can_authenticate()
    {
        $user = User::factory()->create();

        $response = $this->postJson(route('login'),[
            'email' => $user->email,
            'password' => 'password'
        ])
        ->assertOk();

        $this->assertArrayHasKey('access_token', $response->json());
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $response = $this->postJson(route('login'),[
            'email' => $user->email,
            'password' => 'wrong-password'
        ]);

        $response->assertStatus(404);
    }

    // log out test
/*     public function test_user_can_logout()
    {
        $this->withoutExceptionHandling();

        $user = User::factory()->create();
    
        $this->actingAs($user);

        $response = $this->get('/api/logout');
        $response->assertStatus(200);
    }
 */

    // users list
    public function test_user_no_auth_can_not_see_users_list()
    {
        $this->withoutExceptionHandling();

        $user = User::factory()->create();
    
        $this->actingAs($user);

        $response = $this->get('/api/users');
        $response->assertStatus(401);
    }
    
    public function test_if_auth_user_no_teacher_can_not_see_users_list()
    {
        $this->withExceptionHandling();

        User::factory()->create();

        Sanctum::actingAs(
            $user = User::factory()->create([
                   'isAdmin' => false
            ])
        ); 


        $response = $this->get('/api/users');        
        $response->assertStatus(401);
    } 

    public function test_if_auth_user_teacher_can_see_users_list()
    {
        $this->withExceptionHandling();

        User::factory()->create();

        Sanctum::actingAs(
            $user = User::factory()->create([
                   'isAdmin' => true
            ])
        ); 

        $users = User::all();

        $response = $this->get('/api/users');        
        $response->assertStatus(200);
    } 

    // user profile
    public function test_user_no_auth_can_not_see_user_profile()
    {
        $this->withoutExceptionHandling();

        $user = User::factory()->create();

        $currentUser = User::factory()->create();
    
        $this->actingAs($currentUser);

        $response = $this->get(route('userProfile', $user->id));
        $response->assertStatus(401);
    }

    public function test_if_auth_user_no_teacher_can_not_see_user_profile()
    {
        $this->withExceptionHandling();

        User::factory()->create();

        Sanctum::actingAs(
            $user = User::factory()->create([
                   'isAdmin' => false
            ])
        ); 

        $response = $this->get(route('userProfile', $user->id));   
        $response->assertStatus(401);
    }

    public function test_if_auth_user_teacher_can_see_user_profile()
    {
        $this->withExceptionHandling();

        $user = User::factory()->create();

        Sanctum::actingAs(
            $userTeacher = User::factory()->create([
                   'isAdmin' => true
            ])
        ); 

        $response = $this->get(route('userProfile', $user->id));        
        $response->assertStatus(200);
        $this->assertCount(1, User::all()
            ->where('isAdmin', '=', false)
            ->where('superAdmin', '=', false));
    } 

    // delete user tests
    public function test_delete_user_no_auth_user() {
        $this->withoutExceptionHandling();

        $user = User::factory()->create();
    
        $this->actingAs($user);

        $response = $this->delete(route('deleteUser', $user->id));
        $response->assertStatus(401);
    }

    public function test_delete_user__auth_user_no_teacher() {
        $this->withoutExceptionHandling();

        $user = User::factory()->create();

        Sanctum::actingAs(
            $userNoTeacher = User::factory()->create([
                   'isAdmin' => false
            ])
        ); 

        $response = $this->delete(route('deleteUser', $user->id));
        $response->assertStatus(401);
    }

    public function test_delete_user__auth_user_teacher() {
        $this->withoutExceptionHandling();

        $user = User::factory()->create();

        Sanctum::actingAs(
            $userTeacher = User::factory()->create([
                   'isAdmin' => true
            ])
        ); 

        $response = $this->delete(route('deleteUser', $user->id));
        $response->assertStatus(200);
    }


   



    /* public function test_user_profile_can_be_updated_by_auth_user()
    {
        $this->withoutExceptionHandling();

        $user = User::factory()->create();
        $authUser = User::factory()->create();

        $this->actingAs($authUser);
    
        $this->assertCount(2, User::all());

        $response = $this->patch(route('update', $user->id), [
            'name' => 'Update Name',
            'email' => 'user@gmail.com',
            'password' => 'password'
        ]);
    
        $this->assertEquals(User::first()->name,'Update Name');

    } 
 */
}
