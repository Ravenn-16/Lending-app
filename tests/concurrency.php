<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$connection = Illuminate\Support\Facades\DB::connection();
$service = app(App\Services\LoanService::class);
if (($argv[1] ?? '') === 'worker') {
    if (!preg_match('/^lending_test_[a-f0-9]{16}$/D', $connection->getDatabaseName())) exit('Invalid test database');
    echo "READY\n";
    flush();
    try {
        if ($argv[2] === 'void') $service->voidPayment(1, 'Concurrency test', 1);
        elseif ($argv[2] === 'duplicate') app(App\Services\SubmissionService::class)->run('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 1, 'payment', ['amount'=>'50'], fn () => $service->recordPayment(1, '50', date('Y-m-d'), 1)->id);
        else $service->recordPayment(1, '1000', date('Y-m-d'), 1);
        echo "OK\n";
    } catch (InvalidArgumentException $e) {
        echo "REJECTED\n";
    }
    exit;
}

$source = $connection->getPdo();
$name = 'lending_test_' . bin2hex(random_bytes(8));
$source->exec("CREATE DATABASE `$name`");
$previousName = getenv('DB_DATABASE');
try {
    $db = new PDO('mysql:host=' . $connection->getConfig('host') . ';dbname=' . $name . ';charset=utf8mb4', $connection->getConfig('username'), $connection->getConfig('password'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    foreach (['admins', 'borrowers', 'loans', 'payments', 'ledger_entries', 'submission_receipts'] as $table) {
        $definition = $source->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
        $db->exec($definition);
    }
    $db->exec("INSERT INTO admins (id, username, password_hash) VALUES (1, 'test_admin', 'fixture-only')");
    $db->exec("INSERT INTO borrowers (id, name) VALUES (1, 'Concurrent fixture')");
    $db->exec('INSERT INTO loans (id, borrower_id, principal, date_borrowed) VALUES (1, 1, 1000, CURRENT_DATE())');
    $db->exec('INSERT INTO payments (id, loan_id, amount, payment_date) VALUES (1, 1, 100, CURRENT_DATE())');
    $db->exec("INSERT INTO ledger_entries (loan_id, type, amount, effective_date, payment_id) VALUES
        (1, 'PRINCIPAL', 1000, CURRENT_DATE(), NULL), (1, 'INTEREST', 100, CURRENT_DATE(), NULL),
        (1, 'PAYMENT', 100, CURRENT_DATE(), 1)");
    putenv('DB_DATABASE=' . $name);
    foreach (['void', 'payment', 'duplicate'] as $action) {
        $db->beginTransaction();
        $db->query('SELECT id FROM loans WHERE id = 1 FOR UPDATE')->fetchAll();
        $workers = [];
        for ($i = 0; $i < 2; $i++) {
            $process = proc_open([PHP_BINARY, __FILE__, 'worker', $action],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($process)) throw new RuntimeException('Cannot launch worker');
            fclose($pipes[0]);
            stream_set_timeout($pipes[1], 15);
            $workers[] = [$process, $pipes];
        }
        foreach ($workers as [$process, $pipes]) {
            if (trim(fgets($pipes[1]) ?: '') !== 'READY') throw new RuntimeException('Worker not ready');
        }
        // Both workers now contend for the same parent loan lock.
        $db->commit();
        $outcomes = [];
        foreach ($workers as [$process, $pipes]) {
            $outcomes[] = trim(stream_get_contents($pipes[1]));
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            if (proc_close($process) !== 0) throw new RuntimeException($error);
        }
        sort($outcomes);
        if ($outcomes !== ($action === 'duplicate' ? ['OK', 'OK'] : ['OK', 'REJECTED'])) throw new RuntimeException('Concurrency failure: ' . json_encode($outcomes));
        echo "PASS: concurrent $action requests preserve a single financial write.\n";
    }
    if ((int)$db->query("SELECT COUNT(*) FROM ledger_entries WHERE type = 'PAYMENT_REVERSAL'")->fetchColumn() !== 1) {
        throw new RuntimeException('Duplicate reversal');
    }
    if ((int)$db->query("SELECT SUM(amount) FROM payments WHERE status = 'POSTED'")->fetchColumn() !== 1050) {
        throw new RuntimeException('Concurrent overpayment');
    }
} finally {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    putenv($previousName === false ? 'DB_DATABASE' : 'DB_DATABASE=' . $previousName);
    // Delete only the uniquely named database this invocation created.
    if (!preg_match('/^lending_test_[a-f0-9]{16}$/D', $name)) throw new RuntimeException('Unsafe cleanup name');
    $source->exec("DROP DATABASE `$name`");
}
