<?php

namespace BSPDX\Keystone\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccountDeletionController
{
    /**
     * Delete the authenticated user's account.
     *
     * Revokes all Sanctum tokens, invalidates the session, and deletes the
     * user record. Passkeys cascade via FK. 2FA columns go with the row.
     */
    public function destroy(Request $request): RedirectResponse|JsonResponse
    {
        abort_if(! config('keystone.features.account_deletion', false), 404);

        $user = $request->user();

        $user->tokens()->delete();

        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $user->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Account deleted successfully.']);
        }

        return redirect('/')->with('status', 'account-deleted');
    }
}
