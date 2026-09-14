<?php

namespace App\Health\Checks;

use Illuminate\Support\Facades\DB;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class DatabaseLatencyCheck extends Check
{
    public ?int $warningThresholdMs = 50;
    public ?int $criticalThresholdMs = 150;

    public function run(): Result
    {
        $result = Result::make();

        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $latency = round((microtime(true) - $start) * 1000, 2);

            $result->shortSummary("{$latency} ms")
                ->meta(['latency_ms' => $latency]);

            if ($latency >= $this->criticalThresholdMs) {
                return $result->failed("Latency sangat tinggi: {$latency} ms (Batas: {$this->criticalThresholdMs} ms)");
            }

            if ($latency >= $this->warningThresholdMs) {
                return $result->warning("Latency agak lambat: {$latency} ms (Batas: {$this->warningThresholdMs} ms)");
            }

            return $result->ok("Latency optimal ({$latency} ms)");
        } catch (\Throwable $e) {
            return $result->failed("Koneksi Database Gagal: " . $e->getMessage());
        }
    }
}
