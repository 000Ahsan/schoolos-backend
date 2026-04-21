<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MigrateToLedgerSystem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-to-ledger-system';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refactor existing fee invoices and payments to the new ledger-based system';

    public function handle()
    {
        $this->info('Starting migration to Ledger-based Fee System...');

        \DB::transaction(function () {
            // 1. Clean up Fee Invoices: Remove arrears from net_amount
            $this->info('Cleaning Fee Invoices...');
            $invoices = \App\Models\FeeInvoice::all();
            foreach ($invoices as $invoice) {
                // If it's carried_forward, it might have been an old invoice that was "moved"
                // We map status back to pending/paid based on its own debt
                if ($invoice->status === 'carried_forward') {
                    $invoice->status = 'pending';
                }

                // If net_amount currently includes arrears, we MUST subtract them.
                // Based on FeeService: net_amount = currentCharges + additionalCharges + arrears - discount + fine
                // To get clean monthly charge: net_amount = net_amount - arrears
                if ($invoice->arrears > 0) {
                    $invoice->net_amount = (float)$invoice->net_amount - (float)$invoice->arrears;
                }
                
                $invoice->save();
            }

            // 2. Populate student_id for all payments
            $this->info('Updating Fee Payments with student_id...');
            $payments = \App\Models\FeePayment::all();
            foreach ($payments as $payment) {
                // Historically, invoice_id was mandatory. We use it to find the student.
                if ($payment->invoice_id && !$payment->student_id) {
                    $invoice = \App\Models\FeeInvoice::find($payment->invoice_id);
                    if ($invoice) {
                        $payment->student_id = $invoice->student_id;
                        $payment->save();

                        // 3. Create initial allocation logic: 
                        // The user said: "Allocate FULL amount to its linked invoice"
                        \App\Models\FeePaymentAllocation::updateOrCreate(
                            [
                                'payment_id' => $payment->id,
                                'invoice_id' => $payment->invoice_id,
                            ],
                            [
                                'allocated_amount' => $payment->total_amount,
                            ]
                        );
                    }
                }
            }

            // 4. Recalculate all Invoice Statuses based on allocations
            $this->info('Recalculating Invoice Statuses...');
            foreach ($invoices as $invoice) {
                $totalAllocated = \App\Models\FeePaymentAllocation::where('invoice_id', $invoice->id)->sum('allocated_amount');
                
                if ($totalAllocated >= $invoice->net_amount) {
                    $invoice->status = 'paid';
                } elseif ($totalAllocated > 0) {
                    $invoice->status = 'partial';
                } else {
                    $invoice->status = 'pending';
                }
                $invoice->save();
            }
        });

        $this->info('Migration completed successfully!');
    }
}
