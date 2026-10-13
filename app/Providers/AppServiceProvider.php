<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\{Blade, RateLimiter};
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        \Illuminate\Support\Facades\View::composer('partials.messages', function ($view) {
            $dueSoonCount = 0;
            if (auth()->check()) {
                $calculator = app(\App\Services\LoanCalculator::class);
                \App\Models\Loan::with('ledgerEntries')->chunkById(200, function ($loans) use ($calculator, &$dueSoonCount) {
                    foreach ($loans as $loan) {
                        if ($calculator->summary($loan)['status'] === 'DUE_SOON') $dueSoonCount++;
                    }
                });
            }
            $view->with('dueSoonCount', $dueSoonCount);
        });
        Blade::directive('money', fn ($expression) => "<?php echo html_entity_decode('&#8369;', ENT_QUOTES, 'UTF-8').number_format((float)($expression), 2); ?>");
        RateLimiter::for('login', fn (Request $request) => Limit::perMinutes(15, 20)->by($request->ip()));
    }
}
