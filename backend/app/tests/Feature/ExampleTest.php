<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\Request;
use App\Models\Response;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Tests\TestCase;
use Xrequests\Services\Filesystem\Filesystem;
use Xrequests\Services\Mailman\Mailman;

class Effects {
    public ResponseEffect $response;
    public ServicesEffect $services;
    public ?Model $model;

    public function __construct(TestResponse $response, Model $model = null) {
        $this->response = new ResponseEffect($response);
        $this->services = new ServicesEffect();
        $this->model = $model;
    }
}

class ResponseEffect extends TestResponse {
    public int $status;
    public $content;

    public function __construct(TestResponse $response) {
        parent::__construct($response->baseResponse);

        $this->status = $response->status();
        $this->content = json_decode($response->content());
    }
}

class ServicesEffect {
    public ?MockInterface $mailman;
    public ?MockInterface $filesystem;

    public function __construct() {
        $this->mailman = $this->spyOrNull(Mailman::class);
        $this->filesystem = $this->spyOrNull(Filesystem::class);
    }

    private function spyOrNull($className) {
        return App::make($className) instanceof MockInterface ? App::make($className) : null;
    }
}


class ExampleTest extends TestCase {
    const OK = \Illuminate\Http\Response::HTTP_OK;
    const UNAUTHORIZED = \Illuminate\Http\Response::HTTP_UNAUTHORIZED;
    const CONFLICT = \Illuminate\Http\Response::HTTP_CONFLICT;
    const NOT_FOUND = \Illuminate\Http\Response::HTTP_NOT_FOUND;
    const BAD_REQUEST = \Illuminate\Http\Response::HTTP_BAD_REQUEST;

    function mockService(string $name) {
        $mock = \Mockery::mock($name);
        $this->app->instance($name, $mock);
        return $mock;
    }

    function spyService(string $name) {
        $spy = \Mockery::spy($name);
        $this->app->instance($name, $spy);
        return $spy;
    }

    function assertStatusOK(TestResponse $response) {
        $this->assertEquals(self::OK, $response->getStatusCode());
    }

    function assertResponseStatus(int $status, TestResponse $response) {
        $this->assertEquals($status, $response->getStatusCode());
    }

    function hit_create_user(array $values = []): Effects {
        $this->spyService(Mailman::class);

        $defaults = [
            'username' => uniqid(),
            'email' => uniqid() . '@gmail.com',
            'password' => uniqid(),
        ];

        $body = array_merge($defaults, $values);
        $response = $this->post('api/user/create', $body);

        return new Effects($response, User::find(json_decode($response->content())?->id));
    }

    function hit_login(?User $user, array $values = []): Effects {
        if ($values) {
            $body = [
                'email' => $values['email'],
                'password' => $values['password'],
            ];
        } else {
            $body = [
                'email' => $user->email,
                'password' => $user->password,
            ];
        }

        $response = $this->post('api/user/login', $body);

        return new Effects($response, User::where('email', $body['email'])->first());
    }

    function hit_forgot_password(User $user): Effects {
        $this->spyService(Mailman::class);

        $response = $this->post('api/user/forgot-password', ['email' => $user->email]);

        return new Effects($response, $user->refresh());
    }

    function hit_reset_password($token): Effects {
        $response = $this->post('api/user/reset-password', [
            'password' => 'new-password',
            'token' => $token,
        ]);

        return new Effects($response);
    }

    function hit_create_request($input = [], User $user = null): Effects {
        $input = array_merge($input, [
            'description' => 'foo',
            'validation' => 'bar',
        ]);

        $response = $this->post('api/request/create', array_merge(
            ['token' => $user->token ?? null],
            $input
        ));

        return new Effects($response, Request::find(json_decode($response->content())->id));
    }

    function hit_create_response_for(Request $request, $input = [], User $user = null): Effects {
        $this->spyService(Mailman::class);
        $this->spyService(Filesystem::class);

        $input = array_merge($input, [
            'description' => 'foo',
            'files' => [
                UploadedFile::fake()->image('1.png'),
                UploadedFile::fake()->image('2.png')
            ],
        ]);

        $response = $this->post('api/response/create', array_merge(
            [
                'request_id' => $request->id,
                'token' => $user->token ?? null
            ],
            $input
        ));

        return new Effects($response, Response::find(json_decode($response->content())->id));
    }

    function hit_delete_request(Request $request, User $admin): Effects {
        $this->spyService(Filesystem::class);

        $response = $this->post('api/request/delete', [
            'token' => $admin->refresh()->token,
            'id' => $request->id
        ]);

        return new Effects($response, $request);
    }

    function hit_delete_response(Response $response, User $admin): Effects {
        $this->spyService(Filesystem::class);

        $res = $this->post('api/response/delete', [
            'token' => $admin->refresh()->token,
            'id' => $response->id
        ]);

        return new Effects($res, $response);
    }

    function hit_delete_user(User $user, User $admin): Effects {
        $this->spyService(Filesystem::class);

        $res = $this->post('api/user/delete', [
            'token' => $admin->refresh()->token,
            'id' => $user->id
        ]);

        return new Effects($res, $user);
    }

    function hit_upvote_request(Request $request, User $user = null): Effects {
        $response = $this->post('api/request/upvote', [
            'token' => $user->token ?? null,
            'id' => $request->id
        ]);

        return new Effects($response, $request->refresh());
    }

    function hit_upvote_response(Response $response, User $user = null): Effects {
        $res = $this->post('api/response/upvote', [
            'token' => $user->token ?? null,
            'id' => $response->id
        ]);

        return new Effects($res, $response->refresh());
    }

    function test_user_can_register() {
        $effects = $this->hit_create_user([
            'username' => 'foo',
            'email' => 'foo@bar.baz',
            'password' => 'password',
        ]);

        // Assert response
        $this->assertStatusOK($effects->response);
        $this->assertEquals('foo', $effects->response->content->username);
        $this->assertEquals('foo@bar.baz', $effects->response->content->email);
        $this->assertEquals(false, $effects->response->content->is_backoffice);
        $this->assertEquals(false, $effects->response->content->is_anonymous);

        // Assert email
        $effects->services->mailman->shouldHaveReceived('send')->once()->withArgs([
            'foo@bar.baz',
            'emails.welcome',
            'Welcome',
            ['username' => $effects->model->username]
        ]);
    }

    function test_cant_create_user_with_existing_username_or_email() {
        $this->hit_create_user([
            'username' => 'foo',
            'email' => 'foo@bar.baz'
        ]);

        $effects = $this->hit_create_user([
            'username' => 'not_foo',
            'email' => 'foo@bar.baz'
        ]);
        $this->assertResponseStatus(self::CONFLICT, $effects->response);

        $effects = $this->hit_create_user([
            'username' => 'foo',
            'email' => 'not_foo@bar.baz'
        ]);
        $this->assertResponseStatus(self::CONFLICT, $effects->response);
    }

    function test_can_login() {
        $user = $this->hit_create_user()->model;

        $effects = $this->hit_login($user);
        $this->assertStatusOK($effects->response);
        $this->assertNotEmpty($effects->response->content);
    }

    function test_cant_login_with_wrong_email() {
        $user = $this->hit_create_user()->model;

        $effects = $this->hit_login(null, ['email' => $user->email . 'foo', 'password' => $user->password]);
        $this->assertResponseStatus(self::UNAUTHORIZED, $effects->response);
    }

    function test_cant_login_with_wrong_password() {
        $user = $this->hit_create_user()->model;

        $effects = $this->hit_login(null, ['email' => $user->email, 'password' => $user->password . 'foo']);
        $this->assertResponseStatus(self::UNAUTHORIZED, $effects->response);
    }

    function test_can_reset_password() {
        $effects = $this->hit_create_user();
        $effects->services->mailman->shouldHaveReceived('send')->once();

        $user = $effects->model;
        $effects = $this->hit_forgot_password($user);
        $this->assertStatusOK($effects->response);
        $token = DB::table('password_resets')->first()->token;
        $this->assertNotNull($token);
        $effects->services->mailman->shouldHaveReceived('send')->once()->withArgs([
            $user->email,
            'emails.reset_password',
            'Welcome',
            ['username' => $user->username, 'link' => "foo/$token"]
        ]);

        // User goes to link and resets password.
        $token = DB::table('password_resets')->first()->token;
        $effects = $this->hit_reset_password($token);
        $this->assertStatusOK($effects->response);

        $effects = $this->hit_login(null, ['email' => $user->email, 'password' => 'new-password']);
        $this->assertStatusOK($effects->response);

        // User tries to reset password again.
        $effects = $this->hit_reset_password($token);
        $this->assertResponseStatus(self::CONFLICT, $effects->response);
    }

    function test_anonymous_user_can_create_request() {
        $effects = $this->hit_create_request();

        $this->assertEquals('foo', $effects->response->content->description);
        $this->assertEquals('bar', $effects->response->content->validation);
        $this->assertEquals(true, $effects->response->content->user->is_anonymous);
    }

    function test_user_can_create_request() {
        $user = $this->hit_create_user()->model;
        $user = $this->hit_login($user)->model;

        $effects = $this->hit_create_request([], $user);

        $this->assertEquals('foo', $effects->response->content->description);
        $this->assertEquals('bar', $effects->response->content->validation);
        $this->assertEquals($user->id, $effects->response->content->user->id);
    }

    function test_user_cant_create_response_without_files() {
        $effects = $this->hit_create_request();

        $mock = $this->mockService(Filesystem::class);
        $mock->shouldReceive('write')->never();

        $response = $this->post('api/response/create', [
            'request_id' => $effects->model->id,
            'description' => 'foo',
            'uploads' => []
        ]);

        $this->assertResponseStatus(self::BAD_REQUEST, $response);

        $response = $this->post('api/response/create', [
            'request_id' => $effects->model->id,
            'description' => 'foo',
        ]);

        $this->assertResponseStatus(self::BAD_REQUEST, $response);
    }

    function test_anonymous_user_can_create_response() {
        $effects = $this->hit_create_request();

        $effects = $this->hit_create_response_for($effects->model);
        $effects->services->filesystem->shouldHaveReceived('write')->twice();
        $effects->services->mailman->shouldHaveReceived('send')->once();

        $this->assertEquals('foo', $effects->response->content->description);
        $this->assertEquals(true, $effects->response->content->user->is_anonymous);

        $this->assertCount(2, File::all());
    }

    function test_user_can_create_response() {
        $request = $this->hit_create_request()->model;

        $user = $this->hit_create_user()->model;
        $user = $this->hit_login($user)->model;

        $effects = $this->hit_create_response_for($request, [], $user);

        $effects->services->filesystem->shouldHaveReceived('write')->twice();
        $effects->services->mailman->shouldHaveReceived('send')->once();

        $this->assertEquals('foo', $effects->response->content->description);
        $this->assertEquals($user->id, $effects->response->content->user->id);

        $this->assertCount(2, File::all());
    }

    function test_backoffice_user_can_delete_request() {
        $keep_request = $this->hit_create_request()->model;
        $keep_response = $this->hit_create_response_for($keep_request)->model;

        $delete_request = $this->hit_create_request()->model;
        $delete_response1 = $this->hit_create_response_for($delete_request)->model;

        $user = $this->hit_create_user()->model;
        $user = $this->hit_login($user)->model;
        $delete_response2 = $this->hit_create_response_for($delete_request, [], $user)->model;

        $backoffice_user = User::where('is_backoffice', true)->first();
        $this->hit_login($backoffice_user);
        $effects = $this->hit_delete_request($delete_request, $backoffice_user);

        $this->assertStatusOK($effects->response);
        $this->assertEquals($delete_request->id, $effects->response->content->id);

        $this->assertNull(Request::find($delete_request->id));
        $this->assertNotNull(Request::find($keep_request->id));

        $this->assertNull(Response::find($delete_response1->id));
        $this->assertNull(Response::find($delete_response2->id));
        $this->assertNotNull(Response::find($keep_response->id));

        $effects->services->filesystem->shouldHaveReceived('delete')->times(4);
        $this->assertCount(2, File::all());
    }

    function test_user_cant_delete_request() {
        $request = $this->hit_create_request()->model;

        $res = $this->post('api/request/delete', [
            'id' => $request->id
        ]);
        $this->assertResponseStatus(self::NOT_FOUND, $res);

        $user = $this->hit_create_user()->model;
        $user = $this->hit_login($user)->model;
        $effects = $this->hit_delete_request($request, $user);
        $this->assertResponseStatus(self::UNAUTHORIZED, $effects->response);
    }

    function test_user_can_anonymize_own_request() {
        $user = $this->hit_create_user()->model;
        $user = $this->hit_login($user)->model;

        $request = $this->hit_create_request([], $user)->model;
        $response = $this->hit_create_response_for($request)->model;
        $effects = $this->hit_delete_request($request, $user);

        $this->assertStatusOK($effects->response);
        $this->assertTrue($request->refresh()->user->is_anonymous);

        $this->assertNotNull(Response::find($response->id));
        $this->assertCount(2, File::all());
    }

    function test_backoffice_user_can_delete_response() {
        $request = $this->hit_create_request()->model;

        $keep_response = $this->hit_create_response_for($request)->model;
        $delete_response = $this->hit_create_response_for($request)->model;

        $backoffice_user = User::where('is_backoffice', true)->first();
        $this->hit_login($backoffice_user);
        $effects = $this->hit_delete_response($delete_response, $backoffice_user);

        $this->assertStatusOK($effects->response);
        $this->assertEquals($delete_response->id, $effects->response->content->id);

        $effects->services->filesystem->shouldHaveReceived('delete')->times(2);
        $this->assertCount(2, File::all());

        $this->assertNull(Response::find($delete_response->id));
        $this->assertNotNull(Response::find($keep_response->id));
    }

    function test_user_cant_delete_others_response() {
        $request = $this->hit_create_request()->model;
        $response = $this->hit_create_response_for($request)->model;

        $res = $this->post('api/response/delete', [
            'id' => $response->id
        ]);

        $this->assertResponseStatus(self::NOT_FOUND, $res);

        $user = $this->hit_create_user()->model;
        $user = $this->hit_login($user)->model;
        $effects = $this->hit_delete_response($response, $user);
        $this->assertResponseStatus(self::UNAUTHORIZED, $effects->response);
    }

    function test_user_can_delete_own_response() {
        $request = $this->hit_create_request()->model;

        $user = $this->hit_create_user()->model;
        $user = $this->hit_login($user)->model;
        $response = $this->hit_create_response_for($request, [], $user)->model;
        $effects = $this->hit_delete_response($response, $user);

        $this->assertStatusOK($effects->response);
        $effects->services->filesystem->shouldHaveReceived('delete')->times(2);
        $this->assertNull(Response::find($response->id));
    }

    function test_backoffice_user_can_delete_user() {
        $user = $this->hit_create_user()->model;
        $user = $this->hit_login($user)->model;

        $users_request = $this->hit_create_request([], $user)->model;

        $other_users_request = $this->hit_create_request()->model;
        $users_response1 = $this->hit_create_response_for($other_users_request, [], $user)->model;
        $users_response2 = $this->hit_create_response_for($other_users_request, [], $user)->model;

        $backoffice_user = User::where('is_backoffice', true)->first();
        $this->hit_login($backoffice_user);
        $effects = $this->hit_delete_user($user, $backoffice_user);

        $this->assertStatusOK($effects->response);
        $this->assertEquals($user->id, $effects->response->content->id);

        $this->assertTrue($users_request->refresh()->user->is_anonymous);

        $this->assertNull(Response::find($users_response1->id));
        $this->assertNull(Response::find($users_response2->id));
        $effects->services->filesystem->shouldHaveReceived('delete')->times(4);
        $this->assertCount(0, File::all());

        // TODO: Transfer upvotes to anonymous user
    }

    function test_user_cant_delete_other_user() {
        $user1 = $this->hit_create_user()->model;
        $user1 = $this->hit_login($user1)->model;

        $user2 = $this->hit_create_user()->model;
        $user2 = $this->hit_login($user2)->model;

        $effects = $this->hit_delete_user($user1, $user2);
        $this->assertResponseStatus(self::UNAUTHORIZED, $effects->response);
    }

    function test_user_can_delete_self() {
        $user1 = $this->hit_create_user()->model;
        $user1 = $this->hit_login($user1)->model;
        $effects = $this->hit_delete_user($user1, $user1);
        $this->assertStatusOK($effects->response);
    }

    function test_anonymous_user_can_upvote_request_and_response() {
        $request = $this->hit_create_request()->model;
        $response = $this->hit_create_response_for($request)->model;

        $request = $this->hit_upvote_request($request)->model;
        $response = $this->hit_upvote_response($response)->model;
        $response = $this->hit_upvote_response($response)->model;

        $this->assertCount(1, $request->upvotes);
        $this->assertCount(2, $response->upvotes);
    }

    function test_user_can_upvote_request_and_response() {
        $request = $this->hit_create_request()->model;
        $response = $this->hit_create_response_for($request)->model;

        $user = $this->hit_create_user()->model;
        $user = $this->hit_login($user)->model;

        $request = $this->hit_upvote_request($request, $user)->model;
        $response = $this->hit_upvote_response($response, $user)->model;
        $response = $this->hit_upvote_response($response, $user)->model;

        $this->assertCount(1, $request->upvotes);
        $this->assertCount(1, $response->upvotes);
    }

    function test_user_cant_upvote_own_request_and_response() {
        $user = $this->hit_create_user()->model;
        $user = $this->hit_login($user)->model;

        $request = $this->hit_create_request([], $user)->model;
        $response = $this->hit_create_response_for($request, [], $user)->model;

        $effects = $this->hit_upvote_request($request, $user);
        $this->assertResponseStatus(self::CONFLICT, $effects->response);

        $effects = $this->hit_upvote_response($response, $user);
        $this->assertResponseStatus(self::CONFLICT, $effects->response);
    }

    function test_user_cant_upvote_request_and_response_twice() {
        $user = $this->hit_create_user()->model;
        $user = $this->hit_login($user)->model;

        $request = $this->hit_create_request()->model;
        $response = $this->hit_create_response_for($request)->model;

        $this->hit_upvote_request($request, $user);
        $effects = $this->hit_upvote_request($request, $user);
        $this->assertResponseStatus(self::CONFLICT, $effects->response);

        $this->hit_upvote_response($response, $user);
        $effects = $this->hit_upvote_response($response, $user);
        $this->assertResponseStatus(self::CONFLICT, $effects->response);

        $this->assertCount(1, $request->upvotes);
        $this->assertCount(1, $response->upvotes);
    }

    function test_user_can_report_question() {

    }

    function test_user_can_report_answer() {

    }
// GOOD QUESTIONS ARE IMPORTANT: A user should get points if their questions/answers on questions get upvotes
}
