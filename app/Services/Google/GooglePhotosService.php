<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class GooglePhotosService
{
    private const string AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const string TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const string USERINFO_URL = 'https://www.googleapis.com/oauth2/v2/userinfo';

    private const string SESSIONS_URL = 'https://photospicker.googleapis.com/v1/sessions';

    private const string PICKER_MEDIA_ITEMS_URL = 'https://photospicker.googleapis.com/v1/mediaItems';

    private const string MEDIA_ITEMS_URL = 'https://photoslibrary.googleapis.com/v1/mediaItems';

    private const string SCOPE = 'https://www.googleapis.com/auth/photospicker.mediaitems.readonly https://www.googleapis.com/auth/userinfo.email';

    public function getClientId(): ?string
    {
        /** @var ?string $settingId */
        $settingId = Setting::get('google_photos.client_id');

        if ($settingId !== null && $settingId !== '') {
            return $settingId;
        }

        /** @var ?string $configId */
        $configId = config('services.google.client_id');

        return ($configId !== null && $configId !== '') ? $configId : null;
    }

    public function getClientSecret(): ?string
    {
        /** @var ?string $encryptedSecret */
        $encryptedSecret = Setting::get('google_photos.client_secret');

        if ($encryptedSecret !== null && $encryptedSecret !== '') {
            try {
                return Crypt::decryptString($encryptedSecret);
            } catch (Throwable) {
                return $encryptedSecret;
            }
        }

        /** @var ?string $configSecret */
        $configSecret = config('services.google.client_secret');

        return ($configSecret !== null && $configSecret !== '') ? $configSecret : null;
    }

    public function getRedirectUri(): string
    {
        /** @var string $redirectUri */
        $redirectUri = config('services.google.redirect_uri', '/admin/google-photos/callback');

        if (str_starts_with($redirectUri, 'http://') || str_starts_with($redirectUri, 'https://')) {
            return $redirectUri;
        }

        return url($redirectUri);
    }

    public function getAuthUrl(): string
    {
        $clientId = $this->getClientId();

        if ($clientId === null || $clientId === '') {
            throw new RuntimeException('Google Client ID is not configured.');
        }

        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $this->getRedirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ];

        return self::AUTH_URL.'?'.http_build_query($params);
    }

    /**
     * @return array<string, mixed>
     */
    public function handleCallback(string $code): array
    {
        $clientId = $this->getClientId();
        $clientSecret = $this->getClientSecret();

        if ($clientId === null || $clientSecret === null) {
            throw new RuntimeException('Google OAuth client credentials are not configured.');
        }

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->getRedirectUri(),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to exchange authorization code: '.$response->body());
        }

        /** @var array<string, mixed> $tokenData */
        $tokenData = $response->json();

        $expiresIn = isset($tokenData['expires_in']) ? (int) $tokenData['expires_in'] : 3600;
        $tokenData['expires_at'] = now()->getTimestamp() + $expiresIn;

        Setting::set('google_photos.token', Crypt::encryptString(json_encode($tokenData, JSON_THROW_ON_ERROR)));

        if (isset($tokenData['access_token']) && is_string($tokenData['access_token'])) {
            $userinfoResponse = Http::withToken($tokenData['access_token'])->get(self::USERINFO_URL);
            if ($userinfoResponse->successful()) {
                /** @var array<string, mixed> $userinfo */
                $userinfo = $userinfoResponse->json();
                if (isset($userinfo['email']) && is_string($userinfo['email'])) {
                    Setting::set('google_photos.email', $userinfo['email']);
                }
            }
        }

        return $tokenData;
    }

    public function setManualToken(string $tokenOrJson): void
    {
        $trimmed = trim($tokenOrJson);
        $payload = null;

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded) && (isset($decoded['access_token']) || isset($decoded['refresh_token']))) {
                $payload = $decoded;
            }
        } catch (Throwable) {
            // Not valid JSON, treated as raw refresh token string
        }

        $payload ??= ['refresh_token' => $trimmed];

        if (isset($payload['expires_in']) && ! isset($payload['expires_at'])) {
            $payload['expires_at'] = now()->getTimestamp() + (int) $payload['expires_in'];
        }

        Setting::set('google_photos.token', Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)));

        if (isset($payload['access_token']) && is_string($payload['access_token'])) {
            try {
                $userinfoResponse = Http::withToken($payload['access_token'])->get(self::USERINFO_URL);
                if ($userinfoResponse->successful()) {
                    /** @var array<string, mixed> $userinfo */
                    $userinfo = $userinfoResponse->json();
                    if (isset($userinfo['email']) && is_string($userinfo['email'])) {
                        Setting::set('google_photos.email', $userinfo['email']);
                    }
                }
            } catch (Throwable) {
                // Ignore userinfo failure when setting manual token
            }
        }
    }

    public function getValidAccessToken(): ?string
    {
        /** @var ?string $encryptedToken */
        $encryptedToken = Setting::get('google_photos.token');

        if ($encryptedToken === null || $encryptedToken === '') {
            return null;
        }

        try {
            /** @var array<string, mixed> $tokenData */
            $tokenData = json_decode(Crypt::decryptString($encryptedToken), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        $now = now()->getTimestamp();
        $accessToken = isset($tokenData['access_token']) && is_string($tokenData['access_token']) ? $tokenData['access_token'] : null;
        $expiresAt = isset($tokenData['expires_at']) ? (int) $tokenData['expires_at'] : null;

        if ($accessToken !== null && $expiresAt !== null && $expiresAt > ($now + 60)) {
            return $accessToken;
        }

        $refreshToken = isset($tokenData['refresh_token']) && is_string($tokenData['refresh_token']) ? $tokenData['refresh_token'] : null;

        if ($refreshToken !== null && $refreshToken !== '') {
            return $this->refreshAccessToken($refreshToken, $tokenData);
        }

        return $accessToken;
    }

    /**
     * @param  array<string, mixed>  $existingTokenData
     */
    private function refreshAccessToken(string $refreshToken, array $existingTokenData): ?string
    {
        $clientId = $this->getClientId();
        $clientSecret = $this->getClientSecret();

        if ($clientId === null || $clientSecret === null) {
            return null;
        }

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful()) {
            return null;
        }

        /** @var array<string, mixed> $newData */
        $newData = $response->json();

        $freshAccessToken = isset($newData['access_token']) && is_string($newData['access_token']) ? $newData['access_token'] : null;
        if ($freshAccessToken === null) {
            return null;
        }

        $expiresIn = isset($newData['expires_in']) ? (int) $newData['expires_in'] : 3600;
        $existingTokenData['access_token'] = $freshAccessToken;
        $existingTokenData['expires_at'] = now()->getTimestamp() + $expiresIn;

        if (isset($newData['refresh_token']) && is_string($newData['refresh_token'])) {
            $existingTokenData['refresh_token'] = $newData['refresh_token'];
        }

        Setting::set('google_photos.token', Crypt::encryptString(json_encode($existingTokenData, JSON_THROW_ON_ERROR)));

        return $freshAccessToken;
    }

    public function getUserEmail(): ?string
    {
        /** @var ?string $email */
        $email = Setting::get('google_photos.email');

        return ($email !== null && $email !== '') ? $email : null;
    }

    public function isConnected(): bool
    {
        /** @var ?string $token */
        $token = Setting::get('google_photos.token');

        return $token !== null && $token !== '';
    }

    public function disconnect(): void
    {
        Setting::set('google_photos.token', null);
        Setting::set('google_photos.email', null);
    }

    /**
     * @return array{status: int, body: string, contentType: string}
     */
    public function downloadThumbnail(string $url, int $size): array
    {
        $token = $this->getValidAccessToken();

        if ($token === null) {
            return ['status' => 401, 'body' => 'Google Photos not connected', 'contentType' => 'text/plain'];
        }

        $downloadUrl = $url.'=w'.$size.'-h'.$size.'-c';

        $googleResponse = Http::withToken($token)
            ->timeout(15)
            ->get($downloadUrl);

        if (! $googleResponse->successful()) {
            return ['status' => $googleResponse->status(), 'body' => 'Failed to load thumbnail from Google', 'contentType' => 'text/plain'];
        }

        /** @var string $contentType */
        $contentType = $googleResponse->header('Content-Type') ?: 'image/jpeg';

        return ['status' => 200, 'body' => $googleResponse->body(), 'contentType' => $contentType];
    }

    /**
     * @return array{id: string, pickerUri: string, mediaItemsSet: bool, expireTime: ?string}
     */
    public function createSession(): array
    {
        $token = $this->getValidAccessToken();

        if ($token === null) {
            throw new RuntimeException('Google Photos is not connected or token expired.');
        }

        $response = Http::withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post(self::SESSIONS_URL, (object) []);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to create Google Photos picker session: '.$response->body());
        }

        /** @var array{id?: string, pickerUri?: string, mediaItemsSet?: bool, expireTime?: string} $data */
        $data = $response->json();

        return [
            'id' => $data['id'] ?? '',
            'pickerUri' => $data['pickerUri'] ?? '',
            'mediaItemsSet' => (bool) ($data['mediaItemsSet'] ?? false),
            'expireTime' => $data['expireTime'] ?? null,
        ];
    }

    /**
     * @return array{id: string, pickerUri: string, mediaItemsSet: bool, expireTime: ?string}
     */
    public function getSession(string $sessionId): array
    {
        $token = $this->getValidAccessToken();

        if ($token === null) {
            throw new RuntimeException('Google Photos is not connected or token expired.');
        }

        $response = Http::withToken($token)->get(self::SESSIONS_URL.'/'.$sessionId);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to get Google Photos picker session: '.$response->body());
        }

        /** @var array{id?: string, pickerUri?: string, mediaItemsSet?: bool, expireTime?: string} $data */
        $data = $response->json();

        return [
            'id' => $data['id'] ?? $sessionId,
            'pickerUri' => $data['pickerUri'] ?? '',
            'mediaItemsSet' => (bool) ($data['mediaItemsSet'] ?? false),
            'expireTime' => $data['expireTime'] ?? null,
        ];
    }

    /**
     * @return array{mediaItems: list<array<string, mixed>>, nextPageToken: ?string}
     */
    public function listLibraryMediaItems(?string $nextPageToken = null, int $pageSize = 50): array
    {
        return $this->fetchMediaItems(self::MEDIA_ITEMS_URL, [], $nextPageToken, $pageSize);
    }

    /**
     * @return array{mediaItems: list<array<string, mixed>>, nextPageToken: ?string}
     */
    public function listPickerMediaItems(string $sessionId, ?string $nextPageToken = null, int $pageSize = 50): array
    {
        return $this->fetchMediaItems(self::PICKER_MEDIA_ITEMS_URL, ['sessionId' => $sessionId], $nextPageToken, $pageSize);
    }

    /**
     * @param  array<string, mixed>  $extraQuery
     * @return array{mediaItems: list<array<string, mixed>>, nextPageToken: ?string}
     */
    private function fetchMediaItems(string $url, array $extraQuery, ?string $nextPageToken, int $pageSize): array
    {
        $token = $this->getValidAccessToken();

        if ($token === null) {
            throw new RuntimeException('Google Photos is not connected or token expired.');
        }

        $query = array_merge(['pageSize' => $pageSize], $extraQuery);
        if ($nextPageToken !== null && $nextPageToken !== '') {
            $query['pageToken'] = $nextPageToken;
        }

        $response = Http::withToken($token)->get($url, $query);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to fetch media items from Google Photos: '.$response->body());
        }

        /** @var array{mediaItems?: list<array<string, mixed>>, nextPageToken?: ?string} $data */
        $data = $response->json();

        $normalizedItems = [];
        foreach ($data['mediaItems'] ?? [] as $item) {
            $normalizedItems[] = $this->normalizeMediaItem($item);
        }

        return [
            'mediaItems' => $normalizedItems,
            'nextPageToken' => $data['nextPageToken'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{id: string, filename: string, baseUrl: string, mimeType: string, createTime: ?string}
     */
    private function normalizeMediaItem(array $item): array
    {
        $mediaFile = isset($item['mediaFile']) && is_array($item['mediaFile']) ? $item['mediaFile'] : [];

        $str = static fn (array $src, string $key): ?string => isset($src[$key]) && is_string($src[$key]) ? $src[$key] : null;

        return [
            'id' => $str($item, 'id') ?? '',
            'filename' => $str($mediaFile, 'filename') ?? $str($item, 'filename') ?? 'photo.jpg',
            'baseUrl' => $str($mediaFile, 'baseUrl') ?? $str($item, 'baseUrl') ?? '',
            'mimeType' => $str($mediaFile, 'mimeType') ?? $str($item, 'mimeType') ?? 'image/jpeg',
            'createTime' => $str($item, 'createTime'),
        ];
    }
}
