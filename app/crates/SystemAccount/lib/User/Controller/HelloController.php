<?php

namespace Foh\SystemAccount\User\Controller;

use Honeybee\Infrastructure\Template\TemplateRendererInterface;
use Foh\SystemAccount\User\HelloService;

class HelloController
{
    protected $templateRenderer;

    protected $helloService;

    public function __construct(TemplateRendererInterface $templateRenderer, HelloService $helloService)
    {
        $this->templateRenderer = $templateRenderer;
        $this->helloService = $helloService;
    }

    public function read()
    {
        return $this->templateRenderer->render('@SystemAccount/user/hello.twig', [ 'message' => $this->helloService->greet() ]);
    }
}