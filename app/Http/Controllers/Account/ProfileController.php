<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Models\SocialAccount;
use App\Support\Images\SquareWebpImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user()->load('socialAccounts');

        return view('account.profile', [
            'user' => $user,
            'googleAccount' => $user->socialAccounts
                ->firstWhere('provider', SocialAccount::PROVIDER_GOOGLE),
        ]);
    }

    public function update(
        UpdateProfileRequest $request,
        SquareWebpImage $imageStorage
    ): RedirectResponse {
        $user = $request->user();
        $validated = $request->validated();
        $emailChanged = $user->email !== $validated['email'];
        $oldAvatarPath = $user->avatar_path;
        $newAvatarPath = null;
        $replaceAvatar = $request->filled('cropped_avatar') || $request->hasFile('avatar');
        $removeAvatar = $request->boolean('remove_avatar') && ! $replaceAvatar;

        if ($replaceAvatar) {
            $contents = $request->filled('cropped_avatar')
                ? base64_decode(Str::after($request->string('cropped_avatar')->toString(), ','), true)
                : file_get_contents($request->file('avatar')->getRealPath());

            $newAvatarPath = $imageStorage->store(
                (string) $contents,
                'avatars',
                $user->id.'-'.$validated['name']
            );
        }

        try {
            DB::transaction(function () use (
                $user,
                $validated,
                $replaceAvatar,
                $removeAvatar,
                $newAvatarPath
            ): void {
                $user->fill([
                    'name' => $validated['name'],
                    'phone' => $validated['phone'],
                    'email' => $validated['email'],
                ]);

                if ($replaceAvatar || $removeAvatar) {
                    $user->avatar_path = $newAvatarPath;
                }

                $user->save();
            });
        } catch (Throwable $exception) {
            $imageStorage->delete($newAvatarPath);

            throw $exception;
        }

        if (($replaceAvatar || $removeAvatar) && $oldAvatarPath !== $newAvatarPath) {
            $imageStorage->delete($oldAvatarPath);
        }

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return redirect()
            ->route('account.profile')
            ->with('status', $emailChanged
                ? 'profile-updated-verification-required'
                : 'profile-updated');
    }
}
