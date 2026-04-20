<?php

namespace Symfonicat\Maker;

use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

final class MakeModule extends AbstractMaker
{
    public static function getCommandName(): string
    {
        return 'make:module';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a new module controller, JS entry, and module package manifest';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('name', InputArgument::OPTIONAL, 'Module name (e.g. Analytics)')
            ->addArgument('id', InputArgument::OPTIONAL, 'Module id (e.g. analytics)')
        ;
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
        if (!$input->getArgument('name')) {
            $input->setArgument('name', $io->ask('Module name', null, static function ($value) {
                if (!\is_string($value) || trim($value) === '') {
                    throw new \RuntimeException('Name cannot be empty.');
                }

                return trim($value);
            }));
        }

        if (!$input->getArgument('id')) {
            $input->setArgument('id', $io->ask('Module id', null, static function ($value) {
                if (!\is_string($value) || trim($value) === '') {
                    throw new \RuntimeException('Module id cannot be empty.');
                }

                return trim($value);
            }));
        }
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $name = trim((string) $input->getArgument('name'));
        $id = trim((string) $input->getArgument('id'));

        $controllerClass = Str::asClassName($name, 'Controller');
        $serviceClass = Str::asClassName($name, 'Service');
        $serviceVar = lcfirst($serviceClass);
        $routeName = Str::asRouteName('app_module_'.$id);

        $controllerPath = 'src/Symfonicat/Controller/Module/'.$controllerClass.'.php';
        $controllerContents = <<<PHP
<?php

namespace Symfonicat\Controller\Module;

use Symfonicat\Controller\AbstractModuleController;
use Symfonicat\Service\Module\\{$serviceClass};
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/m/{$id}')]
final class {$controllerClass} extends AbstractModuleController
{
    #[Route('', name: '{$routeName}', methods: ['POST'])]
    public function index({$serviceClass} \${$serviceVar}): Response
    {
        return \$this->module(new JsonResponse([
            'working' => true,
        ]));
    }
}
PHP;

        $servicePath = 'src/Symfonicat/Service/Module/'.$serviceClass.'.php';
        $serviceContents = <<<PHP
<?php

namespace Symfonicat\Service\Module;

final class {$serviceClass}
{
}
PHP;

        $jsPath = 'assets/modules/'.$id.'/index.js';
        $jsContents = <<<JS
'{$id}'.log('module active!')

const run = async () => {
    
    const result = await '{$id}'.json({
        test: true,
    })

    '{$id}'.log('/m/{$id} result:', result)
}

run()

JS;

        $packagePath = 'assets/modules/'.$id.'/package.json';
        $packageContents = (string) json_encode([
            'name' => $name,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $generator->dumpFile($controllerPath, $controllerContents);
        $generator->dumpFile($servicePath, $serviceContents);
        $generator->dumpFile($jsPath, $jsContents);
        $generator->dumpFile($packagePath, $packageContents);

        $generator->writeChanges();

        $this->writeSuccessMessage($io);
        $io->text([
            'Module files created. Run this to synchronize the database row:',
            'bin/console symfonicat:schema:update',
        ]);
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }
}
