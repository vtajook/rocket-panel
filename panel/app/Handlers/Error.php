<?php

namespace App\Handlers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Monolog\Logger;

final class Error extends \Slim\Handlers\Error
{
    protected $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function __invoke(Request $request, Response $response, \Exception $exception)
    {
        // Log the message
        $this->logger->critical($exception->getMessage());

        $body = json_encode([
            'error' => "Something went wrong!",
            'code'  => $exception->getCode(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return $response->withStatus(500)
            ->withHeader('Content-Type', 'text/html')
            ->write($body);
        // return parent::__invoke($request, $response,  $body );
    }
}
