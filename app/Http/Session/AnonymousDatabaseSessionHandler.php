<?php

namespace App\Http\Session;

use Illuminate\Session\DatabaseSessionHandler;

/**
 * Database session handler that never links a session to a person.
 *
 * The framework handler writes `user_id` whenever the Guard contract is bound,
 * which is always the case; the `sessions` table has no such column.
 */
class AnonymousDatabaseSessionHandler extends DatabaseSessionHandler
{
    /**
     * Leave the payload without any user information.
     *
     * @param  array<string, mixed>  $payload
     * @return $this
     */
    protected function addUserInformation(&$payload)
    {
        return $this;
    }
}
