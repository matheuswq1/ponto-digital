<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditService
{
    public static function log(
        ?User $user,
        string $action,
        ?string $description = null,
        ?Model $subject = null,
        ?array $properties = null,
        ?int $companyId = null,
        ?Request $request = null,
    ): void {
        $req = $request ?? (function_exists('app') && app()->has('request') ? request() : null);

        AuditLog::create([
            'user_id'      => $user?->id,
            'action'       => $action,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id'   => $subject?->getKey(),
            'description'  => $description,
            'properties'   => $properties,
            'ip_address'   => $req?->ip(),
            'user_agent'   => $req?->userAgent() ? mb_substr($req->userAgent(), 0, 2000) : null,
            'company_id'   => $companyId,
        ]);
    }
}
