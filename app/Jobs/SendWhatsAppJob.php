<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use App\Models\WhatsAppLog;

use Stancl\Tenancy\Contracts\TenantAwareJob;

class SendWhatsAppJob implements ShouldQueue, TenantAwareJob {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $logId;
    public $phone;
    public $message;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 30;

    public function __construct($logId, $phone, $message) {
        $this->logId = $logId;
        $this->phone = $phone;
        $this->message = $message;
    }

    public function handle(): void {
        $log = WhatsAppLog::find($this->logId);
        if (!$log) return;

        $nodeUrl = env('WHATSAPP_NODE_URL', 'http://localhost:3001');

        try {
            $response = Http::post("{$nodeUrl}/send", [
                'phone' => $this->phone,
                'message' => $this->message,
                'tenant_id' => tenant('id'),
            ]);

            if ($response->successful() && $response->json('success')) {
                $log->update([
                    'status' => 'sent',
                    'node_message_id' => $response->json('messageId'),
                    'sent_at' => now(),
                ]);
            } else {
                $error = $response->json('error') ?? 'Unknown error from Node API';
                $log->update(['error_message' => $error]);

                // Throw exception to trigger retry if we have attempts left
                if ($this->attempts() < $this->tries) {
                    throw new \Exception("WhatsApp Send Failed: " . $error);
                } else {
                    $log->update(['status' => 'failed']);
                }
            }
        } catch (\Exception $e) {
            $log->update(['error_message' => $e->getMessage()]);
            
            if ($this->attempts() < $this->tries) {
                throw $e;
            } else {
                $log->update(['status' => 'failed']);
            }
        }
    }
}
