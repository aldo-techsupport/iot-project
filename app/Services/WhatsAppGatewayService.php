<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WhatsAppGatewayService
{
    private const SUCCESS_STATUSES = [
        'PENDING',
        'QUEUED',
        'SENT',
        'DELIVERED',
        'DELIVERED_TO_DEVICE',
        'DELIVERED_TO_GATEWAY',
        'SUCCESS',
    ];

    public function __construct(
        protected readonly string $baseUrl,
        protected readonly string $token,
    ) {
    }

    public static function make(?string $baseUrl = null, ?string $token = null): self
    {
        $baseUrl ??= rtrim(env('WA_GATEWAY_URL', ''), '/');
        $token ??= env('WA_GATEWAY_SECRET', '');

        if (blank($baseUrl) || blank($token)) {
            throw new \RuntimeException('WhatsApp gateway configuration is missing.');
        }

        return new self($baseUrl, $token);
    }

    public function listSessions(): array
    {
        $response = $this->client()
            ->get($this->endpoint('/session'))
            ->throw()
            ->json();

        if (is_array($response)) {
            if (isset($response['sessions']) && is_array($response['sessions'])) {
                return $response['sessions'];
            }

            if (isset($response['data']) && is_array($response['data'])) {
                return $response['data'];
            }
        }

        return [];
    }

    public function getSession(string $session): array
    {
        $payload = $this->client()
            ->get($this->endpoint('/session'), ['session' => $session])
            ->throw()
            ->json();

        if (is_array($payload)) {
            return $payload;
        }

        return [];
    }

    public function startSession(string $session, array $options = []): array
    {
        $payload = array_filter([
            'session' => $session,
        ] + $options);

        $response = $this->client()->post($this->endpoint('/session/start'), $payload);

        if ($response->failed() && !$this->sessionAlreadyExists($response->json())) {
            $response->throw();
        }

        return $response->json() ?? [];
    }

    public function stopSession(string $session): array
    {
        $endpoint = $this->endpoint('/session/logout');
        $queryEndpoint = $endpoint.'?session='.urlencode($session);

        $response = $this->client()->post($queryEndpoint);

        if ($response->failed()) {
            $response = $this->client()->post($endpoint, ['session' => $session]);
        }

        if ($response->failed()) {
            $response->throw();
        }

        return $response->json() ?? [];
    }


    public function sendTextMessage(string $session, string $to, string $text, bool $isGroup = false): array
    {
        $payload = [
            'session' => $session,
            'to' => preg_replace('/\D+/', '', $to),
            'text' => $text,
            'is_group' => $isGroup,
        ];

        $response = $this->client()
            ->post($this->endpoint('/message/send-text'), $payload)
            ->throw()
            ->json() ?? [];

        if (!$this->responseIndicatesSuccess($response)) {
            throw new \RuntimeException('Gateway did not return success status: '.json_encode($response));
        }

        return $response;
    }

    public function sendImageMessage(string $session, string $to, string $text, string $imageUrl, bool $isGroup = false): array
    {
        $payload = [
            'session' => $session,
            'to' => preg_replace('/\D+/', '', $to),
            'text' => $text,
            'image_url' => $imageUrl,
            'is_group' => $isGroup,
        ];

        $response = $this->client()
            ->post($this->endpoint('/message/send-image'), $payload)
            ->throw()
            ->json() ?? [];

        if (!$this->responseIndicatesSuccess($response)) {
            throw new \RuntimeException('Gateway did not return success status: '.json_encode($response));
        }

        return $response;
    }

    public function sendDocumentMessage(string $session, string $to, string $text, string $documentUrl, string $documentName, bool $isGroup = false): array
    {
        $payload = [
            'session' => $session,
            'to' => preg_replace('/\D+/', '', $to),
            'text' => $text,
            'document_url' => $documentUrl,
            'document_name' => $documentName,
            'is_group' => $isGroup,
        ];

        $response = $this->client()
            ->post($this->endpoint('/message/send-document'), $payload)
            ->throw()
            ->json() ?? [];

        if (!$this->responseIndicatesSuccess($response)) {
            throw new \RuntimeException('Gateway did not return success status: '.json_encode($response));
        }

        return $response;
    }

    public function sendStickerMessage(string $session, string $to, string $imageUrl, bool $isGroup = false): array
    {
        $payload = [
            'session' => $session,
            'to' => preg_replace('/\D+/', '', $to),
            'image_url' => $imageUrl,
            'is_group' => $isGroup,
        ];

        $response = $this->client()
            ->post($this->endpoint('/message/send-sticker'), $payload)
            ->throw()
            ->json() ?? [];

        if (!$this->responseIndicatesSuccess($response)) {
            throw new \RuntimeException('Gateway did not return success status: '.json_encode($response));
        }

        return $response;
    }

    public function sendMediaMessage(array $payload, UploadedFile $file, string $endpoint): array
    {
        $formData = Arr::only($payload, [
            'session',
            'to',
            'caption',
            'is_group',
        ]);

        $response = $this->client()
            ->attach('file', fopen($file->getRealPath(), 'rb'), $file->getClientOriginalName())
            ->post($this->endpoint($endpoint), $formData);

        if ($response->failed()) {
            $response->throw();
        }

        return $response->json() ?? [];
    }

    public function getQueueStatus(string $session): array
    {
        $response = $this->client()
            ->get($this->endpoint('/message/queue-status'), ['session' => $session])
            ->throw()
            ->json();

        return $response ?? [];
    }


    public function getProfile(string $session, string $target): array
    {
        $payload = [
            'session' => $session,
            'target' => $target,
        ];

        $response = $this->client()
            ->post($this->endpoint('/profile'), $payload)
            ->throw()
            ->json();

        return $response ?? [];
    }

    // ========== Webhook ==========

    public function bridgeSessionEvent(array $payload): void
    {
        $this->client()
            ->post($this->endpoint('/session/event'), $payload)
            ->throw();
    }

    // ========== Helper Methods ==========

    protected function sessionAlreadyExists(?array $payload): bool
    {
        if (!$payload) {
            return false;
        }

        $message = Str::lower((string) data_get($payload, 'message', ''));

        return Str::contains($message, 'already') && Str::contains($message, 'session');
    }

    protected function responseIndicatesSuccess(array $response): bool
    {
        $successFlag = $this->normalizeBoolean($response['success'] ?? null);

        if ($successFlag === true) {
            return true;
        }

        $status = $this->extractStatus($response);

        if ($status !== null) {
            if (is_numeric($status)) {
                $numericStatus = (int) $status;

                if ($numericStatus >= 200 && $numericStatus < 400) {
                    return true;
                }
            }

            if (in_array(strtoupper((string) $status), self::SUCCESS_STATUSES, true)) {
                return true;
            }
        }

        if (
            isset($response['message'])
            && !isset($response['error'])
            && !isset($response['errors'])
        ) {
            return true;
        }

        return false;
    }

    protected function extractStatus(array $payload): ?string
    {
        if (array_key_exists('status', $payload)) {
            $status = $payload['status'];

            if (is_string($status) || is_numeric($status)) {
                return (string) $status;
            }
        }

        foreach ($payload as $value) {
            if (is_array($value)) {
                $nestedStatus = $this->extractStatus($value);

                if ($nestedStatus !== null) {
                    return $nestedStatus;
                }
            }
        }

        return null;
    }

    protected function normalizeBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower($value);

            if (in_array($normalized, ['true', '1', 'yes'], true)) {
                return true;
            }

            if (in_array($normalized, ['false', '0', 'no'], true)) {
                return false;
            }
        }

        if (is_int($value)) {
            if ($value === 1) {
                return true;
            }

            if ($value === 0) {
                return false;
            }
        }

        return null;
    }

    protected function endpoint(string $path): string
    {
        return $this->baseUrl.$path;
    }

    protected function client(): PendingRequest
    {
        return Http::withToken($this->token)
            ->acceptJson()
            ->timeout(30)
            ->retry(3, 1000, function ($exception, $request) {
                // Retry on connection errors (including DNS failures)
                if ($exception instanceof \Illuminate\Http\Client\ConnectionException) {
                    \Log::warning('WhatsApp Gateway connection failed, retrying...', [
                        'url' => $request->url(),
                        'error' => $exception->getMessage(),
                    ]);
                    return true;
                }
                return false;
            }, throw: false);
    }
}
