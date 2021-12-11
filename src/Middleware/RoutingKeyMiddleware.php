<?php
declare(strict_types=1);

namespace PcComponentes\SymfonyMessengerBundle\Middleware;

use Forkrefactor\Ddd\Util\Message\Message;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;

final class RoutingKeyMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $this->messageFromEnvelope($envelope);

        $envelope = $envelope->with(
            new AmqpStamp(
                $message::messageName(),
            ),
        );

        return $stack->next()->handle($envelope, $stack);
    }

    private function messageFromEnvelope(Envelope $envelope): Message
    {
        return $envelope->getMessage();
    }
}
