<?php

namespace Honeybee\FrameworkBinding\Silex\Config\Handler;

use Honeybee\Common\Error\ConfigError;

class TranslationConfigHandler extends ArrayConfigHandler
{
    protected function handleConfigFile($configFile)
    {
        $translations = $this->parse($configFile);

        if (preg_match('#^.+-(?<locale>\w+).yml$#', $configFile, $matches)) {
            return [ $matches['locale'] => $translations ];
        } else {
            return $translations;
        }
    }

    protected function mergeConfigs(array $out, array $in)
    {
        return array_merge_recursive($out, $in);
    }
}
