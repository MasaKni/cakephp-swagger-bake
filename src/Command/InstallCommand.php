<?php
declare(strict_types=1);

namespace SwaggerBake\Command;

use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use SwaggerBake\Lib\Configuration;
use SwaggerBake\Lib\Factory\SwaggerFactory;

class InstallCommand extends Command
{
    /**
     * Writes a swagger.json file
     *
     * @param Arguments $args
     * @param ConsoleIo $io
     * @return int|void|null
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $io->hr();
        $io->out("| SwaggerBake Install");
        $io->hr();

        $io->info('This will create, but not overwrite config/swagger.yml and config/swagger_bake.php');

        $io->out(
            'If your API exists in a plugin or you have some other non-standard setup, please follow ' .
            'the manual installation steps.'
        );

        if (strtoupper($io->ask('Continue?', 'Y')) !== 'Y') {
            return;
        }

        $assets = __DIR__ . DS . '..' . DS . '..' . DS  . 'assets';
        if (!dir($assets)) {
            $io->error('Unable to locate assets directory, please install manually');
            return;
        }

        if (!copy("$assets/swagger.yml", CONFIG . 'swagger.yml')) {
            $io->error('Unable to copy swagger.yml, check permissions');
            return;
        }

        if (!copy("$assets/swagger_bake.php", CONFIG . 'swagger_bake.php')) {
            $io->error('Unable to copy swagger_bake.php, check permissions');
            return;
        }

        $io->success('Installation Complete!');

        $io->out('Now just add a route for SwaggerUI and you\'re ready to go!');
    }
}
