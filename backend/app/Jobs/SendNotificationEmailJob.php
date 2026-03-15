<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;

class SendNotificationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * @param  User    $user      Recipient user
     * @param  string  $subject   Email subject line
     * @param  string  $template  Blade template name under emails/ (e.g. 'chat-notification')
     * @param  array   $data      Variables to pass to the template
     * @param  string  $fallbackBody  Plain-text fallback if template rendering fails
     */
    public function __construct(
        private readonly User $user,
        private readonly string $subject,
        private readonly string $template = '',
        private readonly array $data = [],
        private readonly string $fallbackBody = '',
    ) {
        $this->onQueue('emails');
    }

    /**
     * Send the notification email using a Blade template with plain-text fallback.
     */
    public function handle(): void
    {
        if (empty($this->user->email)) {
            Log::warning('Skipping email: no email address', ['user_id' => $this->user->id]);

            return;
        }

        $templateView = 'emails.' . $this->template;

        // Try to render the Blade template; fall back to plain text on failure
        if ($this->template && View::exists($templateView)) {
            try {
                Mail::send($templateView, $this->data, function ($mail) {
                    $mail->to($this->user->email, $this->user->full_name)
                        ->subject($this->subject);
                });
            } catch (\Throwable $e) {
                Log::warning('Blade template rendering failed, falling back to plain text', [
                    'template' => $this->template,
                    'error' => $e->getMessage(),
                ]);

                $this->sendPlainText();
            }
        } else {
            // No template specified or template not found — send plain text
            $this->sendPlainText();
        }

        Log::info('Notification email sent', [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'subject' => $this->subject,
            'template' => $this->template ?: 'plain-text',
        ]);
    }

    /**
     * Send a plain-text email using fallbackBody or a generated body from data.
     */
    private function sendPlainText(): void
    {
        $body = $this->fallbackBody;

        // Auto-generate plain text from data if no explicit fallback provided
        if (empty($body) && ! empty($this->data)) {
            $parts = [];
            if (! empty($this->data['recipientName'])) {
                $parts[] = $this->data['recipientName'] . ' 様';
            }
            if (! empty($this->data['title'])) {
                $parts[] = $this->data['title'];
            }
            if (! empty($this->data['body'])) {
                $parts[] = $this->data['body'];
            }
            if (! empty($this->data['messagePreview'])) {
                $parts[] = 'メッセージ: ' . $this->data['messagePreview'];
            }
            if (! empty($this->data['actionUrl'])) {
                $parts[] = 'URL: ' . $this->data['actionUrl'];
            }
            if (! empty($this->data['chatUrl'])) {
                $parts[] = 'URL: ' . $this->data['chatUrl'];
            }
            $body = implode("\n\n", $parts);
        }

        if (empty($body)) {
            $body = $this->subject;
        }

        Mail::raw($body, function ($mail) {
            $mail->to($this->user->email, $this->user->full_name)
                ->subject($this->subject);
        });
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendNotificationEmailJob failed', [
            'user_id' => $this->user->id,
            'subject' => $this->subject,
            'template' => $this->template,
            'error' => $exception->getMessage(),
        ]);
    }
}
