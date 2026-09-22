<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use App\Models\ApiUser;
use Closure;
use Illuminate\Support\Facades\Auth;

class ApiAuth
{
    /** @param Request $request */
    public function handle($request, Closure $next)
    {
        $auth = $request->header('Authorization');

        // hash_equals, not in_array: loose == coerces numeric tokens ("1e3" == "1000").
        $valid = false;
        if (is_string($auth) && $auth !== '') {
            foreach ((array) config('app.api.authlist') as $token) {
                if (is_string($token) && $token !== '' && hash_equals($token, $auth)) {
                    $valid = true;
                    break;
                }
            }
        }

        if (!$valid) {
            return response(['error' => 'No authorization or invalid.'], 401);
        }

        $owner = $request->header('Owner');

        if (!$owner) {
            return response(['error' => 'No owner.'], 401);
        }

        $user = new ApiUser();
        $user->owner = $owner;

        Auth::login($user);

        return $next($request);
    }
}
