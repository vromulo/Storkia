<?php

namespace App\Console\Commands;

use App\Services\ReturnDeadlineService;
use Illuminate\Console\Command;

class ProcessReturnDeadlinesCommand extends Command
{
    protected $signature = 'returns:process-deadlines';

    protected $description = 'Auto-approve return requests the seller never answered, auto-confirm returned parcels the seller never confirmed, and handle return parcels that were never handed over.';

    public function __construct(protected ReturnDeadlineService $returnDeadlineService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $summary = $this->returnDeadlineService->processExpired();

        foreach ($summary['errors'] as $error) {
            $this->error("Return deadline failed: {$error}");
        }

        $this->info(
            "Auto-approved {$summary['seller_auto_approved']} unanswered request(s); "
            . "auto-confirmed {$summary['receipt_auto_confirmed']} returned parcel(s); "
            . "closed {$summary['handover_closed']} unclaimed return(s); "
            . "reminded Logistics on {$summary['handover_extended']} delayed pickup(s); "
            . "{$summary['refund_payouts_overdue']} refund payout(s) overdue."
        );

        return $summary['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}