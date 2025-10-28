<?php
namespace inventor96\MakoTemplatePP;

use InvalidArgumentException;
use mako\syringe\Container;
use mako\syringe\traits\ContainerAwareTrait;
use ReflectionClass;
use RuntimeException;

class FilterRegistry
{
	use ContainerAwareTrait;

	/**
	 * Built-in method names of TemplatePPRenderer.
	 * Computed at runtime in the constructor.
	 *
	 * @var array
	 */
	protected array $builtin_methods = [];

	/**
	 * Known filters.
	 *
	 * @var array
	 */
	protected array $filters = [
		// mako framework filters
		'preserve',
		'attribute',
		'js',
		'css',
		'url',
		'raw',
		'capture',
		'view',

		// templatepp filters
		'up',
		'part',
		'route',
		'pluralize',
		'time',
	];

	/**
	 * Registered compiler handlers.
	 *
	 * @var array
	 */
	protected array $compilerHandlers = [];

	/**
	 * Registered render methods.
	 *
	 * @var array
	 */
	protected array $renderMethods = [];

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		// list all methods of the TemplatePPRenderer class
		$reflection = new ReflectionClass(TemplatePPRenderer::class);
		$this->builtin_methods = array_map(fn($method) => $method->getName(), $reflection->getMethods());
	}

	/**
	 * Sets the container instance.
	 */
	public function setContainer(Container $container): void
	{
		$this->container = $container;

		// make sure we're registered as a singleton (e.g. if filters are registered in a service,
		// because app services are processed before packages)
		if (!$this->container->has(static::class)) {
			$this->container->registerSingleton([static::class, 'filterRegistry'], static::class);
			$this->container->registerInstance(static::class, $this);
		}
	}

	/**
	 * Registers a custom filter callback.
	 * Deduplication is performed based on the filter name, but is limited to known filters and
	 * filters registered via this method. Filter functionality registered via
	 * `registerCompilerHandler` and `registerRenderMethod` cannot be deduplicated, so care should
	 * be taken when registering filters to avoid conflicts.
	 *
	 * @param string   $name     The name of the filter.
	 * @param callable $callback The callback to be executed for the filter.
	 * @param string|int|null $priority The priority of the handler during compilation. `null` will
	 *     cause the handler to be added between the before and after handlers for the `{{ up: }}`
	 *     filter. Use the name of an existing step to insert the handler before it, or an integer
	 *     to insert it at a specific position using `array_splice()`.
	 *
	 * @throws InvalidArgumentException If the filter name is invalid.
	 * @throws RuntimeException         If a filter with the same name is already registered.
	 */
	public function registerFilterCallback(string $name, callable $callback, string|int|null $priority = null): void
	{
		// syntax check
		if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
			throw new InvalidArgumentException("Filter name '{$name}' is not valid. Only alphanumeric characters and underscores are allowed.");
		}

		// prevent overwriting existing filters
		if (isset($this->filters[$name])) {
			throw new RuntimeException("A known filter with the name '{$name}' is already registered.");
		}

		// register the method
		$method_name = "{$name}_filter_callback";
		$this->registerRenderMethod($method_name, $callback);

		// register the compiler handler
		$pattern = '/{{\s*' . preg_quote($name, '/') . '(?:\:\s*(.*?))?\s*?}}/s';
		$compiler_handler = function (string $template_content) use ($pattern, $method_name): string {
			return preg_replace($pattern, "<?php echo \$this->{$method_name}(\$1); ?>", $template_content);
		};
		$this->registerCompilerHandler($compiler_handler, $priority);
	}

	/**
	 * Registers a compiler handler. Compiler handlers are called when converting a Mako template to PHP.
	 * Typical usage is to use a regex to find custom template tags and replace them with PHP code.
	 * This method does not check for duplicate handlers; it's up to the caller to manage that. The
	 * handler should be a callable that accepts a string (the template content) and returns a
	 * string (the modified template content).
	 *
	 * @param callable        $handler  The compiler handler.
	 * @param string|int|null $priority The priority of the handler during compilation. `null` will
	 *     cause the handler to be added between the before and after handlers for the `{{ up: }}`
	 *     filter. Use the name of an existing step to insert the handler before it, or an integer
	 *     to insert it at a specific position using `array_splice()`.
	 *
	 * @return self
	 */
	public function registerCompilerHandler(callable $handler, string|int|null $priority = null): self
	{
		$this->compilerHandlers[] = [
			'handler' => $handler,
			'priority' => $priority,
		];
		return $this;
	}

	/**
	 * Registers a render method. Render methods are available inside a compiled template.
	 * Typical usage involves a compiler handler that converts custom template tags into calls to
	 * these render methods.
	 *
	 * @param string   $method_name The name of the method to be called from the template.
	 * @param callable $handler     The render handler.
	 *
	 * @return self
	 */
	public function registerRenderMethod(string $method_name, callable $handler): self
	{
		// syntax check
		if (!preg_match('/^[a-zA-Z0-9_]+$/', $method_name)) {
			throw new InvalidArgumentException("Render method name '{$method_name}' is not valid. Only alphanumeric characters and underscores are allowed.");
		}

		// prevent overwriting existing methods
		if (isset($this->renderMethods[$method_name]) || in_array($method_name, $this->builtin_methods, true)) {
			throw new RuntimeException("A render method with the name '{$method_name}' is already registered.");
		}

		$this->renderMethods[$method_name] = $handler;
		return $this;
	}

	/**
	 * Returns the registered compiler handlers.
	 *
	 * @return array
	 */
	public function getCompilerHandlers(): array
	{
		return $this->compilerHandlers;
	}

	/**
	 * Returns a render method by name.
	 * @param string $method_name
	 * @return callable|null
	 */
	public function getRenderMethod(string $method_name): ?callable
	{
		return $this->renderMethods[$method_name] ?? null;
	}
}
