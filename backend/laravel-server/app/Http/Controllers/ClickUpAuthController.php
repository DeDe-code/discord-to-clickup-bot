<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use App\Services\ClickUpService;

class ClickUpAuthController extends Controller
{
    public function __construct(
        private ClickUpService $clickUpService
    ) {}

    /**
     * Redirect to ClickUp OAuth
     */
    public function redirectToClickUp(): RedirectResponse
    {
        $redirectUri = config('app.url') . '/auth/clickup/callback';
        $clientId = config('services.clickup.client_id');
        
        $clickupAuthUrl = "https://app.clickup.com/api?client_id={$clientId}&redirect_uri={$redirectUri}";
        
        return redirect($clickupAuthUrl);
    }

    /**
     * Handle ClickUp OAuth callback
     */
    public function handleCallback(Request $request): JsonResponse
    {
        $code = $request->query('code');
        
        if (!$code) {
            return response()->json(['error' => 'Authorization code not provided'], 400);
        }

        try {
            $token = $this->clickUpService->exchangeCodeForToken($code);
            
            if ($token) {
                return response()->json([
                    'message' => '✅ ClickUp auth successful. You can now close this window.',
                    'success' => true
                ]);
            } else {
                return response()->json(['error' => 'Failed to obtain access token'], 500);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Auth failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get ClickUp authentication status
     */
    public function getAuthStatus(): JsonResponse
    {
        return response()->json([
            'authenticated' => $this->clickUpService->hasValidToken(),
            'token_exists' => $this->clickUpService->tokenExists(),
        ]);
    }

    /**
     * Revoke ClickUp token
     */
    public function revokeToken(): JsonResponse
    {
        $this->clickUpService->revokeToken();
        
        return response()->json([
            'message' => 'Token revoked successfully'
        ]);
    }
}
