<?php

namespace Allway\Chat\Classes\Helpers;

use Cms\Twig\DebugExtension;
use Cms\Twig\Extension as CmsTwigExtension;
use Illuminate\Support\Facades\App;
use System\Twig\SecurityPolicy as TwigSecurityPolicy;
use Twig\Environment;

class Lazy
{
    public static function twigRawParser($content = null, array $data = [])
    {
        try {
            /** @var Environment $twig */
            $twig = App::make('twig.environment');

            $twig->addExtension(new CmsTwigExtension());
            $twig->addExtension(new DebugExtension());
            TwigSecurityPolicy::addExtensionToTwig($twig);

            $template = $twig->createTemplate($content);

            return $template->render($data);

        } catch (\Exception $exception) {
            return \Twig::parse((string)$content, $data);
        }
    }
}
