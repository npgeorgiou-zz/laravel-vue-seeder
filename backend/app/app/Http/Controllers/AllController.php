<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Xrequests\Core\Errors\Conflict;
use Xrequests\Core\Errors\MissingInput;
use Xrequests\Core\Errors\ModelExists;
use Xrequests\Core\Errors\ModelNotFound;
use Xrequests\Core\Errors\Unauthorized;
use Xrequests\Core\Errors\UsernameExists;
use Xrequests\Services\Filesystem\Filesystem;
use Xrequests\Services\Filesystem\Local;
use Xrequests\Services\Mailman\Mailman;
use function Xrequests\Core\Cases\create_request;
use function Xrequests\Core\Cases\create_response;
use function Xrequests\Core\Cases\create_user;
use function Xrequests\Core\Cases\delete_request;
use function Xrequests\Core\Cases\delete_response;
use function Xrequests\Core\Cases\delete_user;
use function Xrequests\Core\Cases\forgot_password;
use function Xrequests\Core\Cases\login;
use function Xrequests\Core\Cases\reset_password;
use function Xrequests\Core\Cases\upvote_request;
use function Xrequests\Core\Cases\upvote_response;

class AllController extends Controller {
    const OK = Response::HTTP_OK;
    const UNAUTHORIZED = Response::HTTP_UNAUTHORIZED;
    const CONFLICT = Response::HTTP_CONFLICT;
    const NOT_FOUND = Response::HTTP_NOT_FOUND;
    const BAD_REQUEST = Response::HTTP_BAD_REQUEST;

    public function create(Request $request, Mailman $mailman) {
        try {
            $user = create_user((object)[
                'username' => $request->input('username'),
                'email' => $request->input('email'),
                'password' => $request->input('password')
            ], $mailman);

            return response()->json($user);
        } catch (UsernameExists) {
            return response('Username exists', self::CONFLICT);
        } catch (ModelExists) {
            return response('Email exists', self::CONFLICT);
        }
    }

    public function login(Request $request) {
        try {
            $user = login($request->email, $request->password);

            // Add token.
            $token = $user->token;
            $user = json_decode($user->toJson());
            $user->token = $token;
            return response()->json($user);
        } catch (ModelNotFound) {
            return response('Cant do that', self::UNAUTHORIZED);
        }
    }

    public function forgot_password(Request $request, Mailman $mailman) {
        forgot_password($request->email, $mailman);
        return response()->json();
    }

    public function reset_password(Request $request, Mailman $mailman) {
        try {
            reset_password($request->password, $request->token);
            return response('OK', self::OK);
        } catch (ModelNotFound) {
            return response('token already used', self::CONFLICT);
        }
    }

    public function create_request(Request $request) {
        $request = create_request($request->input('token'), (object)[
            'description' => $request->input('description'),
            'validation' => $request->input('validation'),
        ]);

        return response()->json($request);
    }

    public function delete_request(Request $request, Filesystem $filesystem) {
        try {
            $request = delete_request(
                $request->input('token'),
                $request->input('id'),
                $filesystem
            );
            return response()->json($request);
        } catch (ModelNotFound) {
            return response('Model not found', self::NOT_FOUND);
        } catch (Unauthorized) {
            return response('Cant do that', self::UNAUTHORIZED);
        }
    }

    public function create_response(Request $request, Filesystem $filesystem, Mailman $mailman) {

        $files = $request->allFiles();
        $files = array_key_exists('files', $files) ? $files['files'] : [];

        try {
            $request = create_response(
                $request->input('token'),
                $request->input('request_id'),
                $files,
                (object)[
                    'description' => $request->input('description')
                ],
                $filesystem,
                $mailman
            );

            return response()->json($request);
        } catch (MissingInput) {
            return response('Missing files', self::BAD_REQUEST);
        }
    }

    public function delete_response(Request $request, Filesystem $filesystem) {
        try {
            $request = delete_response(
                $request->input('token'),
                $request->input('id'),
                $filesystem
            );
            return response()->json($request);
        } catch (ModelNotFound) {
            return response('Model not found', self::NOT_FOUND);
        } catch (Unauthorized) {
            return response('Cant do that', self::UNAUTHORIZED);
        }
    }

    public function delete_user(Request $request, Filesystem $filesystem) {
        try {
            $request = delete_user(
                $request->input('token'),
                $request->input('id'),
                $filesystem
            );
            return response()->json($request);
        } catch (ModelNotFound) {
            return response('Model not found', self::NOT_FOUND);
        } catch (Unauthorized) {
            return response('Cant do that', self::UNAUTHORIZED);
        }
    }

    public function upvote_request(Request $request) {
        try {
            $request = upvote_request(
                $request->input('token'),
                $request->input('id')
            );

            return response()->json($request);
        } catch (Conflict) {
            return response('Can upvote', self::CONFLICT);
        }
    }

    public function upvote_response(Request $request) {
        try {
            $response = upvote_response(
                $request->input('token'),
                $request->input('id')
            );

            return response()->json($response);
        } catch (Conflict) {
            return response('Can upvote', self::CONFLICT);
        }
    }
}
