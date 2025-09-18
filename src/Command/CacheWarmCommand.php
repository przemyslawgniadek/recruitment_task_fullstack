<?php

declare(strict_types=1);

namespace App\Command;

use App\Infrastructure\Cache\CacheWarmer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use DateTimeImmutable;

/**
 * Console Command for Cache Warming
 * 
 * Provides CLI interface for warming exchange rates cache.
 * Can be used in deployment scripts, cron jobs, or manual operations.
 * 
 * Usage Examples:
 * - php bin/console cache:warm-fx                    # Warm all data
 * - php bin/console cache:warm-fx --current-only    # Only current rates
 * - php bin/console cache:warm-fx --days=7          # 7 days history
 * - php bin/console cache:warm-fx --date=2024-01-15 # Specific date
 */
#[AsCommand(
    name: 'cache:warm-fx',
    description: 'Warm exchange rates cache with current and historical data',
    aliases: ['fx:cache:warm']
)]
final class CacheWarmCommand extends Command
{
    public function __construct(
        private CacheWarmer $cacheWarmer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command warms the exchange rates cache with current and historical data from NBP API.')
            ->addOption(
                'current-only',
                'c',
                InputOption::VALUE_NONE,
                'Warm only current rates (skip historical data)'
            )
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_REQUIRED,
                'Number of historical days to warm (default: 14)',
                '14'
            )
            ->addOption(
                'date',
                null,
                InputOption::VALUE_REQUIRED,
                'Target date for warming (YYYY-MM-DD format, default: today)'
            )
            ->addOption(
                'fallback',
                'f',
                InputOption::VALUE_NONE,
                'Include business days fallback warming'
            )
            ->addOption(
                'status',
                's',
                InputOption::VALUE_NONE,
                'Show cache status instead of warming'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // Handle status option
        if ($input->getOption('status')) {
            return $this->showStatus($io);
        }
        
        $io->title('Exchange Rates Cache Warming');
        
        // Parse and validate options
        $targetDate = $this->parseTargetDate($input, $io);
        if ($targetDate === null) {
            return Command::FAILURE;
        }
        
        $days = (int) $input->getOption('days');
        $currentOnly = $input->getOption('current-only');
        $includeFallback = $input->getOption('fallback');
        
        $io->section('Configuration');
        $io->definitionList(
            ['Target Date' => $targetDate->format('Y-m-d (l)')],
            ['Current Rates' => 'Yes'],
            ['Historical Rates' => $currentOnly ? 'No (--current-only)' : "Yes ({$days} days)"],
            ['Business Days Fallback' => $includeFallback ? 'Yes' : 'No']
        );
        
        $startTime = microtime(true);
        $overallSuccess = true;
        
        // Step 1: Warm current rates
        $io->section('Warming Current Rates');
        $currentResults = $this->cacheWarmer->warmCurrentRates($targetDate);
        $this->displayCurrentRatesResults($io, $currentResults);
        
        if ($currentResults['all_rates']['status'] !== 'success') {
            $overallSuccess = false;
        }
        
        // Step 2: Warm historical rates (unless current-only)
        if (!$currentOnly) {
            $io->section('Warming Historical Rates');
            $historicalResults = $this->cacheWarmer->warmAllCurrencies($days, $targetDate);
            $this->displayHistoricalResults($io, $historicalResults);
            
            if ($historicalResults['summary']['operations_error'] > 0) {
                $overallSuccess = false;
            }
        }
        
        // Step 3: Warm business days fallback (if requested)
        if ($includeFallback) {
            $io->section('Warming Business Days Fallback');
            $fallbackResults = $this->cacheWarmer->warmBusinessDaysFallback($targetDate);
            $this->displayFallbackResults($io, $fallbackResults);
        }
        
        // Summary
        $endTime = microtime(true);
        $totalDuration = round($endTime - $startTime, 2);
        
        $io->section('Summary');
        $io->definitionList(
            ['Total Duration' => "{$totalDuration} seconds"],
            ['Overall Status' => $overallSuccess ? '<fg=green>SUCCESS</>' : '<fg=red>PARTIAL FAILURE</>']
        );
        
        if ($overallSuccess) {
            $io->success('Cache warming completed successfully!');
            return Command::SUCCESS;
        } else {
            $io->warning('Cache warming completed with some errors. Check the output above for details.');
            return Command::FAILURE;
        }
    }

    private function showStatus(SymfonyStyle $io): int
    {
        $io->title('Exchange Rates Cache Status');
        
        try {
            $status = $this->cacheWarmer->getWarmingStatus();
            
            $io->section('Current Rates');
            if ($status['current_rates']['available']) {
                $io->success(sprintf(
                    'Available: %d rates for %s',
                    $status['current_rates']['count'],
                    $status['current_rates']['date']
                ));
                
                if (!empty($status['current_rates']['currencies'])) {
                    $io->listing($status['current_rates']['currencies']);
                }
            } else {
                $io->error('Current rates not available');
                if (isset($status['current_rates']['error'])) {
                    $io->text('Error: ' . $status['current_rates']['error']);
                }
            }
            
            $io->section('Supported Currencies');
            $currencyTable = [];
            foreach ($status['supported_currencies'] as $currency) {
                $currencyTable[] = [
                    $currency['code'],
                    $currency['supports_buying'] ? 'Yes' : 'No',
                    $currency['buy_margin'] ?? 'N/A',
                    $currency['sell_margin']
                ];
            }
            
            $io->table(
                ['Currency', 'Supports Buying', 'Buy Margin', 'Sell Margin'],
                $currencyTable
            );
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error('Failed to get cache status: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function parseTargetDate(InputInterface $input, SymfonyStyle $io): ?DateTimeImmutable
    {
        $dateOption = $input->getOption('date');
        
        if ($dateOption === null) {
            return new DateTimeImmutable();
        }
        
        try {
            return new DateTimeImmutable($dateOption);
        } catch (\Exception $e) {
            $io->error(sprintf(
                'Invalid date format: %s. Please use YYYY-MM-DD format.',
                $dateOption
            ));
            return null;
        }
    }

    private function displayCurrentRatesResults(SymfonyStyle $io, array $results): void
    {
        $allRates = $results['all_rates'];
        
        switch ($allRates['status']) {
            case 'success':
                $io->success(sprintf(
                    'Successfully warmed %d current rates for %s',
                    $allRates['count'],
                    $allRates['date']
                ));
                
                if (!empty($allRates['currencies'])) {
                    $io->text('Currencies: ' . implode(', ', $allRates['currencies']));
                }
                break;
                
            case 'no_data':
                $io->warning(sprintf(
                    'No current rates available for %s',
                    $allRates['date']
                ));
                break;
                
            case 'error':
                $io->error(sprintf(
                    'Failed to warm current rates for %s: %s',
                    $allRates['date'],
                    $allRates['error']
                ));
                break;
        }
    }

    private function displayHistoricalResults(SymfonyStyle $io, array $results): void
    {
        $summary = $results['summary'];
        
        $io->definitionList(
            ['Duration' => "{$summary['duration_seconds']} seconds"],
            ['Operations' => "{$summary['operations_success']}/{$summary['operations_total']} successful"],
            ['Success Rate' => "{$summary['success_rate']}%"],
            ['Total Rates Cached' => $summary['total_rates_cached']],
            ['Currencies Processed' => $summary['currencies_processed']],
            ['History Days' => $summary['history_days']]
        );
        
        if ($summary['operations_error'] > 0) {
            $io->warning(sprintf(
                '%d operations failed. Details:',
                $summary['operations_error']
            ));
            
            foreach ($results['historical'] as $currency => $result) {
                if ($result['status'] === 'error') {
                    $io->text(sprintf(
                        '  - %s: %s',
                        $currency,
                        $result['error']
                    ));
                }
            }
        } else {
            $io->success('All historical rates warmed successfully!');
        }
    }

    private function displayFallbackResults(SymfonyStyle $io, array $results): void
    {
        $successCount = 0;
        $totalCount = count($results);
        
        foreach ($results as $date => $result) {
            if ($result['status'] === 'success' && $result['count'] > 0) {
                $successCount++;
            }
        }
        
        $io->text(sprintf(
            'Fallback warming: %d/%d dates have data available',
            $successCount,
            $totalCount
        ));
        
        if ($io->isVerbose()) {
            $fallbackTable = [];
            foreach ($results as $date => $result) {
                $fallbackTable[] = [
                    $date,
                    $result['day_name'],
                    $result['status'],
                    $result['count'] ?? 0,
                    $result['days_back']
                ];
            }
            
            $io->table(
                ['Date', 'Day', 'Status', 'Rates', 'Days Back'],
                $fallbackTable
            );
        }
    }
}
