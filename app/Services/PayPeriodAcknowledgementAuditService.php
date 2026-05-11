<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeePayPeriodAcknowledgement;
use App\Models\PayPeriodAckAuditEvent;
use App\Models\PayPeriodClosure;
use Illuminate\Http\Request;

/**
 * Registo só de escrita do aceite / contestação com snapshot hash SHA-256 do espelho visto.
 */
class PayPeriodAcknowledgementAuditService
{
    public function __construct(
        private readonly PayPeriodMirrorPayloadService $mirrorPayloadService,
    ) {}

    /**
     * @param  array<string, mixed>|null  $clientMeta  Normalizado (apenas chaves permitidas).
     * @return array{snapshot_hash: string, audit_event_id: int}
     */
    public function recordDecision(
        Request $request,
        EmployeePayPeriodAcknowledgement $ack,
        PayPeriodClosure $closure,
        Employee $employee,
        string $decision,
        ?string $employeeNotes,
        ?array $clientMeta,
    ): array {
        $termsVersion = (string) config('pay_mirror.terms_version', 'v1');

        $mirror = $this->mirrorPayloadService->buildMirrorPayload(
            $employee,
            $closure,
            $ack,
            $request,
        );

        $canonicalForHash = [
            'schema_version' => 1,
            'terms_version' => $termsVersion,
            'pay_period_acknowledgement_id' => $ack->id,
            'pay_period_closure_id' => $closure->id,
            'employee_id' => $employee->id,
            'decision' => $decision,
            'employee_notes' => $employeeNotes,
            'mirror' => $mirror,
        ];

        $canonicalEncoded = json_encode(
            $this->canonicalizeForHash($canonicalForHash),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        $hash = hash('sha256', $canonicalEncoded);

        $storedEnvelope = array_merge($canonicalForHash, [
            'snapshot_sha256' => $hash,
            'authenticated_user_id' => $request->user()?->id,
            'server_recorded_at_utc' => now()->utc()->toIso8601String(),
            'server_timezone' => config('app.timezone', 'UTC'),
        ]);

        $event = PayPeriodAckAuditEvent::query()->create([
            'pay_period_acknowledgement_id' => $ack->id,
            'decision' => $decision,
            'snapshot_hash' => $hash,
            'snapshot_json' => json_encode(
                $storedEnvelope,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'client_meta' => $clientMeta,
            'terms_version' => $termsVersion,
            'recorded_at' => now(),
        ]);

        return [
            'snapshot_hash' => $hash,
            'audit_event_id' => $event->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $clientMetaRaw
     * @return array<string, string>|null
     */
    public static function normalizeClientMeta(?array $clientMetaRaw): ?array
    {
        if ($clientMetaRaw === null || $clientMetaRaw === []) {
            return null;
        }

        $allowed = ['app_version', 'build_number', 'platform', 'device_id', 'locale'];

        $out = [];
        foreach ($allowed as $key) {
            if (! array_key_exists($key, $clientMetaRaw)) {
                continue;
            }
            $val = $clientMetaRaw[$key];
            if ($val === null) {
                continue;
            }
            $str = is_string($val) ? $val : (string) $val;
            $str = trim($str);
            if ($str === '') {
                continue;
            }
            $out[$key] = mb_substr($str, 0, 256);
        }

        return $out === [] ? null : $out;
    }

    /**
     * Ordena chaves em todos os níveis (arrays associativos) para hash estável.
     */
    private function canonicalizeForHash(mixed $data): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        $isAssoc = array_keys($data) !== range(0, count($data) - 1);

        if ($isAssoc) {
            ksort($data);
        }

        foreach ($data as $key => $value) {
            $data[$key] = $this->canonicalizeForHash($value);
        }

        return $data;
    }
}
