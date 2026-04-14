<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use App\Models\WhatsAppLog;

use Spatie\Multitenancy\Jobs\NotTenantAware;

class SendWhatsAppJob implements ShouldQueue, NotTenantAware {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $logId;
    public $phone;
    public $message;

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
            ]);

            if ($response->successful() && $response->json('success')) {
                $log->update([
                    'status' => 'sent',
                    'node_message_id' => $response->json('messageId'),
                    'sent_at' => now(),
                ]);
            } else {
                $log->update([
                    'status' => 'failed',
                    'error_message' => $response->json('error') ?? 'Unknown error from Node API',
                ]);
            }
        } catch (\Exception $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
