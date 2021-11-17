<?php
declare(strict_types=1);

namespace SwaggerBake\Lib;

use Cake\Event\Event;
use Cake\Event\EventManager;
use SwaggerBake\Lib\Exception\SwaggerBakeRunTimeException;
use SwaggerBake\Lib\Model\ModelScanner;
use SwaggerBake\Lib\OpenApi\Path;
use SwaggerBake\Lib\OpenApi\Schema;
use Symfony\Component\Yaml\Yaml;

/**
 * Class Swagger
 *
 * @package SwaggerBake\Lib
 */
class Swagger
{
    /**
     * @var string
     */
    private const ASSETS = __DIR__ . DS . '..' . DS . '..' . DS . 'assets' . DS;

    private array $array = [];

    private Configuration $config;

    /**
     * @param \SwaggerBake\Lib\Model\ModelScanner $modelScanner ModelScanner instance
     * @throws \ReflectionException
     */
    public function __construct(ModelScanner $modelScanner)
    {
        $this->config = $modelScanner->getConfig();

        $this->array = (new OpenApiFromYaml())->build(Yaml::parseFile($this->config->getYml()));

        EventManager::instance()->dispatch(
            new Event('SwaggerBake.initialize', $this)
        );

        $xSwaggerBake = Yaml::parseFile(self::ASSETS . 'x-swagger-bake.yaml');

        $this->array['x-swagger-bake'] = array_merge_recursive(
            $xSwaggerBake['x-swagger-bake'],
            $this->array['x-swagger-bake'] ?? []
        );

        $this->array = (new OpenApiSchemaGenerator($modelScanner))->generate($this->array);
        $this->array = (new OpenApiPathGenerator($this, $modelScanner->getRouteScanner(), $this->config))
            ->generate($this->array);
    }

    /**
     * Returns OpenAPI 3.0 specification as an array
     *
     * @return array
     */
    public function getArray(): array
    {
        foreach ($this->array['paths'] as $method => $paths) {
            foreach ($paths as $pathId => $path) {
                if ($path instanceof Path) {
                    $this->array['paths'][$method][$pathId] = $path->toArray();
                }
            }
        }

        foreach ($this->array['components']['schemas'] as $schema) {
            if (!is_array($schema)) {
                $schema->toArray();
            }
        }

        ksort($this->array['paths'], SORT_STRING);
        if (is_array($this->array['components']['schemas'])) {
            uksort($this->array['components']['schemas'], function ($a, $b) {
                return strcasecmp(
                    preg_replace('/\s+/', '', $a),
                    preg_replace('/\s+/', '', $b)
                );
            });
        }

        if (empty($this->array['components']['schemas'])) {
            unset($this->array['components']['schemas']);
        }
        if (empty($this->array['components'])) {
            unset($this->array['components']);
        }

        return $this->array;
    }

    /**
     * @param array $array openapi array
     * @return $this
     */
    public function setArray(array $array)
    {
        $this->array = $array;

        return $this;
    }

    /**
     * Returns OpenAPI 3.0 spec as a JSON string
     *
     * @return false|string
     */
    public function toString()
    {
        EventManager::instance()->dispatch(
            new Event('SwaggerBake.beforeRender', $this)
        );

        return json_encode($this->getArray(), $this->config->get('jsonOptions'));
    }

    /**
     * Writes OpenAPI 3.0 spec to a file using the $output argument as a file path
     *
     * @param string $output Absolute file path
     * @return void
     */
    public function writeFile(string $output): void
    {
        if (!is_writable($output)) {
            throw new SwaggerBakeRunTimeException("Output file is not writable, given $output");
        }

        file_put_contents($output, $this->toString());

        if (!file_exists($output)) {
            throw new SwaggerBakeRunTimeException("Error encountered while writing swagger file to $output");
        }
    }

    /**
     * Returns a schema object by $name argument
     *
     * @param string $name Name of schema
     * @return \SwaggerBake\Lib\OpenApi\Schema|null
     */
    public function getSchemaByName(string $name): ?Schema
    {
        if (isset($this->array['components']['schemas'][$name])) {
            return $this->array['components']['schemas'][$name];
        }

        return $this->array['x-swagger-bake']['components']['schemas'][$name] ?? null;
    }

    /**
     * Return the configuration
     *
     * @return \SwaggerBake\Lib\Configuration
     */
    public function getConfig(): Configuration
    {
        return $this->config;
    }

    /**
     * Returns an array of Operation objects that do not have a 200-299 HTTP status code
     *
     * @return \SwaggerBake\Lib\OpenApi\Operation[]
     */
    public function getOperationsWithNoHttp20x(): array
    {
        $operations = [];

        foreach ($this->array['paths'] as $path) {
            if (!$path instanceof Path) {
                continue;
            }

            $operations = array_merge(
                $operations,
                array_filter($path->getOperations(), function ($operation) {
                    return !$operation->hasSuccessResponseCode();
                })
            );
        }

        return $operations;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->toString();
    }
}
