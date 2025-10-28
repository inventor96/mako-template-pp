<?php
namespace inventor96\MakoTemplatePP;

use mako\file\FileSystem;
use mako\view\compilers\Template;
use RuntimeException;

/**
 * An extension to the original templating engine included with Mako.
 * 
 * The compile order is adjusted in the constructor, and any methods called from a template are defined in TemplatePPRenderer.php.
 */
class TemplatePPCompiler extends Template {
	/**
	 * Constructor.
	 *
	 * @param FileSystem $fs
	 * @param string $cache_path
	 * @param string $template
	 * @param FilterRegistry|null $filter_registry If the filter registry is provided, extra filters will be included.
	 */
	public function __construct(FileSystem $fs, string $cache_path, string $template, protected ?FilterRegistry $filter_registry = null) {
		parent::__construct($fs, $cache_path, $template);

		// find our desired location in the compile order
		$location = array_search('views', $this->compileOrder);

		// check for incompatible Mako version
		if ($location === false) {
			throw new RuntimeException("Incompatible Mako version: 'views' not found in compile order.");
		}

		// insert our custom compilation methods into the compile order
		array_splice($this->compileOrder, $location, 0, [
			'filterUps',
			'routes',
			'pluralize',
			'times',
			'filterDowns',
			'partials',
		]);

		// if we have a filter registry, register any custom compiler handlers
		if ($filter_registry !== null) {
			foreach ($filter_registry->getCompilerHandlers() as $index => $handler_info) {
				// set default position if none provided
				if ($handler_info['priority'] === null) {
					$handler_info['priority'] = 'filterDowns';
				}

				// determine position
				$position = is_int($handler_info['priority'])
					? $handler_info['priority']
					: array_search($handler_info['priority'], $this->compileOrder, true);

				// insert handler
				if ($position === false) {
					throw new RuntimeException("Invalid compile order position: '{$handler_info['priority']}' not found.");
				}
				array_splice($this->compileOrder, $position, 0, ["custom_handler_{$index}"]);
			}
		}
	}

	/**
	 * Magic method to handle calls to custom compiler handlers.
	 *
	 * @param string $name
	 * @param array $arguments
	 * @return mixed
	 */
	public function __call($name, $arguments) {
		// handle custom compiler handlers
		if (str_starts_with($name, 'custom_handler_') && $this->filter_registry !== null) {
			$index = (int)substr($name, strlen('custom_handler_'));
			$handler_info = $this->filter_registry->getCompilerHandlers()[$index] ?? null;
			if ($handler_info !== null) {
				return call_user_func($handler_info['handler'], $arguments[0]);
			} else {
				throw new RuntimeException("Compiler handler at index {$index} not found in FilterRegistry.");
			}
		}

		throw new RuntimeException("Undefined method called in TemplatePPCompiler: '{$name}'");
	}

	/**
	 * Mark code for filtering up the compiled PHP so it can be part of a parameter in another template tag.
	 *
	 * @param string $template
	 * @return string
	 */
	protected function filterUps(string $template): string {
		return preg_replace('/{{\s*up:\s*({{.+?}})\s*}}/i', '__BEGIN_FILTER_UP__$1__END_FILTER_UP__', $template);
	}

	/**
	 * Unmark code for filtering up and remove the extra PHP stuff so it's ready to be passed into a parameter for another template tag.
	 *
	 * @param string $template
	 * @return string
	 */
	protected function filterDowns(string $template): string {
		return preg_replace_callback('/__BEGIN_FILTER_UP__(.*?)__END_FILTER_UP__/', function($m) {
			// remove php open/close stuff
			$m[1] = preg_replace('/^\s*<\?(?:php\s+(?:echo\s+)?|=)/i', '', $m[1]);
			return preg_replace('/;?\s*\?>\s*$/', '', $m[1]);
		}, $template);
	}

	/**
	 * Alias to {{ view: 'partials.*' [, ...] }}, allowing new lines for code readability
	 *
	 * @param string $template
	 * @return string
	 */
	protected function partials(string $template): string {
		// compile with parameters
		$template = preg_replace_callback('/{{\s*part:(.+?)\s*,(?![^\(]*\))\s*(.*?)\s*}}/smi', function($m) {
			return '{{view:\'partials.\'.'.$m[1].', '.preg_replace('/\r?\n\s*/', ' ', $m[2]).'}}';
		}, $template);

		// compile without parameters
		return preg_replace('/{{\s*part:(.+?)\s*}}/i', '{{view:\'partials.\'.$1}}', $template);
	}

	/**
	 * Compiles route names to valid URLs.
	 *
	 * @param string $template
	 * @return string
	 */
	protected function routes(string $template): string {
		// compile routes with parameters
		$template = preg_replace('/{{\s*route:(.+?)\s*,(?![^\(]*\))\s*(.*?)\s*}}/i', '<?php echo $this->genRoute($1, $2); ?>', $template);

		// compile routes without parameters
		return preg_replace('/{{\s*route:(.+?)\s*}}/i', '<?php echo $this->genRoute($1); ?>', $template);
	}

	/**
	 * Creates an alias to the `Str::pluralize()` method.
	 *
	 * @param string $template
	 * @return string
	 */
	protected function pluralize(string $template): string {
		// compile routes with parameters
		$template = preg_replace('/{{\s*pluralize:(.+?)\s*,(?![^\(]*\))\s*(.*?)\s*}}/i', '<?php echo \mako\utility\Str::pluralize($1, $2); ?>', $template);

		// compile routes without parameters
		return preg_replace('/{{\s*pluralize:(.+?)\s*}}/i', '<?php echo \mako\utility\Str::pluralize($1); ?>', $template);
	}

	/**
	 * Formats a DateTime object to a humanly-readable string.
	 *
	 * @param string $template
	 * @return string
	 */
	protected function times(string $template): string {
		return preg_replace('/{{\s*time:(.+?)\s*}}/i', '<?php echo $this->dateDisplay($1); ?>', $template);
	}
}