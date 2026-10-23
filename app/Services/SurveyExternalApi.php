<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SurveyExternalApi
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $username,
        private readonly string $password,
        private readonly string $authPath = 'auth/login',
        private readonly string $lookupPath = 'solar/member/lookup',
    ) {}

    public static function make(): self
    {
        $cfg = config('services.survey_external') ?? [];
        // API doc: auth = /api/external/auth/login (no solar), lookup = /api/external/solar/member/lookup.
        // Normalize so base never ends with /solar (avoids .../solar/solar/member/lookup or .../solar/auth/login).
        $baseUrl = rtrim((string) ($cfg['base_url'] ?? ''), '/');
        if (str_ends_with($baseUrl, '/solar')) {
            $baseUrl = rtrim(substr($baseUrl, 0, -6), '/');
        }
        return new self(
            $baseUrl,
            (string) ($cfg['username'] ?? ''),
            (string) ($cfg['password'] ?? ''),
            (string) ($cfg['auth_path'] ?? 'auth/login'),
            (string) ($cfg['lookup_path'] ?? 'solar/member/lookup'),
        );
    }

    public static function getPowerDeptInstance(): self
    {
        // configuration for power dept instance is same as default, so using make() method
        $config = config('services.power_dept_reference_api') ?? [];

        return new self(
            rtrim((string) ($config['base_url'] ?? ''), '/'),
            (string) ($config['username'] ?? ''),
            (string) ($config['password'] ?? ''),
        );
    }

    // get consumer details from power dept reference api
    public function getConsumerDetails(string $consumerNumber): array
    {
        // $token = $this->token(); // get token using existing method
        $config = config('services.power_dept_reference_api') ?? [];
        try {
            $response = Http::timeout(90)
                ->acceptJson()
                ->withoutVerifying()
                ->post($this->baseUrl, [
                    'username' => (string) ($config['username'] ?? ''),
                    'password' => (string) ($config['password'] ?? ''),
                    'reference_number' => $consumerNumber,
                ])
                ->throw()
                ->json();

            return is_array($response) ? $response : ['success' => false, 'message' => 'Invalid response from Power Dept'];
        } catch (ConnectionException $e) {
            Log::warning('Power Dept connection failed', ['message' => $e->getMessage()]);
            return [
                'success' => false,
                'error_code' => 'SERVICE_UNAVAILABLE',
                'message' => 'Electricity bill verification service is not reachable. Please try again later.',
            ];
        } catch (RequestException $e) {
            Log::warning('Power Dept request failed', ['message' => $e->getMessage()]);
            return [
                'success' => false,
                'error_code' => 'SERVICE_UNAVAILABLE',
                'message' => 'Power Dept service unreachable',
            ];
        } catch (\Throwable $e) {
            Log::warning('Power Dept unexpected error', ['message' => $e->getMessage()]);
            return [
                'success' => false,
                'error_code' => 'SERVICE_ERROR',
                'message' => 'Unable to verify consumer details at this moment.',
            ];
        }
    }

    /** HTTP client for Survey API: SSL verification disabled (expired cert), optional IPv4. */
    private function surveyHttp(): \Illuminate\Http\Client\PendingRequest
    {
        $opts = ['verify' => false];
        if (defined('CURLOPT_IPRESOLVE')) {
            $opts['curl'] = [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4];
        }
        return Http::acceptJson()
            ->withoutVerifying()
            ->withOptions($opts);
    }

    /** Get cached token or login */
    public function token(): string
    {
        $baseUrl = $this->baseUrl;
        return Cache::remember('survey_external_token', now()->addHours(23), function () use ($baseUrl) {
            $loginUrl = $baseUrl . '/' . ltrim($this->authPath, '/');
            $resp = $this->surveyHttp()
                ->timeout(90)
                ->post($loginUrl, [
                    'username' => $this->username,
                    'password' => $this->password,
                ])
                ->throw()
                ->json();

            Log::info('Survey auth response', ['success' => $resp['success'] ?? false]);

            if (!($resp['success'] ?? false)) {
                throw new \RuntimeException('Survey login failed: ' . ($resp['message'] ?? 'unknown'));
            }
            return $resp['data']['access_token'] ?? throw new \RuntimeException('Survey token missing');
        });
    }

    /** Lookup member by CNIC (must be dashed format) */
    public function lookup(string $cnicDashed): array
    {
        if (empty($this->baseUrl)) {
            return [
                'success' => false,
                'message' => 'Survey external API is not configured (base URL missing).',
            ];
        }

        $lookupUrl = $this->baseUrl . '/' . ltrim($this->lookupPath, '/');
        $http = fn () => $this->surveyHttp()->timeout(120);

        try {
            $token = $this->token();
        } catch (ConnectionException $e) {
            Log::warning('Survey API: connection failed during auth', [
                'message' => $e->getMessage(),
                'base_url' => $this->baseUrl,
            ]);
            return [
                'success' => false,
                'message' => 'Survey service is not reachable. Please check your network and try again.',
            ];
        } catch (\Throwable $e) {
            Log::warning('Survey API: auth failed', [
                'message' => $e->getMessage(),
                'base_url' => $this->baseUrl,
            ]);
            return [
                'success' => false,
                'message' => 'Survey authentication failed: ' . $e->getMessage(),
            ];
        }

        try {
            $resp = $http()
                ->withToken($token)
                ->post($lookupUrl, ['cnic' => $cnicDashed])
                ->throw()
                ->json();

            return is_array($resp) ? $resp : ['success' => false, 'message' => 'Invalid response from survey service'];
        } catch (ConnectionException $e) {
            Log::warning('Survey API: connection failed during lookup', [
                'message' => $e->getMessage(),
                'lookup_url' => $lookupUrl,
            ]);
            return [
                'success' => false,
                'message' => 'Survey service is not reachable. Please check your network and try again.',
            ];
        } catch (RequestException $e) {
            // If token expired/invalid, clear cache and retry once
            $status = $e->response?->status();
            $body = $e->response?->json();
            $errorCode = is_array($body) ? ($body['error_code'] ?? null) : null;

            if ($status === 401 && in_array($errorCode, ['TOKEN_EXPIRED', 'INVALID_TOKEN'], true)) {
                Cache::forget('survey_external_token');
                try {
                    $token = $this->token();
                    $resp = $http()
                        ->withToken($token)
                        ->post($lookupUrl, ['cnic' => $cnicDashed])
                        ->throw()
                        ->json();
                    return is_array($resp) ? $resp : ['success' => false, 'message' => 'Invalid response from survey service'];
                } catch (\Throwable $retryEx) {
                    Log::warning('Survey API: retry after 401 failed', ['message' => $retryEx->getMessage()]);
                }
            }

            Log::warning('Survey lookup request failed', ['status' => $status, 'body' => $body ?? '']);
            return is_array($body) ? $body : [
                'success' => false,
                'error_code' => 'SERVICE_UNAVAILABLE',
                'message' => 'Survey service unreachable',
            ];
        } catch (\Throwable $e) {
            Log::warning('Survey lookup unexpected failure', ['message' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Survey verification failed: ' . $e->getMessage(),
            ];
        }
    }
}
