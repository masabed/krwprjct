<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class LoginController extends Controller
{
    public function __invoke(Request $request)
    {
        // 1) Validate (param captcha: recaptcha)
        $validator = Validator::make($request->all(), [
            'username'  => 'required|string',
            'password'  => 'required|string|min:8',
            'recaptcha' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        // 2) Verify Turnstile (server-side) dari field "recaptcha"
        $verify = $this->verifyTurnstile(
            (string) $request->input('recaptcha'),
            (string) $request->ip()
        );

        if (!($verify['success'] ?? false)) {
            return response()->json([
                'message' => 'Captcha verification failed.',
                'errors'  => $verify['error-codes'] ?? ['turnstile_failed'],
            ], 403);
        }

        // Optional: cek action kalau kamu set data-action="login"
        $expectedAction = (string) env('TURNSTILE_ACTION', 'login');
        if ($expectedAction !== '' && isset($verify['action']) && $verify['action'] !== $expectedAction) {
            return response()->json([
                'message' => 'Captcha action mismatch.',
            ], 403);
        }

        $credentials = $request->only('username', 'password');

        // 3) Attempt login (JWT token langsung dari attempt)
        $token = auth()->attempt($credentials);
        if (!$token) {
            return response()->json([
                'message' => 'Invalid username or password.',
            ], 401);
        }

        $user = auth()->user();

        // 4) Update last_login_at & previous_login_at
        //    previous_login_at = nilai last_login_at sebelumnya
        $previous = $user->last_login_at; // bisa null pada login pertama

        $user->forceFill([
            'previous_login_at' => $previous,
            'last_login_at'     => now(),
        ])->save();

        $user->refresh();

        // 5) Return ONLY token + last/previous login (format ISO)
        $lastIso = $user->last_login_at
            ? Carbon::parse($user->last_login_at)->toISOString()
            : null;

        $prevIso = $user->previous_login_at
            ? Carbon::parse($user->previous_login_at)->toISOString()
            : null;

        return response()->json([
            'token'             => $token,
            'last_login_at'     => $lastIso,
            'previous_login_at' => $prevIso,
        ]);
    }

    private function verifyTurnstile(string $token, string $ip = ''): array
    {
        $secret = (string) env('TURNSTILE_SECRET', '');
        if ($secret === '') {
            return ['success' => false, 'error-codes' => ['missing-turnstile-secret']];
        }

        $payload = [
            'secret'           => $secret,
            'response'         => $token,
            'idempotency_key'  => (string) Str::uuid(),
        ];

        if ($ip !== '') {
            $payload['remoteip'] = $ip;
        }

        try {
            $res = Http::asForm()
                ->timeout(8)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', $payload);

            return $res->json() ?? ['success' => false, 'error-codes' => ['bad-siteverify-response']];
        } catch (ConnectionException $e) {
            return ['success' => false, 'error-codes' => ['turnstile_connection_error']];
        }
    }
}
