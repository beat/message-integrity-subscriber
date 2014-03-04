<?php

namespace GuzzleHttp\Tests\MessageIntegrity;

use GuzzleHttp\Client;
use GuzzleHttp\Subscriber\MessageIntegrity\StreamIntegritySubscriber;
use GuzzleHttp\Subscriber\MessageIntegrity\PhpHash;
use GuzzleHttp\Subscriber\Mock;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Stream\Stream;

class StreamIntegritySubscriberTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \GuzzleHttp\Subscriber\MessageIntegrity\MessageIntegrityException
     * @expectedExceptionMessage Message integrity check failure. Expected "fud" but got "rL0Y20zC+Fzt72VPzMSk2A==
     */
    public function testThrowsSpecificException()
    {
        $sub = new StreamIntegritySubscriber([
            'hash' => new PhpHash('md5', ['base64' => true]),
            'expected' => function (ResponseInterface $response) {
                return $response->getHeader('Content-MD5');
            }
        ]);
        $client = new Client();
        $client->getEmitter()->addSubscriber($sub);
        $client->getEmitter()->addSubscriber(new Mock([
            new Response(200, ['Content-MD5' => 'fud'], Stream::factory('foo'))
        ]));
        $request = $client->createRequest('GET', 'http://httpbin.org');
        $response = $client->send($request);
        $response->getBody()->getContents();
    }
}
