<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Google\GooglePhotosService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use MoonShine\Support\Enums\ToastType;
use Throwable;

class GooglePhotosAuthController extends Controller
{
    public function redirect(GooglePhotosService $service): RedirectResponse
    {
        try {
            $url = $service->getAuthUrl();

            return redirect()->away($url);
        } catch (Throwable $throwable) {
            toast($throwable->getMessage(), ToastType::ERROR);

            return redirect()->to('/admin/page/feature-settings-page');
        }
    }

    public function callback(Request $request, GooglePhotosService $service): RedirectResponse
    {
        $code = (string) $request->input('code');

        if ($code === '') {
            toast('Authorization code missing from Google callback.', ToastType::ERROR);

            return redirect()->to('/admin/page/feature-settings-page');
        }

        try {
            $service->handleCallback($code);
            toast('Google Photos account connected successfully', ToastType::SUCCESS);
        } catch (Throwable $throwable) {
            toast('Failed to connect Google Photos: '.$throwable->getMessage(), ToastType::ERROR);
        }

        return redirect()->to('/admin/page/feature-settings-page');
    }

    public function disconnect(GooglePhotosService $service): RedirectResponse
    {
        $service->disconnect();
        toast('Google Photos disconnected successfully', ToastType::SUCCESS);

        return redirect()->to('/admin/page/feature-settings-page');
    }
}
