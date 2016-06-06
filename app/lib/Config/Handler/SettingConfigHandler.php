<?php

namespace Honeybee\FrameworkBinding\Silex\Config\Handler;

use Honeybee\Common\Error\ConfigError;
use Honeybee\FrameworkBinding\Silex\Config\ConfigProviderInterface;
use Honeybee\Infrastructure\Config\ConfigInterface;

class SettingConfigHandler implements ConfigHandlerInterface
{
    protected $config;

    protected $yamlParser;

    public function __construct(ConfigInterface $config, ConfigProviderInterface $configProvider)
    {
        $this->config = $config;
        $parserClass = $this->config->get('parser');
        $this->yamlParser = new $parserClass;
        $this->configProvider = $configProvider;
    }

    public function handle(array $configFiles)
    {
        return $this->interpolateConfigValues(
            array_merge_recursive(
                array_reduce(
                    array_map([ $this, 'handlConfigFile' ], $configFiles), [ $this, 'mergeConfigs' ],
                    []
                ),
                (array)$this->configProvider->getSettings()->toArray()
            )
        );
    }

    protected function handlConfigFile($configFile)
    {
        $settings = [];

        $settingsConfig = $this->yamlParser->parse(file_get_contents($configFile));

        $localConfigs = isset($settingsConfig['local_configs']) ? $settingsConfig['local_configs'] : [];
        unset($settingsConfig['local_configs']);

        foreach ($localConfigs as $prefix => $localConfig) {
            $settings[$prefix] = $this->loadLocalSettings($localConfig);
        }
        foreach ($settingsConfig as $prefix => $rawSettings) {
            $settings[$prefix] = $rawSettings;
        }

        return $settings;
    }

    protected function mergeConfigs(array $out, array $in)
    {
        return array_merge_recursive($out, $in);
    }

    protected function loadLocalSettings(array $localConfig)
    {
        $configFile = $this->configProvider->getLocalConfigDir().'/'.$localConfig['name'];
        if ($localConfig['load'] === 'from_file') {
            $settings = $this->handleFileBasedLocalConfig($configFile, $localConfig['type']);
        }
        // @todo load from env

        return $settings;
    }

    protected function handleFileBasedLocalConfig($configFile, $type)
    {
        if ($type === 'yaml') {
            $yamlString = file_get_contents($configFile);
            if (!$yamlString) {
                throw new ConfigError('Failed to read local configuration at: '.$configFile);
            };
            try {
                $settings = $yaml_parser->parse($yamlString);
            } catch (\Exception $parseError) {
                throw new ConfigError(
                    'Failed to parse yaml for local config file: '.$configFile.PHP_EOL .
                    'Error: '.$parseError->getMessage()
                );
            }
        } elseif ($type === 'json') {
            $jsonString = file_get_contents($configFile);
            if (!$jsonString) {
                throw new Exception('Failed to read local configuration at: '.$configFile);
            }
            $settings = json_decode($jsonString, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ConfigError('Failed to parse json from file "'.$confgFile.'": '.json_last_error_msg());
            }
        } else {
            throw new ConfigError('Only "yaml" or "json" are supported for "type" setting of local-configs.');
        }

        return $settings;
    }

    protected function interpolateConfigValues(array $config, array $globalConf = null)
    {
        $globalConf = $globalConf ?: $config;
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $config[$key] = $this->interpolateConfigValues($value, $globalConf);
            } else if (is_string($value)) {
                if (preg_match_all('/(\$\{(.*?)\})/', $value, $matches)) {
                    $replacements = [];
                    foreach ($matches[2] as $configKey) {
                        $replacements[] = $this->interpolateConfigValue($configKey, $globalConf);
                    }
                    $config[$key] = str_replace($matches[0], $replacements, $value);
                }
            }
        }

        return $config;
    }

    protected function interpolateConfigValue($key, array $globalConf)
    {
        $pathParts = explode('.', $key);
        $value = &$globalConf;

        do {
            $curKey = array_shift($pathParts);
            $value = &$value[$curKey];
        } while (!empty($pathParts));

        return $value;
    }
}
