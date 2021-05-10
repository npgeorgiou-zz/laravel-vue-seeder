<?php

namespace Xrequests\Core\Cases;

use App\Models\File;
use App\Models\Request;
use App\Models\RequestUpvote;
use App\Models\Response;
use App\Models\ResponseUpvote;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Xrequests\Core\Errors\Conflict;
use Xrequests\Core\Errors\MissingInput;
use Xrequests\Core\Errors\ModelExists;
use Xrequests\Core\Errors\ModelNotFound;
use Xrequests\Core\Errors\Unauthorized;
use Xrequests\Core\Errors\UsernameExists;
use Xrequests\Services\Filesystem\Filesystem;
use Xrequests\Services\Mailman\Mailman;
use Xrequests\Services\Persistence\Database;

function create_user($values, Mailman $mailman): User {
    $user = User::where('username', $values->username)->first();
    if ($user) {
        throw new UsernameExists();
    }

    $user = User::where('email', $values->email)->first();
    if ($user) {
        throw new ModelExists();
    }

    $user = new User();
    $user->username = $values->username;
    $user->email = $values->email;
    $user->password = $values->password;
    $user->save();

    // Send email
    $mailman->send(
        $user->email,
        'emails.welcome',
        'Welcome',
        ['username' => $user->username]
    );

    return $user->refresh();
}

function delete_user($token, $id, Filesystem $filesystem): User {
    $user = User::where('token', $token)->first();
    if (!$token || !$user) {
        throw new ModelNotFound();
    }

    $to_delete = User::find($id);
    if (!$to_delete) {
        throw new ModelNotFound();
    }

    if (!$user->is_backoffice && $user->isNot($to_delete)) {
        throw new Unauthorized();
    }

    // cleanup
    foreach ($to_delete->responses as $response) {
        _delete_response($response, $filesystem);
    }

    $anonymous = User::where('is_anonymous', true)->first();
    foreach ($to_delete->requests as $request) {
        $request->user()->associate($anonymous);
        $request->save();
    }

    $to_delete->delete();
    return $to_delete;
}

function login($email, $password): User {
    // TODO Password encryption at creation
    $user = User
        ::where('email', $email)
        ->where('password', $password)
        ->first();

    if (!$user) {
        throw new ModelNotFound();
    }

    do {
        $token = uniqid();
    } while (User::where('token', $token)->first());

    $user->token = $token;
    $user->save();

    return $user;
}

function forgot_password($email, Mailman $mailman) {
    $user = User::where('email', $email)->first();

    if (!$user) {
        return;
    }

    $token = (new Database())->create_password_reset_token($email);
    $link = "foo/$token";

    $mailman->send(
        $user->email,
        'emails.reset_password',
        'Welcome',
        ['username' => $user->username, 'link' => $link]
    );
}

function reset_password($password, $token) {
    $token = (new Database())->find_password_reset_token($token);

    if (!$token) {
        throw new ModelNotFound();
    }

    $email = $token->email;

    $user = User::where('email', $email)->first();
    $user->password = $password;
    $user->save();

    (new Database())->delete_password_reset_token($token->token);
}

function create_request($token, $values): Request {
    if (!$token) {
        $user = User::where('is_anonymous', true)->first();
    } else {
        $user = User::where('token', $token)->first();
    }

    $request = new Request();
    $request->description = $values->description;
    $request->validation = $values->validation;
    $request->user()->associate($user);

    $request->save();
    return $request;
}

function delete_request($token, $id, Filesystem $filesystem): Request {
    $user = User::where('token', $token)->first();
    if (!$token || !$user) {
        throw new ModelNotFound();
    }

    $request = Request::find($id);
    if (!$request) {
        throw new ModelNotFound();
    }

    if (!$user->is_backoffice && $user->isNot($request->user)) {
        throw new Unauthorized();
    }

    if ($user->is_backoffice) {
        // cleanup
        foreach ($request->responses as $response) {
            _delete_response($response, $filesystem);
        }

        $request->delete();
        return $request;
    }

    $anonymous = User::where('is_anonymous', true)->first();
    $request->user()->associate($anonymous);
    $request->save();
    return $request;
}

function create_response(
    $token,
    $request_id,
    $files,
    $values,
    Filesystem $filesystem,
    Mailman $mailman
): Response {
    if (!$files) {
        throw new MissingInput();
    }

    if (!$token) {
        $user = User::where('is_anonymous', true)->first();
    } else {
        $user = User::where('token', $token)->first();
    }

    $request = Request::find($request_id);

    $response = new Response();
    $response->description = $values->description;
    $response->user()->associate($user);
    $response->request()->associate($request);
    $response->save();

    foreach ($files as $file) {
        $asset = new File();
        $asset->name = $user->id . uniqid(true);
        $asset->mimetype = $file->clientExtension();
        $asset->response()->associate($response);
        $asset->save();

        $filesystem->write($asset, $file->get());
    }

    $mailman->send(
        env('BACKOFFICE_EMAIL'),
        'emails.new_response',
        'New response',
        ['response' => $response]
    );

    return $response->refresh();
}

function delete_response($token, $id, Filesystem $filesystem): Response {
    $user = User::where('token', $token)->first();
    if (!$token || !$user) {
        throw new ModelNotFound();
    }

    $response = Response::find($id);
    if (!$response) {
        throw new ModelNotFound();
    }

    if (!$user->is_backoffice && $user->isNot($response->user)) {
        throw new Unauthorized();
    }

    // cleanup
    _delete_response($response, $filesystem);

    return $response;
}


function upvote_request($token, $id): Request {
    if (!$token) {
        $user = User::where('is_anonymous', true)->first();
    } else {
        $user = User::where('token', $token)->first();
    }

    $request = Request::find($id);

    if (!$user->is_anonymous) {
        // Cant upvote own content
        if ($user->is($request->user)) {
            throw new Conflict();
        }

        // Cant upvote same content twice
        $existingUpvote = $request->upvotes()->where('user_id', $user->id)->first();
        if ($existingUpvote) {
            throw new Conflict();
        }
    }

    $upvote = new RequestUpvote();
    $upvote->user()->associate($user);
    $upvote->request()->associate($request);
    $upvote->save();

    return $request;
}

function upvote_response($token, $id): Response {
    if (!$token) {
        $user = User::where('is_anonymous', true)->first();
    } else {
        $user = User::where('token', $token)->first();
    }

    $response = Response::find($id);

    if (!$user->is_anonymous) {
        // Cant upvote own content
        if ($user->is($response->user)) {
            throw new Conflict();
        }

        // Cant upvote same content twice
        $existingUpvote = $response->upvotes()->where('user_id', $user->id)->first();
        if ($existingUpvote) {
            throw new Conflict();
        }
    }

    $upvote = new ResponseUpvote();
    $upvote->user()->associate($user);
    $upvote->response()->associate($response);
    $upvote->save();

    return $response;
}

function list_requests($token, $values): array {
}

function list_responses($token, $values): array {
}

function _delete_response(Response $response, Filesystem $filesystem) {
    foreach ($response->files as $asset) {
        $filesystem->delete($asset);
        $asset->delete();
    }

    $response->delete();
}
