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

    /**
     * @param HttpKernelInterface $app
     * @param string              $publisherOrigin
     */
    public function __construct(HttpKernelInterface $app, $publisherOrigin)
    {
        $this->app = $app;
        if (!$this->isSecureUri($publisherOrigin)) {
            throw new \InvalidArgumentException('Publisher origin protocol needs to be https');
        }
        $this->publisherOrigin = $publisherOrigin;
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
        $origin = $this->retrieveOrigin($request, $ampSourceOrigin);

        if (is_null($origin)) {
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
    private function isSecureUri($uri)
    {
        return (bool)preg_match('/https:\/\//i', $uri);
    }

    /**
     * @return array
     */
    private function createValidOrigins()
    {
        return [
            $this->publisherOrigin,
            sprintf('%s.cdn.ampproject.org', str_replace('.', '-', str_replace('-', '--', $this->publisherOrigin))),
            sprintf('%s.amp.cloudflare.com', $this->publisherOrigin),
            'https://cdn.ampproject.org'
        ];
    }

    /**
     * @param Request $request
     * @param string  $ampSourceOrigin
     *
     * @return null|string
     */
    private function retrieveOrigin(Request $request, $ampSourceOrigin)
    {
        if ($this->requestContainsSameOrigin($request)) {
            return $ampSourceOrigin;
        }

        $origin = $request->headers->get(self::ORIGIN_HEADER);
        if ($this->isValidOrigin($origin) && $this->isValidAmpSourceOrigin($ampSourceOrigin)) {
            return $origin;
        }

        return null;
    }

    /**
     * @param Request $request
     *
     * @return bool
     */
    private function requestContainsSameOrigin(Request $request)
    {
        return $request->headers->get(self::AMP_SAME_ORIGIN_HEADER) == 'true';
    }

    /**
     * @param string $origin
     *
     * @return bool
     */
    private function isValidOrigin($origin)
    {
        $validOrigins = $this->createValidOrigins();

        return in_array($origin, $validOrigins);
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
