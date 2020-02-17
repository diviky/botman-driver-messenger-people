<?php

namespace BotMan\Drivers\MessengerPeople;

use BotMan\BotMan\Drivers\HttpDriver;
use BotMan\BotMan\Interfaces\VerifiesService;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\BotMan\Messages\Outgoing\OutgoingMessage;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\BotMan\Users\User;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MessengerPeopleDriver extends HttpDriver implements VerifiesService
{
    const DRIVER_NAME = 'MessengerPeople';
    const API_URL     = 'https://api.messengerpeople.dev';

    /**
     * @param Request $request
     */
    public function buildPayload(Request $request)
    {
        $this->payload = new ParameterBag((array) json_decode($request->getContent(), true));
        $this->event   = Collection::make($this->payload->all());
        $this->config  = Collection::make($this->config->get('messengerpeople'));
    }

    /**
     * Determine if the request is for this driver.
     *
     * @return bool
     */
    public function matchesRequest()
    {
        return (($this->event->get('messenger_id') && $this->event->get('outgoing') === false)
            || $this->event->get('challenge'));
    }

    /**
     * @param  \BotMan\BotMan\Messages\Incoming\IncomingMessage $message
     * @return \BotMan\BotMan\Messages\Incoming\Answer
     */
    public function getConversationAnswer(IncomingMessage $message)
    {
        return Answer::create($message->getText())->setMessage($message);
    }

    /**
     * Retrieve the chat message.
     *
     * @return array
     */
    public function getMessages()
    {
        return [
            new IncomingMessage(
                $this->event->get('payload')['text'],
                $this->event->get('sender'),
                $this->event->get('recipient'),
                $this->payload
            ),
        ];
    }

    /**
     * @return bool
     */
    public function isBot()
    {
        return false;
    }

    /**
     * @param IncomingMessage $message
     * @return \BotMan\BotMan\Users\User
     */
    public function getUser(IncomingMessage $message)
    {
        $payload = Collection::make($message->getPayload()->get('playload'));
        $user    = Collection::make($payload->get('user'));

        return new User(
            $user->get('id'),
            $user->get('name'),
            null,
            $user->get('id'),
            $user
        );
    }

    public function getAccessToken()
    {
        $response = $this->http->post('https://auth.messengerpeople.dev/token', [], [
            'client_id'     => $this->config->get('client_id'),
            'client_secret' => $this->config->get('client_secret'),
            'grant_type'    => 'client_credentials',
            'scope'         => 'messages:send messages:read messages:delete media:create',
        ]);

        $responseData = json_decode($response->getContent());

        return $responseData->access_token;
    }

    /**
     * @param Request $request
     * @return null|Response
     */
    public function verifyRequest(Request $request)
    {
        $payload = Collection::make(json_decode($request->getContent(), true));
        if ($payload->get('challenge') && $payload->get('verification_token')) {
            return JsonResponse::create([
                "success"   => true,
                "challenge" => $payload->get('challenge'),
            ])->send();
        }
    }

    /**
     * @param string|Question|IncomingMessage $message
     * @param IncomingMessage $matchingMessage
     * @param array $additionalParameters
     * @return Response
     */
    public function buildServicePayload($message, $matchingMessage, $additionalParameters = [])
    {
        $recipient = $matchingMessage->getRecipient();
        if ($recipient === '' || is_null($recipient)) {
            $recipient = $this->config->get('number_id');
        }

        $payload = array_merge_recursive([
            'type' => 'text',
        ], $additionalParameters);

        /*
         * If we send a Question with buttons, ignore
         * the text and append the question.
         */
        if ($message instanceof Question) {
            $payload['text'] = $message->getText();
        } elseif ($message instanceof OutgoingMessage) {
            $payload['text'] = $message->getText();
        } else if (\is_array($message)) {
            $payload = array_merge_recursive($message, $additionalParameters);
        } else {
            $payload['text'] = $message;
        }

        return [
            'identifier' => $recipient . ':' . $matchingMessage->getSender(),
            'payload'    => $payload,
        ];
    }

    /**
     * @param mixed $payload
     * @return Response
     */
    public function sendPayload($payload)
    {
        $headers = [
            'Content-Type:application/vnd.messengerpeople.v1+json',
            'Accept:application/vnd.messengerpeople.v1+json',
            'Authorization:Bearer ' . $this->getAccessToken(),
        ];

        return $this->http->post(self::API_URL . '/messages', [], $payload, $headers, true);
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return !empty($this->config->get('client_id')) && !empty($this->config->get('client_secret'));
    }

    /**
     * Low-level method to perform driver specific API requests.
     *
     * @param string $endpoint
     * @param array $parameters
     * @param IncomingMessage $matchingMessage
     * @return Response
     */
    public function sendRequest($endpoint, array $parameters, IncomingMessage $matchingMessage)
    {
        $headers = [
            'Content-Type:application/vnd.messengerpeople.v1+json',
            'Accept:application/vnd.messengerpeople.v1+json',
            'Authorization:Bearer ' . $this->getAccessToken(),
        ];

        return $this->http->post(self::API_URL . '/' . $endpoint, [], $parameters, $headers, true);
    }
}
