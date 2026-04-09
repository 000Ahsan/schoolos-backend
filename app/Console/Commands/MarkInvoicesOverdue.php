<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FeeInvoice;

class MarkInvoicesOverdue extends Command
{
    protected $signature = 'invoices:mark-overdue';
    protected $description = 'Marks pending invoices as overdue if past due date';

    public function handle()
    {
        $updated = FeeInvoice::where('status', 'pending')
            ->whereDate('due_date', '<', today())
            ->update(['status' => 'overdue']);

        $this->info("Marked {$updated} invoices as overdue.");
    }
}
