<?php
namespace inventor96\MakoTemplatePP;

use DateTime;
use mako\application\Application;
use mako\config\Config;
use mako\file\FileSystem;
use mako\http\routing\Routes;
use mako\http\routing\URLBuilder;
use mako\session\Session;
use mako\view\renderers\Template;
use RuntimeException;

class TemplatePPRenderer extends Template {
	protected ?FilterRegistry $filter_registry = null;

	public function __construct(
		protected FileSystem $fs,
		protected Application $app,
		protected URLBuilder $builder,
		protected Session $session,
		protected Config $config,
		protected Routes $routes,
	) {
		// path is the same one used in the `ViewFactoryService`
		parent::__construct($fs, "{$app->getStoragePath()}/cache/views");

		// only load the filter registry if it exists in the container (i.e. something has probably been registered in it)
		if ($this->app->getContainer()->hasInstanceOf(FilterRegistry::class)) {
			$this->filter_registry = $this->app->getContainer()->get(FilterRegistry::class);
		}
	}

	/**
	 * Override to use our template add-ons
	 *
	 * @param string $view
	 * @return void
	 * 
	 * @codeCoverageIgnore
	 */
	protected function compile(string $view): void {
		(new TemplatePPCompiler(
			$this->fileSystem,
			$this->cachePath,
			$view,
			$this->filter_registry
		))->compile();
	}

	/**
	 * Magic method to handle calls to custom render methods registered in the FilterRegistry.
	 *
	 * @param string $name
	 * @param array $arguments
	 * @return mixed
	 */
	public function __call($name, $arguments) {
		// check if we have a filter registry
		if ($this->filter_registry === null) {
			throw new RuntimeException("Method '{$name}' not found.");
		}

		// check if the method is registered
		if (!($method = $this->filter_registry->getRenderMethod($name))) {
			throw new RuntimeException("Method '{$name}' not found.");
		}

		// call the registered method
		return call_user_func($method, ...$arguments);
	}

	/**
	 * Replace route name and params to generate URL
	 *
	 * @param string $route_name
	 * @param array $params
	 * @return string
	 */
	protected function genRoute(string $route_name, array $params = []): string {
		return $this->builder->toRoute($route_name, $params);
	}

	/**
	 * Formats a DateTime object (including Mako `Time` objects), or a DateTime string to a humanly-readable string.
	 *
	 * @param DateTime|string|false|null $obj
	 * @param string|null $empty_replacement If $obj is empty, what replacement string should be returned instead.
	 * @param string|null $format Optional format string to override the default format.
	 * @return string
	 */
	protected function dateDisplay(DateTime|string|false|null $obj, ?string $format = null, ?string $empty_replacement = null): string {
		// empty replacement
		if (empty($obj)) {
			return $empty_replacement ?? $this->config->get('templatepp::template.time.empty_replacement', '---');
		}

		// convert it
		if (!is_a($obj, DateTime::class)) {
			$obj = new DateTime($obj);
		}

		// format it
		return $obj->format($format ?? $this->config->get('templatepp::template.time.format', 'D, j M Y g:i:s a T'));
	}
}