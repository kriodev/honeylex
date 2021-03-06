<?php

namespace Honeybee\FrameworkBinding\Silex\Console\Command\Resource;

use Honeybee\FrameworkBinding\Silex\Config\ConfigProviderInterface;
use Honeybee\FrameworkBinding\Silex\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Finder\Finder;
use Trellis\CodeGen\Parser\Schema\EntityTypeSchemaXmlParser;

abstract class ResourceCommand extends Command
{
    public function __construct(
        ConfigProviderInterface $configProvider,
        Finder $fileFinder
    ) {
        parent::__construct($configProvider);
        $this->fileFinder = $fileFinder;
    }

    protected function listCrates(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion('Please select a crate: ', $this->configProvider->getCrateMap()->getKeys());
        return $helper->ask($input, $output, $question);
    }

    protected function listResources(InputInterface $input, OutputInterface $output, $cratePrefix)
    {
        $helper = $this->getHelper('question');
        $crate = $this->configProvider->getCrateMap()->getItem($cratePrefix);
        $finder = clone $this->fileFinder;
        $foundSchemas = $finder->in($crate->getRootDir())->name('aggregate_root.xml');
        $resource_names = [];
        foreach (iterator_to_array($foundSchemas, true) as $fileInfo) {
            $entitySchema = (new EntityTypeSchemaXmlParser)->parse($fileInfo->getPathname());
            $typeDefinition = $entitySchema->getEntityTypeDefinition();
            $resource_names[] = $typeDefinition->getName();
        }
        $question = new ChoiceQuestion('Please select a resource: ', $resource_names);
        return $helper->ask($input, $output, $question);
    }
}
