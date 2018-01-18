<?php

namespace Zmc\Stack;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class AmpCorsMiddleware implements HttpKernelInterface
{
    const AMP_SOURCE_ORIGIN_PARAMETER = '__amp_source_origin';
    const ORIGIN_HEADER = 'Origin';
    const AMP_SAME_ORIGIN_HEADER = 'AMP-Same-Origin';

    /** @var HttpKernelInterface */
    private $app;

    /** @var string */
    private $publisherOrigin;

    private $validOrigins = [
        '{$publisherOrigin}',
        '{$publisherOriginFormatted}.cdn.ampproject.org',
        '{$publisherOrigin}.amp.cloudflare.com',
        'https://cdn.ampproject.org'
    ];

    /**
     * @param HttpKernelInterface $app
     * @param string              $publisherOrigin
     */
    public function __construct(HttpKernelInterface $app, $publisherOrigin)
    {
        $this->app = $app;
        if (!$this->containsProtocol($publisherOrigin)) {
            throw new \InvalidArgumentException('Publisher origin needs protocol');
        }
        $this->publisherOrigin = $publisherOrigin;
        $this->prepareValidOrigins();
    }

    /**
     * {@inheritDoc}
     */
    public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true)
    {
        if (!$this->isAmpRequest($request)) {
            return $this->app->handle($request, $type, $catch);
        }

        $ampSourceOrigin = $request->query->get(self::AMP_SOURCE_ORIGIN_PARAMETER);
        if ($this->requestContainsSameOrigin($request)) {
            $origin = $ampSourceOrigin;
        } elseif ($this->isValidOrigin($request->headers->get(self::ORIGIN_HEADER)) &&
            $this->isValidAmpSourceOrigin($ampSourceOrigin)) {
            $origin = $request->headers->get(self::ORIGIN_HEADER);
        } else {
            return $this->createUnauthorizedResponse('Unauthorized Request');
        }

        $request->query->remove(self::AMP_SOURCE_ORIGIN_PARAMETER);
        $response = $this->app->handle($request, $type, $catch);
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('AMP-Access-Control-Allow-Source-Origin', $ampSourceOrigin);
        $response->headers->set('Access-Control-Expose-Headers', 'AMP-Access-Control-Allow-Source-Origin');

        return $response;
    }

    /**
     * @param string $uri
     *
     * @return bool
     */
    private function containsProtocol($uri)
    {
        return (bool) preg_match('/https:\/\//i', $uri);
    }

    private function prepareValidOrigins()
    {
        $validOrigins = [];
        $publisherOriginFormatted = str_replace('.', '-', str_replace('-', '--', $this->publisherOrigin));

        foreach ($this->validOrigins as $validOrigin) {
            $validOrigins[] = strstr(
                $validOrigin,
                [
                    '{$publisherOrigin}' => $this->publisherOrigin,
                    '{$publisherOriginFormatted}' => $publisherOriginFormatted
                ]
            );
        }

        $this->validOrigins = $validOrigins;
    }

    /**
     * @param Request $request
     *
     * @return bool
     */
    private function requestContainsSameOrigin(Request $request)
    {
        if (!$this->isAmpRequest($request)) {
            return false;
        }

        return $request->headers->get(self::AMP_SAME_ORIGIN_HEADER) == 'true';
    }

    /**
     * @param string $origin
     *
     * @return bool
     */
    private function isValidOrigin($origin)
    {
        if (is_null($origin)) {
            return false;
        }

        foreach ($this->validOrigins as $validOrigin) {
            if ($origin === $validOrigin) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $ampSourceOrigin
     *
     * @return bool
     */
    private function isValidAmpSourceOrigin($ampSourceOrigin)
    {
        return $ampSourceOrigin === $this->publisherOrigin;
    }

    /**
     * @param string $message
     *
     * @return Response
     */
    private function createUnauthorizedResponse($message)
    {
        return new Response($message, 401);
    }

    /**
     * @param Request $request
     *
     * @return bool
     */
    private function isAmpRequest(Request $request)
    {
        return !is_null($request->query->get(self::AMP_SOURCE_ORIGIN_PARAMETER));
    }
}
