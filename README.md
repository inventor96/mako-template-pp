# Mako Template++
Additional filters I use within the Mako templating system, with the ability to register more.

## Installation
1. Install the composer package:
    ```bash
    composer require inventor96/mako-template-pp
    ```

1. Enable the package in Mako:  
    `app/config/application.php`:
    ```php
    [
        'packages' => [
            'web' => [
                \inventor96\MakoTemplatePP\TemplatePackage::class
            ],
        ],
    ];
    ```
    This will automatically register the `TemplatePP` class in the Mako View Factory as the default renderer for `.tpl.php` files.

## Configuration
Configuration options are available for only the `time` filter at this time. To customize these options, create a configuration file at `app/config/packages/templatepp/template.php` with the following structure:

```php
<?php
return [
	/**
	 * The `time:` template filter.
	 */
	'time' => [
		/**
		 * The format to use when displaying date/time values.
		 * Defaults to 'D, j M Y g:i:s a T'.
		 */
		'format' => 'D, j M Y g:i:s a T',

		/**
		 * The string to display when the date/time value is empty.
		 * Defaults to '---'.
		 */
		'empty_replacement' => '---',
	],
];
```

## Usage
### Available Filters
#### `{{ route: <route_name> [, <param>, ...] }}`
Generates a URL for a named route defined in the [Mako routing system](https://makoframework.com/docs/11.4/routing-and-controllers:routing). Accepts additional parameters to fill in route placeholders. This ensures that URLs are generated consistently and correctly throughout the application.

#### `{{ time: <timestamp> [, <format>] [, <empty_replacement>] }}`
Formats a given timestamp into a human-readable date/time string, as defined by the `time` filter configuration. Data types include `DateTime`, [Mako's `Time`](https://makoframework.com/docs/11.4/learn-more:date-and-time#time), `string`, `false`, and `null`. The format can be overridden by providing the second parameter. If the timestamp evaluates to `empty()`, the empty replacement string will be used, as defined in the configuration, or the value of the third parameter. This is useful for displaying dates and times in a consistent format across the application.

#### `{{ pluralize: <noun> [, <count>] }}`
Alias of [Mako's `Str::pluralize()` method](https://makoframework.com/docs/11.4/learn-more:string-helper).

#### `{{ part: <partial_name> [, <param>, ...] }}`
Alias to `{{ view: 'partials.<partial_name>' [, <param>, ...] }}`, allowing new lines for code readability. Renders a partial template located in the `app/resources/views/partials/` directory. This helps to keep templates modular and reusable.

#### `{{ up: {{ ... }} }}`
Mark code for "filtering up" the compiled PHP so it can be part of a parameter in another template tag. This is particularly useful when you need to pass dynamic values to template params that require PHP code. e.g. `{{ route: 'route.name', [ 'param' => {{ up: {{ pluralize: 'value' }} }} ] }}`.

### Creating Custom Filters
#### Simple Filters
To create and register custom template filters, you need to use the `FilterRegistry` class provided by this package. You can register the filters anywhere the Mako container is available (e.g. in the `bootstrap.php` file, in a service class, in a controller, etc.). Below is an example of how to use a service class to create a custom filter that converts a string to uppercase.

```php
namespace app\services;

use inventor96\MakoTemplatePP\FilterRegistry;
use mako\application\services\Service;

class UppercaseTemplateFilter extends Service
{
	/**
	 * Registers the service.
	 */
	public function register(): void
	{
		// get the FilterRegistry from the container
		$registry = $this->container->get(FilterRegistry::class);

		// register the 'uppercase' filter
		$registry->registerFilterCallback('uppercase', function ($string) {
			return strtoupper($string);
		});
	}
}
```

Then, enable the service in your `application.php` config:

```php
[
	'services' => [
		'core' => [
			app\services\UppercaseTemplateFilter::class,
		],
	],
];
```

You can now use the `uppercase` filter in your templates:

```php
{{ uppercase: 'hello world' }} <!-- Outputs: HELLO WORLD -->
```

#### Advanced Filters
For more complex filters that require additional setup or dependencies, you can use the `registerCompilerHandler()` and `registerRenderMethod()` methods of the `FilterRegistry` class. These methods allow you to define custom compiler handlers and render methods for your filters. Compiler handlers are used to convert the template syntax into PHP code. Render methods are called during each render of the compiled PHP template. Refer to the method docblocks in `FilterRegistry` for more details. See also the `mako\view\compilers\Template` and `mako\view\renderers\Template` classes for reference on how Mako's templating system works.