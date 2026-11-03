<?php

namespace App\Http\Middleware;

use App\Models\Applicant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SetDatabaseUserContext
{
    /**
     * Handle an incoming request.
     * Sets row-level database connection session variables for PostgreSQL and MySQL
     * to enforce database-level row access policies.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('staff')
            ?? $request->user('api')
            ?? $request->user('applicant-api')
            ?? $request->user('applicant')
            ?? $request->user();

        $this->setDatabaseSessionContext($user, $request);

        return $next($request);
    }

    /**
     * Set session-scoped database variables on the active database connection.
     */
    protected function setDatabaseSessionContext(null|User|Applicant $user, Request $request): void
    {
        try {
            $connection = DB::connection();
            $driver = $connection->getDriverName();

            $isBypass = 'false';
            if ($request->is('api/v1/webhooks/*') || $request->is('webhooks/*')) {
                $isBypass = 'true';
            }

            $userId = '0';
            $userRole = 'guest';
            $applicantId = '0';
            $zoneId = '0';
            $commandId = '0';

            if ($user instanceof Applicant) {
                $userId = (string) $user->id;
                $userRole = 'applicant';
                $applicantId = (string) $user->id;
            } elseif ($user instanceof User) {
                $userId = (string) $user->id;
                $userRole = $user->role instanceof \BackedEnum ? $user->role->value : (string) $user->role;
                $zoneId = (string) ($user->zone_id ?? '0');
                $commandId = (string) ($user->command_id ?? '0');
            }

            if ($driver === 'pgsql') {
                $connection->statement("SET LOCAL app.current_user_id = '{$userId}';");
                $connection->statement("SET LOCAL app.current_user_role = '{$userRole}';");
                $connection->statement("SET LOCAL app.current_applicant_id = '{$applicantId}';");
                $connection->statement("SET LOCAL app.current_zone_id = '{$zoneId}';");
                $connection->statement("SET LOCAL app.current_command_id = '{$commandId}';");
                $connection->statement("SET LOCAL app.is_bypass_rls = '{$isBypass}';");
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                $connection->statement(
                    "SET @app_current_user_id = ?, @app_current_user_role = ?, @app_current_applicant_id = ?, @app_current_zone_id = ?, @app_current_command_id = ?, @app_is_bypass_rls = ?",
                    [$userId, $userRole, $applicantId, $zoneId, $commandId, $isBypass]
                );
            }
        } catch (\Throwable $e) {
            // Silently continue if the database connection doesn't support session variables (e.g. basic SQLite in memory)
        }
    }
}
