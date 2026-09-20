<?php

namespace App\Services;

use App\Models\Document;
use Aws\Sqs\SqsClient;
use RuntimeException;

class DocumentQueue
{
    public function publish(Document $document): void
    {
        $queueUrl = config('services.sqs.queue_url');

        if (! is_string($queueUrl) || $queueUrl === '') {
            throw new RuntimeException('AWS_SQS_QUEUE_URL não configurada.');
        }

        if (! is_string($document->storage_path) || $document->storage_path === '') {
            throw new RuntimeException('Documento sem chave S3 para publicar na fila.');
        }

        $this->client()->sendMessage([
            'QueueUrl' => $queueUrl,
            'MessageBody' => json_encode([
                'document_id' => $document->id,
                's3_key' => $document->storage_path,
            ], JSON_THROW_ON_ERROR),
        ]);
    }

    private function client(): SqsClient
    {
        return new SqsClient([
            'version' => 'latest',
            'region' => config('services.sqs.region'),
            'credentials' => [
                'key' => config('services.sqs.key'),
                'secret' => config('services.sqs.secret'),
            ],
        ]);
    }
}
