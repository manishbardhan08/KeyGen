<?php

namespace App\Traits;

use App\Models\UserModel;

/**
 * Shared "change my own password" flow for the Owner, Admin and Seller profile
 * screens.
 *
 * Each controller previously re-implemented this with slightly different
 * validation. Routing it through one place keeps the rules (and the error
 * copy) identical everywhere, adds a null guard on the loaded user, and stops
 * a user from "changing" to the same password.
 */
trait PasswordTrait
{
    /**
     * Validate the current/new/confirm fields on $this->request and, when they
     * are valid, persist a fresh bcrypt hash for $uid.
     *
     * @return string|null Human-readable error, or null on success.
     */
    protected function applyPasswordChange(int $uid, int $minLength = 8): ?string
    {
        $current = (string) $this->request->getPost('current_password');
        $new     = (string) $this->request->getPost('new_password');
        $confirm = (string) $this->request->getPost('confirm_password');

        $m = new UserModel();
        $u = $m->find($uid);
        if (! $u) {
            return 'Account not found.';
        }

        if (! password_verify($current, (string) $u['password'])) {
            return 'Current password is wrong.';
        }
        if ($new !== $confirm) {
            return 'New passwords do not match.';
        }
        if (strlen($new) < $minLength) {
            return "New password must be at least {$minLength} characters.";
        }
        if (password_verify($new, (string) $u['password'])) {
            return 'New password must be different from the current one.';
        }

        $m->update($u['id'], ['password' => password_hash($new, PASSWORD_BCRYPT)]);

        return null;
    }
}
