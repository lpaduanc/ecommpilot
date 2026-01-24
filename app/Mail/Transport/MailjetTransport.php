<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Log;
use Mailjet\Client;
use Mailjet\Resources;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

class MailjetTransport extends AbstractTransport
{
    private Client $client;

    private int $maxRetries = 3;

    private array $retryDelays = [5, 15, 30];

    /**
     * Safe log to mail channel - silently ignores permission errors.
     */
    private function safeMailLog(string $level, string $message, array $context = []): void
    {
        try {
            Log::channel('mail')->{$level}($message, $context);
        } catch (\Throwable) {
            // Fallback to default log channel if mail channel fails
            try {
                Log::{$level}('[mail] '.$message, $context);
            } catch (\Throwable) {
                // Silently ignore
            }
        }
    }

    public function __construct(
        string $apiKey,
        string $secretKey,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($dispatcher, $logger);

        $this->client = new Client($apiKey, $secretKey, true, ['version' => 'v3.1']);
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $from = $email->getFrom()[0] ?? null;
        if (! $from) {
            throw new \RuntimeException('No sender address specified');
        }

        $toRecipients = array_map(
            fn (Address $addr) => [
                'Email' => $addr->getAddress(),
                'Name' => $addr->getName() ?: '',
            ],
            $email->getTo()
        );

        $body = [
            'Messages' => [
                [
                    'From' => [
                        'Email' => $from->getAddress(),
                        'Name' => $from->getName() ?: '',
                    ],
                    'To' => $toRecipients,
                    'Subject' => $email->getSubject() ?? '',
                    'TextPart' => $email->getTextBody() ?: '',
                    'HTMLPart' => $email->getHtmlBody() ?: '',
                ],
            ],
        ];

        // Add CC if present
        if ($email->getCc()) {
            $body['Messages'][0]['Cc'] = array_map(
                fn (Address $addr) => [
                    'Email' => $addr->getAddress(),
                    'Name' => $addr->getName() ?: '',
                ],
                $email->getCc()
            );
        }

        // Add BCC if present
        if ($email->getBcc()) {
            $body['Messages'][0]['Bcc'] = array_map(
                fn (Address $addr) => [
                    'Email' => $addr->getAddress(),
                    'Name' => $addr->getName() ?: '',
                ],
                $email->getBcc()
            );
        }

        // Add Reply-To if present
        if ($email->getReplyTo()) {
            $replyTo = $email->getReplyTo()[0] ?? null;
            if ($replyTo) {
                $body['Messages'][0]['ReplyTo'] = [
                    'Email' => $replyTo->getAddress(),
                    'Name' => $replyTo->getName() ?: '',
                ];
            }
        }

        $this->sendWithRetry($body);
    }

    private function sendWithRetry(array $body): void
    {
        $lastException = null;

        for ($attempt = 0; $attempt < $this->maxRetries; $attempt++) {
            try {
                $response = $this->client->post(Resources::$Email, ['body' => $body]);

                if ($response->success()) {
                    $this->safeMailLog('info', 'Mailjet email sent successfully', [
                        'to' => $body['Messages'][0]['To'][0]['Email'] ?? 'unknown',
                        'subject' => $body['Messages'][0]['Subject'] ?? 'unknown',
                    ]);

                    return;
                }

                $errorData = $response->getData();
                $statusCode = $response->getStatus();

                // Don't retry on client errors (4xx) except rate limiting (429)
                if ($statusCode >= 400 && $statusCode < 500 && $statusCode !== 429) {
                    throw new \RuntimeException(
                        'Mailjet API error: '.json_encode($errorData),
                        $statusCode
                    );
                }

                $lastException = new \RuntimeException(
                    'Mailjet API error: '.json_encode($errorData),
                    $statusCode
                );

            } catch (\Exception $e) {
                $lastException = $e;

                $this->safeMailLog('warning', 'Mailjet send attempt failed', [
                    'attempt' => $attempt + 1,
                    'error' => $e->getMessage(),
                ]);
            }

            // Wait before retry
            if ($attempt < $this->maxRetries - 1) {
                sleep($this->retryDelays[$attempt] ?? 30);
            }
        }

        $this->safeMailLog('error', 'Mailjet send failed after all retries', [
            'to' => $body['Messages'][0]['To'][0]['Email'] ?? 'unknown',
            'error' => $lastException?->getMessage(),
        ]);

        throw $lastException ?? new \RuntimeException('Failed to send email via Mailjet');
    }

    public function __toString(): string
    {
        return 'mailjet';
    }
}
