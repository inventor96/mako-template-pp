<?php
namespace inventor96\MakoTemplatePP;

use mako\file\FileSystem;
use mako\view\compilers\Template;

/**
 * An extension to the original templating engine included with Mako.
 * 
 * The compile order is adjusted in the constructor, and any methods called from a template are defined in TemplatePPRenderer.php.
 */
class TemplatePPCompiler extends Template {
	public function __construct(FileSystem $fs, string $cache_path, string $template) {
		parent::__construct($fs, $cache_path, $template);

		// include our stuff
		$location = array_search('views', $this->compileOrder);
		if ($location !== false) {
			array_splice($this->compileOrder, $location, 0, [
				// our method names from this class
				'filterUps',
				'routes',
				'pluralize',
				'times',
				'filterDowns',
				'partials',
			]);
		}
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