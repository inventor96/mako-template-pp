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

## Usage
### Available Filters
#### `{{ route: <route_name> [, <param>, ...] }}`
Generates a URL for a named route defined in the [Mako routing system](https://makoframework.com/docs/11.4/routing-and-controllers:routing). Accepts additional parameters to fill in route placeholders. This ensures that URLs are generated consistently and correctly throughout the application.

#### `{{ time: <timestamp> [, <empty_replacement>] }}`
Formats a given timestamp (accepts `DateTime`, [Mako's `Time`](https://makoframework.com/docs/11.4/learn-more:date-and-time#time), `string`, and `null`) into a human-readable date/time string, as defined by the `DT_FMT_DISPLAY` constant in `bootstrap.php`. This is useful for displaying dates and times in a consistent format across the application.

#### `{{ pluralize: <noun> [, <count>] }}`
Alias of [Mako's `Str::pluralize()` method](https://makoframework.com/docs/11.4/learn-more:string-helper).

#### `{{ part: <partial_name> [, <param>, ...] }}`
Alias to `{{ view: 'partials.<partial_name>' [, <param>, ...] }}`, allowing new lines for code readability. Renders a partial template located in the `app/resources/views/partials/` directory. This helps to keep templates modular and reusable.

#### `{{ up: {{ ... }} }}`
Mark code for "filtering up" the compiled PHP so it can be part of a parameter in another template tag. This is particularly useful when you need to pass dynamic values to template params that require PHP code. e.g. `{{ route: 'route.name', [ 'param' => {{ up: {{ pluralize: 'value' }} }} ] }}`.

### Creating Custom Filters
TBD...