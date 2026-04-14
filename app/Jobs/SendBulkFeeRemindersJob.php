<?php

namespace App\Jobs;

use App\Models\Student;
use App\Models\WhatsAppLog;
use App\Models\SchoolSetting;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

use Spatie\Multitenancy\Jobs\NotTenantAware;

class SendBulkFeeRemindersJob implements ShouldQueue, NotTenantAware
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $studentIds;

    /**
     * Create a new job instance.
     */
    public function __construct(array $studentIds)
    {
        $this->studentIds = $studentIds;
    }

    /**
     * Execute the job.
     */
    public function handle(WhatsAppService $whatsappService): void
    {
        $today = now()->format('Y-m-d');
        $students = Student::with(['invoices' => function($q) use ($today) {
            $q->where('status', '!=', 'paid')
              ->where('due_date', '<', $today);
        }])->whereIn('id', $this->studentIds)->get();

        foreach ($students as $student) {
            $totalDue = $student->invoices->sum('net_amount');
            
            if ($totalDue <= 0) continue;

            $schoolSettings = SchoolSetting::first();
            $schoolName = $schoolSettings ? $schoolSettings->school_name : config('app.name', 'SchoolOS');
            
            $message = "Dear Parent,\n\nThis is a reminder from *{$schoolName}* regarding the outstanding fee of RS " . number_format($totalDue) . " for your child *{$student->name}* ({$student->roll_no}).\n\nPlease clear the dues as soon as possible to avoid any inconvenience.\n\nThank you.";

            try {
                $whatsappService->sendReminder($student, $message);
                
            } catch (\Exception $e) {
                Log::error("Failed to queue bulk reminder to student {$student->id}: " . $e->getMessage());
            }

            // Sleep for 2 seconds to pace the dispatching
            sleep(2);
        }
    }
}
