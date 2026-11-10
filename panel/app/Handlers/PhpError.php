<?php

namespace App\Handlers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Monolog\Logger;
use Throwable;

final class PhpError extends \Slim\Handlers\PhpError
{
    protected $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function __invoke(Request $request, Response $response, Throwable $error)
    {

        $this->logger->critical($error->getMessage(), [$error->getFile(), "Line: " . $error->getLine()]);

        $body = json_encode([
            'error' => "Something went wrong!",
            'code'  => $error->getCode(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        // parent::__invoke($request, $response,  $error);

        return $response->withStatus(500)
            ->withHeader('Content-Type', 'text/html')
            ->write($body);
    }
}
