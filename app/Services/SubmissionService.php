<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class SubmissionService
{
    /** Unique key reservation and financial write commit together, including retries. */
    public function run(string $key, int $adminId, string $operation, array $payload, callable $action): int
    {
        ksort($payload);
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        return DB::transaction(function () use ($key, $adminId, $operation, $hash, $action) {
            DB::table('submission_receipts')->insertOrIgnore(['key' => $key, 'admin_id' => $adminId, 'operation' => $operation,
                'payload_hash' => $hash, 'created_at' => now()]);
            $receipt = DB::table('submission_receipts')->where('key', $key)->lockForUpdate()->first();
            if (!$receipt || $receipt->admin_id !== $adminId || $receipt->operation !== $operation || !hash_equals($receipt->payload_hash, $hash)) {
                throw ValidationException::withMessages(['submission_key' => 'This form was already used with different details. Open a new form and try again.']);
            }
            if ($receipt->result_id !== null) return (int)$receipt->result_id;
            $id = (int)$action();
            DB::table('submission_receipts')->where('key', $key)->update(['result_id' => $id]);
            return $id;
        }, 3);
    }
}
