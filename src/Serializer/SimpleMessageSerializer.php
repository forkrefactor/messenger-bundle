<?php
declare(strict_types=1);

namespace PcComponentes\SymfonyMessengerBundle\Serializer;

use Assert\Assert;
use Forkrefactor\Ddd\Util\Message\Serialization\Exception\MessageClassNotFoundException;
use Forkrefactor\Ddd\Util\Message\Serialization\JsonApi\SimpleMessageJsonApiSerializable;
use Forkrefactor\Ddd\Util\Message\Serialization\JsonApi\SimpleMessageStream;
use Forkrefactor\Ddd\Util\Message\Serialization\JsonApi\SimpleMessageStreamDeserializer;
use Forkrefactor\DddLogging\DomainTrace\Tracker;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

final class SimpleMessageSerializer extends DomainSerializer
{
    private SimpleMessageJsonApiSerializable $serializer;
    private SimpleMessageStreamDeserializer $deserializer;

    public function __construct(
        Tracker $tracker,
        SimpleMessageJsonApiSerializable $serializer,
        SimpleMessageStreamDeserializer $deserializer
    ) {
        parent::__construct($tracker);

        $this->serializer = $serializer;
        $this->deserializer = $deserializer;
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        $message = $this->streamFromEncodedEnvelope($encodedEnvelope);

        try {
            $simpleMessage = $this->deserializer->unserialize($message);
        } catch (MessageClassNotFoundException $exception) {
            throw new MessageDecodingFailedException();
        }

        $this->obtainDomainTrace($simpleMessage, $encodedEnvelope);

        $retryCount = $this->extractHeaderRetryCount($encodedEnvelope);

        return (new Envelope($simpleMessage))->with(new RedeliveryStamp($retryCount));
    }

    public function encode(Envelope $envelope): array
    {
        return [
            'body' => $this->serializer->serialize(
                $envelope->getMessage(),
            ),
            'headers' => [
                'Content-Type' => 'application/json',
                'x-correlation-id' => $this->tracker()->correlationId(),
                'x-reply-to' => $this->tracker()->replyTo(),
                'x-retry-count' => $this->extractEnvelopeRetryCount($envelope),
            ],
        ];
    }

    private function streamFromEncodedEnvelope(array $encodedEnvelope): SimpleMessageStream
    {
        $body = \json_decode($encodedEnvelope['body'], true);
        $this->assertContent($body);
        $event = $body['data'];

        if (null === $event) {
            throw new \InvalidArgumentException('The body of message is null');
        }

        return new SimpleMessageStream(
            $event['message_id'],
            $event['type'],
            \json_encode($event['attributes']) ?: '',
        );
    }

    private function assertContent(?array $content)
    {
        Assert::lazy()->tryAll()
            ->that($content['data'], 'data')->isArray()
            ->keyExists('message_id')
            ->keyExists('type')
            ->keyExists('attributes')
            ->verifyNow()
        ;

        Assert::lazy()->tryAll()
            ->that($content['data']['message_id'], 'message_id')->uuid()
            ->that($content['data']['type'], 'type')->string()->notEmpty()
            ->that($content['data']['attributes'], 'attributes')->isArray()
            ->verifyNow()
        ;
    }
}
