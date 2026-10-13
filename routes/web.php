<?php
use App\Http\Controllers\{AuthController, BorrowerController, DashboardController, LoanController, MoneyController, LoanApiController};
use Illuminate\Support\Facades\Route;

Route::pattern('borrower', '[0-9]+');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:login');
    Route::get('/login/code', [\App\Http\Controllers\LoginCodeController::class, 'show'])->name('login.code');
    Route::get('/login/email', [\App\Http\Controllers\LoginCodeController::class, 'emailForm'])->name('login.email');
    Route::post('/login/email', [\App\Http\Controllers\LoginCodeController::class, 'registerEmail'])->middleware('throttle:5,1')->block();
    Route::post('/login/code', [\App\Http\Controllers\LoginCodeController::class, 'verify'])->middleware('throttle:10,1')->block();
    Route::post('/login/code/resend', [\App\Http\Controllers\LoginCodeController::class, 'resend'])->middleware('throttle:5,1')->block()->name('login.code.resend');
    Route::post('/login/code/cancel', [\App\Http\Controllers\LoginCodeController::class, 'cancel'])->block()->name('login.code.cancel');
});
Route::middleware(['auth', 'auth.session', \App\Http\Middleware\RequireLoginCode::class])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/settings', [AuthController::class, 'settings'])->name('settings');
    Route::put('/settings/password', [AuthController::class, 'password'])->name('settings.password');
    Route::get('/api/addresses/cities', [\App\Http\Controllers\AddressController::class, 'cities'])->name('addresses.cities');
    Route::get('/api/addresses/barangays', [\App\Http\Controllers\AddressController::class, 'barangays'])->name('addresses.barangays');
    Route::get('/transactions', [\App\Http\Controllers\TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/reports', [\App\Http\Controllers\ReportController::class, 'index'])->name('reports.index');
    Route::resource('borrowers', BorrowerController::class);
    Route::get('/loans', [LoanController::class, 'index'])->name('loans.index');
    Route::get('/loans/{loan}', [LoanController::class, 'show'])->whereNumber('loan')->name('loans.show');
    Route::get('/money', [MoneyController::class, 'create'])->name('money.create');
    Route::post('/loans', [MoneyController::class, 'loan'])->name('loans.store');
    Route::post('/payments', [MoneyController::class, 'payment'])->name('payments.store');
    Route::get('/payments/{payment}/void', [MoneyController::class, 'confirmVoid'])->whereNumber('payment')->name('payments.void');
    Route::post('/payments/{payment}/void', [MoneyController::class, 'void'])->whereNumber('payment');
    Route::get('/api/borrowers/{borrower}/loans', [LoanApiController::class, 'loans'])->whereNumber('borrower')->name('api.borrower-loans');
    Route::get('/api/loans/{loan}', [LoanApiController::class, 'info'])->whereNumber('loan')->name('api.loan-info');
});

// Preserve old bookmarks while all forms use named Laravel routes and CSRF tokens.
Route::get('/{legacy}', function (string $legacy) {
    $id = (int) request('id', 0);
    $target = match ($legacy) {
        'index.php' => route('dashboard'), 'login.php' => route('login'), 'settings.php' => route('settings'),
        'borrowers/index.php' => route('borrowers.index'), 'borrowers/add.php' => route('borrowers.create'),
        'borrowers/view.php' => route('borrowers.show', $id), 'borrowers/edit.php' => route('borrowers.edit', $id),
        'borrowers/loan.php' => route('loans.show', $id),
        'borrowers/active.php' => route('loans.index', ['status' => 'ACTIVE']),
        'borrowers/due_soon.php' => route('loans.index', ['status' => 'DUE_SOON']),
        'borrowers/overdue.php' => route('loans.index', ['status' => 'OVERDUE']),
        'borrowers/paid.php' => route('loans.index', ['status' => 'FULLY_PAID']),
        'payments/add.php' => route('money.create', request()->only(['borrower_id', 'loan_id', 'mode'])),
        'payments/void.php' => route('payments.void', $id),
        default => abort(404),
    };
    return redirect($target);
})->where('legacy', '.*\.php');
